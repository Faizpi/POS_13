<?php

declare(strict_types=1);

namespace App\Services\Accounting;

use App\Accounting\DomainException;
use App\Accounting\JournalStatus;
use App\Accounting\LineOrder;
use App\Accounting\Money;
use App\Accounting\SourceIdentity;
use App\Models\Account;
use App\Models\JournalEntry;
use Illuminate\Support\Facades\DB;

final class LedgerPersistenceService
{
    /**
     * @param  list<array{account_id: int, line_order: LineOrder, debit: ?Money, credit: ?Money, gudang_id?: ?int, contact_type?: ?string, contact_id?: ?int, description?: ?string}>  $lines
     */
    public function persist(
        SourceIdentity $sourceIdentity,
        string $journalDate,
        string $description,
        ?int $gudangId,
        ?string $contactType,
        ?int $contactId,
        array $lines,
    ): JournalEntry {
        [$debitTotal, $creditTotal, $accountIds] = $this->validate($lines);

        return DB::transaction(function () use ($sourceIdentity, $journalDate, $description, $gudangId, $contactType, $contactId, $lines, $debitTotal, $creditTotal, $accountIds): JournalEntry {
            $this->assertPostableActiveAccounts($accountIds);

            $journal = JournalEntry::query()->create([
                'source_type' => $sourceIdentity->sourceType,
                'source_id' => $sourceIdentity->sourceId,
                'journal_type' => $sourceIdentity->journalType,
                'source_version' => $sourceIdentity->sourceVersion,
                'journal_date' => $journalDate,
                'description' => $description,
                'gudang_id' => $gudangId,
                'contact_type' => $contactType,
                'contact_id' => $contactId,
                'status' => JournalStatus::Draft,
                'total_debit' => $debitTotal,
                'total_credit' => $creditTotal,
                'posting_sequence' => 0,
            ]);

            foreach ($lines as $line) {
                $journal->lines()->create([
                    'account_id' => $line['account_id'],
                    'line_sequence' => $line['line_order']->value,
                    'gudang_id' => $line['gudang_id'] ?? $gudangId,
                    'contact_type' => $line['contact_type'] ?? $contactType,
                    'contact_id' => $line['contact_id'] ?? $contactId,
                    'debit' => $line['debit']?->toDecimalString() ?? '0.00',
                    'credit' => $line['credit']?->toDecimalString() ?? '0.00',
                    'description' => $line['description'] ?? null,
                ]);
            }

            Account::query()->whereIn('id', $accountIds)->update(['is_used' => true]);

            return $journal->load('lines');
        });
    }

    /** @param list<array<string, mixed>> $lines
     * @return array{0: string, 1: string, 2: list<int>}
     */
    private function validate(array $lines): array
    {
        if (count($lines) < 2) {
            throw new DomainException('A journal requires at least two lines.');
        }

        $debitTotal = Money::fromDecimalString('0.00');
        $creditTotal = Money::fromDecimalString('0.00');
        $sequences = [];
        $accountIds = [];

        foreach ($lines as $line) {
            if (! isset($line['account_id'], $line['line_order']) || ! $line['line_order'] instanceof LineOrder) {
                throw new DomainException('Each journal line requires an account and explicit line order.');
            }

            $debit = $line['debit'] ?? null;
            $credit = $line['credit'] ?? null;

            if (($debit !== null && ! $debit instanceof Money) || ($credit !== null && ! $credit instanceof Money)) {
                throw new DomainException('Journal amounts must be Money instances.');
            }
            if (($debit !== null && ! $debit->isPositive()) || ($credit !== null && ! $credit->isPositive())) {
                throw new DomainException('Journal debit and credit amounts must be positive.');
            }
            if (($debit === null) === ($credit === null)) {
                throw new DomainException('Each journal line must have exactly one debit or credit amount.');
            }
            if (isset($sequences[$line['line_order']->value])) {
                throw new DomainException('Journal line sequence must be unique within an entry.');
            }

            $sequences[$line['line_order']->value] = true;
            $accountIds[] = $line['account_id'];
            $debitTotal = $debitTotal->add($debit ?? Money::fromDecimalString('0.00'));
            $creditTotal = $creditTotal->add($credit ?? Money::fromDecimalString('0.00'));
        }

        if (! $debitTotal->equals($creditTotal)) {
            throw new DomainException('Journal debit and credit totals must balance.');
        }

        return [$debitTotal->toDecimalString(), $creditTotal->toDecimalString(), array_values(array_unique($accountIds))];
    }

    /** @param list<int> $accountIds */
    private function assertPostableActiveAccounts(array $accountIds): void
    {
        $accounts = Account::query()->whereIn('id', $accountIds)->get()->keyBy('id');

        if ($accounts->count() !== count($accountIds)) {
            throw new DomainException('Journal lines must reference existing accounts.');
        }

        foreach ($accounts as $account) {
            if (! $account->is_active || ! $account->isPostable()) {
                throw new DomainException('Journal lines must reference active postable accounts.');
            }
        }
    }
}
