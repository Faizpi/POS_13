<?php

declare(strict_types=1);

namespace App\Services\Accounting;

use App\Accounting\JournalType;
use App\Accounting\MappingKey;
use App\Models\JournalEntry;
use App\Models\Pembelian;
use Illuminate\Support\Collection;

final readonly class HutangLedgerService
{
    public function mutationsForPurchase(Pembelian $purchase): Collection
    {
        $purchases = JournalEntry::query()
            ->where('status', 'posted')
            ->where('source_type', 'purchase')
            ->where('source_id', $purchase->id)
            ->where('journal_type', JournalType::Purchase->value)
            ->get();
        $payments = JournalEntry::query()
            ->where('status', 'posted')
            ->where('source_type', 'payment')
            ->where('contact_type', 'supplier')
            ->where('contact_id', $purchase->id)
            ->where('journal_type', JournalType::ApPayment->value)
            ->get();
        $originalIds = $purchases->pluck('id')->merge($payments->pluck('id'));
        $reversals = JournalEntry::query()
            ->where('status', 'posted')
            ->where('journal_type', JournalType::Reversal->value)
            ->whereIn('original_journal_entry_id', $originalIds)
            ->get();
        $journals = $purchases->merge($payments)->merge($reversals)
            ->sortBy(fn (JournalEntry $journal): string => sprintf('%s-%012d', $journal->journal_date->format('Y-m-d'), $journal->posting_sequence));
        $running = 0;

        return $journals->map(function (JournalEntry $journal) use (&$running): array {
            $original = $journal->original_journal_entry_id === null
                ? $journal
                : JournalEntry::query()->findOrFail($journal->original_journal_entry_id);
            $ap = app(AccountMappingService::class)->resolve(MappingKey::ApPayable, $original->journal_date->format('Y-m-d'));
            if ($ap === null) {
                return [];
            }
            $line = $journal->lines()->where('account_id', $ap->id)->first();
            if ($line === null) {
                return [];
            }
            $debit = (string) $line->debit;
            $credit = (string) $line->credit;
            $running += (int) bcmul($credit, '100', 0) - (int) bcmul($debit, '100', 0);

            return [
                'journal_id' => $journal->id,
                'journal_number' => $journal->journal_number,
                'source_type' => $journal->source_type,
                'source_id' => $journal->source_id,
                'journal_date' => $journal->journal_date->format('Y-m-d'),
                'debit' => $debit,
                'credit' => $credit,
                'running_balance' => bcdiv((string) $running, '100', 2),
                'is_reversal' => $journal->original_journal_entry_id !== null,
            ];
        })->filter()->values();
    }

    public function outstandingForPurchase(Pembelian $purchase): string
    {
        $last = $this->mutationsForPurchase($purchase)->last();

        return $last['running_balance'] ?? '0.00';
    }

    public function agingBucketForPurchase(Pembelian $purchase, \DateTimeInterface $asOf): string
    {
        if (bccomp($this->outstandingForPurchase($purchase), '0.00', 2) <= 0 || $purchase->tgl_jatuh_tempo === null) {
            return 'current';
        }
        $days = max(0, $purchase->tgl_jatuh_tempo->diffInDays($asOf, false));

        return match (true) {
            $days === 0 => 'current',
            $days <= 30 => '1-30',
            $days <= 60 => '31-60',
            $days <= 90 => '61-90',
            default => '90+',
        };
    }
}
