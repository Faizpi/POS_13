<?php

declare(strict_types=1);

namespace App\Services\Accounting;

use App\Accounting\DomainException;
use App\Accounting\JournalStatus;
use App\Accounting\JournalType;
use App\Models\JournalEntry;
use App\Models\User;
use Closure;
use Illuminate\Support\Facades\DB;

final readonly class JournalReversalService
{
    public function __construct(
        private AccountingAuthorization $authorization,
    ) {}

    public function reverse(User $actor, JournalEntry $journal, string $reason, ?Closure $afterWrites = null): JournalEntry
    {
        if (! $this->authorization->canReverseJournal($actor)) {
            throw new DomainException('The actor is not authorized to reverse journals.');
        }
        $reason = trim($reason);
        if ($reason === '') {
            throw new DomainException('Reversal reason is required.');
        }

        return DB::transaction(function () use ($actor, $journal, $reason, $afterWrites): JournalEntry {
            $original = JournalEntry::query()->with('lines')->lockForUpdate()->findOrFail($journal->id);

            if ($original->journal_type === JournalType::Reversal) {
                throw new DomainException('Reversal journals cannot be reversed.');
            }
            if ($original->status !== JournalStatus::Posted) {
                throw new DomainException('Only posted journals can be reversed.');
            }
            if (JournalEntry::query()->where('original_journal_entry_id', $original->id)->exists()) {
                throw new DomainException('The journal has already been reversed.');
            }

            $sequence = $this->nextSequence();
            $reversal = JournalEntry::query()->create([
                'original_journal_entry_id' => $original->id,
                'source_type' => $original->source_type,
                'source_id' => $original->source_id,
                'journal_type' => JournalType::Reversal,
                'source_version' => $original->source_version,
                'journal_date' => $original->journal_date,
                'journal_number' => sprintf('JRN-%s-%06d', $original->journal_date->format('Ymd'), $sequence),
                'posting_sequence' => $sequence,
                'gudang_id' => $original->gudang_id,
                'contact_type' => $original->contact_type,
                'contact_id' => $original->contact_id,
                'description' => "Reversal of {$original->journal_number}: {$reason}",
                'reversal_reason' => $reason,
                'reversed_by' => $actor->id,
                'status' => JournalStatus::Draft,
                'total_debit' => $original->total_credit,
                'total_credit' => $original->total_debit,
            ]);

            foreach ($original->lines->sortBy('line_sequence') as $line) {
                $reversal->lines()->create([
                    'account_id' => $line->account_id,
                    'line_sequence' => $line->line_sequence,
                    'gudang_id' => $line->gudang_id,
                    'contact_type' => $line->contact_type,
                    'contact_id' => $line->contact_id,
                    'debit' => $line->credit,
                    'credit' => $line->debit,
                    'description' => $line->description,
                ]);
            }

            $afterWrites?->__invoke();
            $reversal->update(['status' => JournalStatus::Approved]);
            $reversal->update(['status' => JournalStatus::Posted]);

            return $reversal->fresh(['lines' => fn ($query) => $query->orderBy('line_sequence')]);
        });
    }

    private function nextSequence(): int
    {
        DB::table('journal_posting_sequences')->insertOrIgnore([
            'sequence_key' => 'journal',
            'last_value' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $row = DB::table('journal_posting_sequences')->where('sequence_key', 'journal')->lockForUpdate()->first();
        if ($row === null) {
            throw new DomainException('Unable to allocate journal posting sequence.');
        }

        $next = (int) $row->last_value + 1;
        DB::table('journal_posting_sequences')->where('sequence_key', 'journal')->update([
            'last_value' => $next,
            'updated_at' => now(),
        ]);

        return $next;
    }
}
