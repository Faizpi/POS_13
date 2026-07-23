<?php

declare(strict_types=1);

namespace App\Services\Accounting;

use App\Accounting\JournalType;
use App\Accounting\MappingKey;
use App\Models\JournalEntry;
use App\Models\Penjualan;
use Illuminate\Support\Collection;

final readonly class PiutangLedgerService
{
    public function mutationsForSale(Penjualan $sale): Collection
    {
        $saleJournals = JournalEntry::query()
            ->where('status', 'posted')
            ->where('source_type', 'sale')
            ->where('source_id', $sale->id)
            ->where('journal_type', JournalType::Sale->value)
            ->get();
        $paymentJournals = JournalEntry::query()
            ->where('status', 'posted')
            ->where('source_type', 'payment')
            ->where('contact_type', 'customer')
            ->where('contact_id', $sale->id)
            ->where('journal_type', JournalType::ArPayment->value)
            ->get();
        $originalIds = $saleJournals->pluck('id')->merge($paymentJournals->pluck('id'));
        $reversals = JournalEntry::query()
            ->where('status', 'posted')
            ->where('journal_type', JournalType::Reversal->value)
            ->whereIn('original_journal_entry_id', $originalIds)
            ->get();

        $journals = $saleJournals->merge($paymentJournals)->merge($reversals)
            ->sortBy(fn (JournalEntry $journal): string => sprintf('%s-%012d', $journal->journal_date->format('Y-m-d'), $journal->posting_sequence));

        $running = 0;

        return $journals->map(function (JournalEntry $journal) use (&$running): array {
            $original = $journal->original_journal_entry_id === null
                ? $journal
                : JournalEntry::query()->findOrFail($journal->original_journal_entry_id);
            $arAccount = app(AccountMappingService::class)->resolve(
                MappingKey::ArReceivable,
                $original->journal_date->format('Y-m-d'),
            );
            if ($arAccount === null) {
                return [];
            }
            $line = $journal->lines()->where('account_id', $arAccount->id)->first();
            if ($line === null) {
                return [];
            }
            $debit = (string) $line->debit;
            $credit = (string) $line->credit;
            $running += (int) bcmul($debit, '100', 0) - (int) bcmul($credit, '100', 0);

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

    public function outstandingForSale(Penjualan $sale): string
    {
        $last = $this->mutationsForSale($sale)->last();

        return $last['running_balance'] ?? '0.00';
    }

    public function agingBucketForSale(Penjualan $sale, \DateTimeInterface $asOf): string
    {
        if (bccomp($this->outstandingForSale($sale), '0.00', 2) <= 0 || $sale->tgl_jatuh_tempo === null) {
            return 'current';
        }

        $days = max(0, $sale->tgl_jatuh_tempo->diffInDays($asOf, false));

        return match (true) {
            $days === 0 => 'current',
            $days <= 30 => '1-30',
            $days <= 60 => '31-60',
            $days <= 90 => '61-90',
            default => '90+',
        };
    }
}
