<?php

declare(strict_types=1);

namespace App\Services\Accounting;

use App\Accounting\DomainException;
use App\Accounting\JournalPostingCheckpoint;
use App\Accounting\JournalPostingLine;
use App\Accounting\JournalPostingRequest;
use App\Accounting\JournalPostingStage;
use App\Accounting\JournalStatus;
use App\Accounting\Money;
use App\Models\Account;
use App\Models\JournalEntry;
use App\Models\JournalSourceLock;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final readonly class JournalPostingService
{
    public function __construct(
        private AccountMappingService $mappings,
        private AccountingAuthorization $authorization,
        private JournalPostingCheckpoint $checkpoint,
    ) {}

    public function post(User $actor, JournalPostingRequest $request): JournalEntry
    {
        $this->assertAuthorized($actor);
        $date = $this->parseJournalDate($request->journalDate);
        if ($date->isAfter(CarbonImmutable::today())) {
            throw new DomainException('Future-dated journals cannot be posted.');
        }

        $existing = $this->findExisting($request);
        if ($existing !== null) {
            return $existing;
        }

        return DB::transaction(function () use ($request, $date): JournalEntry {
            $this->checkpoint->reached(JournalPostingStage::BeforeSourceLock);
            $this->acquireSourceLock($request);
            $this->checkpoint->reached(JournalPostingStage::AfterSourceLock);

            $existing = $this->findExisting($request, true);
            if ($existing !== null) {
                return $existing;
            }

            [$resolved, $total] = $this->resolveAndValidate($request, $date);
            $sequence = $this->nextSequence('journal');
            $this->checkpoint->reached(JournalPostingStage::AfterSequenceAllocated);
            $journal = JournalEntry::query()->create([
                'source_type' => $request->sourceIdentity->sourceType,
                'source_id' => $request->sourceIdentity->sourceId,
                'journal_type' => $request->sourceIdentity->journalType->value,
                'source_version' => $request->sourceIdentity->sourceVersion,
                'journal_date' => $date->toDateString(),
                'journal_number' => sprintf('JRN-%s-%06d', $date->format('Ymd'), $sequence),
                'posting_sequence' => $sequence,
                'gudang_id' => $request->gudangId,
                'contact_type' => $request->contactType,
                'contact_id' => $request->contactId,
                'description' => $request->description,
                'status' => JournalStatus::Draft,
                'total_debit' => $total->toDecimalString(),
                'total_credit' => $total->toDecimalString(),
            ]);

            foreach ($resolved as [$line, $account]) {
                $journal->lines()->create([
                    'account_id' => $account->id,
                    'line_sequence' => $line->lineOrder->value,
                    'gudang_id' => $line->gudangId ?? $request->gudangId,
                    'contact_type' => $line->contactType ?? $request->contactType,
                    'contact_id' => $line->contactId ?? $request->contactId,
                    'debit' => $line->debit?->toDecimalString() ?? '0.00',
                    'credit' => $line->credit?->toDecimalString() ?? '0.00',
                    'description' => $line->description,
                ]);
            }

            $accountIds = $resolved->pluck(1)->map(fn (Account $account): int => $account->id)->unique()->values()->all();
            if ($accountIds !== []) {
                Account::query()->whereIn('id', $accountIds)->update(['is_used' => true]);
            }
            $this->checkpoint->reached(JournalPostingStage::AfterPostingWrites);

            $journal->update(['status' => JournalStatus::Approved]);
            $journal->update(['status' => JournalStatus::Posted]);

            return $journal->fresh(['lines' => fn ($query) => $query->orderBy('line_sequence')]);
        });
    }

    private function assertAuthorized(User $actor): void
    {
        if (! $this->authorization->canPostJournal($actor)) {
            throw new DomainException('The actor is not authorized to post journals.');
        }
    }

    private function parseJournalDate(string $journalDate): CarbonImmutable
    {
        try {
            return CarbonImmutable::parse($journalDate)->startOfDay();
        } catch (\Exception $exception) {
            throw new DomainException('Invalid journal date format.', 0, $exception);
        }
    }

    private function acquireSourceLock(JournalPostingRequest $request): void
    {
        $identity = [
            'source_type' => $request->sourceIdentity->sourceType,
            'source_id' => $request->sourceIdentity->sourceId,
            'journal_type' => $request->sourceIdentity->journalType->value,
            'source_version' => $request->sourceIdentity->sourceVersion,
        ];

        try {
            JournalSourceLock::query()->create(array_merge($identity, [
                'locked_at' => now(),
            ]));
        } catch (UniqueConstraintViolationException $e) {
            // Narrow: only the identity unique constraint
            if (! $this->isLockIdentityConstraint($e)) {
                throw $e;
            }
            // Collision: another writer inserted first. Lock the canonical row.
            // lockForUpdate() blocks until the other transaction commits/rolls back.
            JournalSourceLock::query()->where($identity)->lockForUpdate()->firstOrFail();
        }
    }

    /**
     * Determine if the exception is the lock identity unique constraint.
     * Supports SQLite (driver code 19) and MySQL/MariaDB (SQLSTATE 23000, driver code 1062).
     * Uses structured errorInfo for strict verification.
     */
    private function isLockIdentityConstraint(UniqueConstraintViolationException $e): bool
    {
        $errorInfo = $e->errorInfo ?? null;
        if (! is_array($errorInfo) || count($errorInfo) < 2) {
            return false;
        }

        $sqlState = $errorInfo[0] ?? null;
        $driverCode = $errorInfo[1] ?? null;
        $message = $e->getMessage();

        // MySQL/MariaDB: SQLSTATE 23000 + driver code 1062 + exact index name
        if ($sqlState === '23000' && $driverCode === 1062) {
            if (! preg_match("/for key '(?:[^']*\\.)?([^']+)'/", $message, $matches)) {
                return false;
            }

            return $matches[1] === 'journal_source_locks_identity_unique';
        }

        // SQLite: driver code 19 + exact column list in UNIQUE constraint clause
        if ($driverCode === 19) {
            if (! preg_match('/UNIQUE constraint failed:\s*(.+?)(?:\s*\(|$)/s', $message, $matches)) {
                return false;
            }

            return trim($matches[1]) === 'journal_source_locks.source_type, journal_source_locks.source_id, journal_source_locks.journal_type, journal_source_locks.source_version';
        }

        return false;
    }

    private function findExisting(JournalPostingRequest $request, bool $lock = false): ?JournalEntry
    {
        $query = JournalEntry::query()
            ->where('source_type', $request->sourceIdentity->sourceType)
            ->where('source_id', $request->sourceIdentity->sourceId)
            ->where('journal_type', $request->sourceIdentity->journalType->value)
            ->where('source_version', $request->sourceIdentity->sourceVersion);

        if ($lock) {
            $query->lockForUpdate();
        }

        $journal = $query->first();
        if ($journal !== null && $journal->status !== JournalStatus::Posted) {
            throw new DomainException('The source identity is already reserved by an unposted journal.');
        }

        return $journal?->load(['lines' => fn ($lines) => $lines->orderBy('line_sequence')]);
    }

    /** @return array{0: Collection<int, array{JournalPostingLine, Account}>, 1: Money} */
    private function resolveAndValidate(JournalPostingRequest $request, CarbonImmutable $date): array
    {
        $debit = Money::fromDecimalString('0.00');
        $credit = Money::fromDecimalString('0.00');
        $sequences = [];
        $resolved = collect();

        foreach ($request->lines as $line) {
            if (isset($sequences[$line->lineOrder->value])) {
                throw new DomainException('Posting line order must be unique.');
            }
            $sequences[$line->lineOrder->value] = true;
            $account = $this->mappings->resolve($line->mappingKey, $date->toDateString());
            if ($account === null) {
                throw new DomainException("No mapping exists for {$line->mappingKey->value} on {$date->toDateString()}.");
            }
            if (! $account->is_active) {
                throw new DomainException("Account for {$line->mappingKey->value} is inactive.");
            }
            if (! $account->isPostable()) {
                throw new DomainException("Account for {$line->mappingKey->value} is not postable.");
            }
            $debit = $debit->add($line->debit ?? Money::fromDecimalString('0.00'));
            $credit = $credit->add($line->credit ?? Money::fromDecimalString('0.00'));
            $resolved->push([$line, $account]);
        }

        if (! $debit->equals($credit)) {
            throw new DomainException('Journal debit and credit totals must balance.');
        }

        return [$resolved->sortBy(fn (array $pair): int => $pair[0]->lineOrder->value)->values(), $debit];
    }

    private function nextSequence(string $key): int
    {
        DB::table('journal_posting_sequences')->insertOrIgnore([
            'sequence_key' => $key,
            'last_value' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $row = DB::table('journal_posting_sequences')->where('sequence_key', $key)->lockForUpdate()->first();
        if ($row === null) {
            throw new DomainException('Unable to allocate journal posting sequence.');
        }

        $next = (int) $row->last_value + 1;
        DB::table('journal_posting_sequences')->where('sequence_key', $key)->update(['last_value' => $next, 'updated_at' => now()]);

        return $next;
    }
}
