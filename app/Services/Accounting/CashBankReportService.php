<?php

declare(strict_types=1);

namespace App\Services\Accounting;

use App\Models\CashBankAccount;
use App\Models\JournalLine;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

final class CashBankReportService
{
    /**
     * @param  array{date_from?: string|null, date_to?: string|null}  $filters
     * @return array{opening_balance: string, movement_debit: string, movement_credit: string, ending_balance: string, rows: Collection<int, array<string, mixed>>}
     */
    public function report(CashBankAccount $cashBankAccount, array $filters = []): array
    {
        $from = $filters['date_from'] ?? null;
        $to = $filters['date_to'] ?? null;
        $base = JournalLine::query()
            ->with('journalEntry')
            ->where('account_id', $cashBankAccount->account_id)
            ->whereHas('journalEntry', fn (Builder $query): Builder => $query->where('status', 'posted'));

        $opening = clone $base;
        if ($from === null) {
            $opening->whereRaw('1 = 0');
        } else {
            $opening->whereHas('journalEntry', fn (Builder $query): Builder => $query->whereDate('journal_date', '<', $from));
        }
        $openingBalance = $this->balance($opening->get());

        $movement = clone $base;
        if ($from !== null) {
            $movement->whereHas('journalEntry', fn (Builder $query): Builder => $query->whereDate('journal_date', '>=', $from));
        }
        if ($to !== null) {
            $movement->whereHas('journalEntry', fn (Builder $query): Builder => $query->whereDate('journal_date', '<=', $to));
        }

        $running = $this->minor($openingBalance);
        $movementDebit = '0.00';
        $movementCredit = '0.00';
        $rows = $movement->get()->sortBy(fn (JournalLine $line): string => sprintf('%s-%020d-%010d-%020d', $line->journalEntry->journal_date->format('Y-m-d'), $line->journalEntry->posting_sequence, $line->line_sequence, $line->id))->values()->map(function (JournalLine $line) use (&$running, &$movementDebit, &$movementCredit): array {
            $debit = (string) $line->debit;
            $credit = (string) $line->credit;
            $movementDebit = bcadd($movementDebit, $debit, 2);
            $movementCredit = bcadd($movementCredit, $credit, 2);
            $running += $this->minor($debit) - $this->minor($credit);

            return [
                'journal_id' => $line->journalEntry->id,
                'journal_number' => $line->journalEntry->journal_number,
                'journal_date' => $line->journalEntry->journal_date->format('Y-m-d'),
                'source_type' => $line->journalEntry->source_type,
                'source_id' => $line->journalEntry->source_id,
                'posting_sequence' => $line->journalEntry->posting_sequence,
                'line_sequence' => $line->line_sequence,
                'description' => $line->description ?? $line->journalEntry->description,
                'debit' => $debit,
                'credit' => $credit,
                'running_balance' => bcdiv((string) $running, '100', 2),
            ];
        });

        return [
            'opening_balance' => $openingBalance,
            'movement_debit' => $movementDebit,
            'movement_credit' => $movementCredit,
            'ending_balance' => bcadd($openingBalance, bcsub($movementDebit, $movementCredit, 2), 2),
            'rows' => $rows,
        ];
    }

    /** @param Collection<int, JournalLine> $lines */
    private function balance(Collection $lines): string
    {
        return bcsub($this->sum($lines->pluck('debit')), $this->sum($lines->pluck('credit')), 2);
    }

    /** @param Collection<int, string> $amounts */
    private function sum(Collection $amounts): string
    {
        return $amounts->reduce(fn (string $total, mixed $amount): string => bcadd($total, (string) $amount, 2), '0.00');
    }

    private function minor(string $amount): int
    {
        return (int) bcmul($amount, '100', 0);
    }
}
