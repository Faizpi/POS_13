<?php

declare(strict_types=1);

namespace App\Services\Accounting;

use App\Models\Account;
use App\Models\JournalEntry;
use App\Models\JournalLine;
use App\Models\Pembelian;
use App\Models\Penjualan;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

final class AccountingReportService
{
    /**
     * @param  array{date_from?: string|null, date_to?: string|null, account_id?: int|null, source?: string|null, gudang_id?: int|null}  $filters
     * @return array{rows: Collection<int, array<string, mixed>>, groups: Collection<string, array<string, mixed>>, total_debit: string, total_credit: string, is_management_view: bool, warehouse_treatment: string}
     */
    public function journal(array $filters = []): array
    {
        $entries = $this->postedEntries($filters)
            ->with(['lines.account', 'gudang'])
            ->orderBy('journal_date')
            ->orderBy('posting_sequence')
            ->orderBy('id')
            ->get();

        $rows = $entries->map(function (JournalEntry $entry): array {
            return [
                'journal_id' => $entry->id,
                'journal_number' => $entry->journal_number,
                'journal_date' => $entry->journal_date->format('Y-m-d'),
                'source_type' => $entry->source_type,
                'source_id' => $entry->source_id,
                'journal_type' => $entry->journal_type->value,
                'posting_sequence' => $entry->posting_sequence,
                'description' => $entry->description,
                'gudang_id' => $entry->gudang_id,
                'gudang_name' => $entry->gudang?->nama_gudang,
                'total_debit' => (string) $entry->total_debit,
                'total_credit' => (string) $entry->total_credit,
                'lines' => $entry->lines->sortBy('line_sequence')->map(fn (JournalLine $line): array => [
                    'line_sequence' => $line->line_sequence,
                    'account_id' => $line->account_id,
                    'account_code' => $line->account?->code,
                    'account_name' => $line->account?->name,
                    'gudang_id' => $line->gudang_id,
                    'debit' => (string) $line->debit,
                    'credit' => (string) $line->credit,
                    'description' => $line->description,
                ])->values(),
            ];
        })->values();

        $groups = $rows->groupBy('source_type')->map(function (Collection $group, string $source): array {
            return [
                'source_type' => $source,
                'rows' => $group->values(),
                'total_debit' => $this->sum($group->pluck('total_debit')),
                'total_credit' => $this->sum($group->pluck('total_credit')),
            ];
        });

        return [
            'rows' => $rows,
            'groups' => $groups,
            'total_debit' => $this->sum($rows->pluck('total_debit')),
            'total_credit' => $this->sum($rows->pluck('total_credit')),
            'is_management_view' => isset($filters['gudang_id']) && $filters['gudang_id'] !== null,
            'warehouse_treatment' => 'Matching warehouse journals and global/null journal lines are included.',
        ];
    }

    /**
     * @param  array{date_from?: string|null, date_to?: string|null, source?: string|null, gudang_id?: int|null}  $filters
     * @return array{account_id: int, opening_balance: string, movement_debit: string, movement_credit: string, ending_balance: string, rows: Collection<int, array<string, mixed>>, is_management_view: bool, warehouse_treatment: string}
     */
    public function generalLedger(int $accountId, array $filters = []): array
    {
        $from = $filters['date_from'] ?? null;
        $entryFilters = $filters;
        unset($entryFilters['date_from'], $entryFilters['date_to']);
        $base = JournalLine::query()
            ->where('account_id', $accountId)
            ->whereHas('journalEntry', fn (Builder $query): Builder => $this->applyEntryFilters($query, $entryFilters));
        $this->applyWarehouseLineFilter($base, $filters['gudang_id'] ?? null);

        $openingQuery = clone $base;
        if ($from !== null) {
            $openingQuery->whereHas('journalEntry', fn (Builder $query): Builder => $query->whereDate('journal_date', '<', $from));
        } else {
            $openingQuery->whereRaw('1 = 0');
        }
        $opening = $this->balance($openingQuery->get());

        $movementQuery = clone $base;
        if ($from !== null) {
            $movementQuery->whereHas('journalEntry', fn (Builder $query): Builder => $query->whereDate('journal_date', '>=', $from));
        }
        if (($filters['date_to'] ?? null) !== null) {
            $to = $filters['date_to'];
            $movementQuery->whereHas('journalEntry', fn (Builder $query): Builder => $query->whereDate('journal_date', '<=', $to));
        }

        $lines = $movementQuery->with(['journalEntry.gudang'])->get()->sortBy(fn (JournalLine $line): string => sprintf('%s-%020d-%010d-%020d', $line->journalEntry->journal_date->format('Y-m-d'), $line->journalEntry->posting_sequence, $line->line_sequence, $line->id))->values();
        $running = $this->minor($opening);
        $movementDebit = '0.00';
        $movementCredit = '0.00';
        $rows = $lines->map(function (JournalLine $line) use (&$running, &$movementDebit, &$movementCredit): array {
            $debit = (string) $line->debit;
            $credit = (string) $line->credit;
            $movementDebit = bcadd($movementDebit, $debit, 2);
            $movementCredit = bcadd($movementCredit, $credit, 2);
            $running += $this->minor($debit) - $this->minor($credit);

            return [
                'journal_id' => $line->journalEntry->id,
                'journal_number' => $line->journalEntry->journal_number,
                'journal_date' => $line->journalEntry->journal_date->format('Y-m-d'),
                'posting_sequence' => $line->journalEntry->posting_sequence,
                'line_sequence' => $line->line_sequence,
                'source_type' => $line->journalEntry->source_type,
                'source_id' => $line->journalEntry->source_id,
                'description' => $line->description ?? $line->journalEntry->description,
                'debit' => $debit,
                'credit' => $credit,
                'running_balance' => bcdiv((string) $running, '100', 2),
            ];
        });

        return [
            'account_id' => $accountId,
            'opening_balance' => $opening,
            'movement_debit' => $movementDebit,
            'movement_credit' => $movementCredit,
            'ending_balance' => bcadd($opening, bcsub($movementDebit, $movementCredit, 2), 2),
            'rows' => $rows,
            'is_management_view' => isset($filters['gudang_id']) && $filters['gudang_id'] !== null,
            'warehouse_treatment' => 'Matching warehouse journal lines and global/null journal lines are included.',
        ];
    }

    /**
     * @param  array{date_from?: string|null, date_to?: string|null}  $filters
     * @return array{rows: Collection<int, array<string, mixed>>, totals: array<string, string>}
     */
    public function trialBalance(array $filters = []): array
    {
        $from = $filters['date_from'] ?? null;
        $to = $filters['date_to'] ?? null;

        // Opening: all posted lines before $from
        $openingRows = $this->trialBalanceAggregate(null, $from);

        // Movement: all posted lines between $from and $to
        $movementRows = $this->trialBalanceAggregate($from, $to);

        // Ending: all posted lines up to $to (opening + movement)
        $endingRows = $this->trialBalanceAggregate(null, $to ?: null);

        $accounts = Account::query()
            ->whereHas('journalLines', fn (Builder $q): Builder => $q
                ->whereHas('journalEntry', fn (Builder $je): Builder => $je->where('status', 'posted'))
                ->when($to !== null, fn (Builder $q2): Builder => $q2
                    ->whereHas('journalEntry', fn (Builder $je2): Builder => $je2->whereDate('journal_date', '<=', $to))
                )
            )
            ->orderBy('code')
            ->get();

        $rows = $accounts->map(function (Account $account) use ($openingRows, $movementRows, $endingRows): array {
            $id = (string) $account->id;
            $openingDebit = $openingRows[$id]['debit'] ?? '0.00';
            $openingCredit = $openingRows[$id]['credit'] ?? '0.00';
            $movementDebit = $movementRows[$id]['debit'] ?? '0.00';
            $movementCredit = $movementRows[$id]['credit'] ?? '0.00';
            $ending = bcadd(bcsub($openingDebit, $openingCredit, 2), bcsub($movementDebit, $movementCredit, 2), 2);

            return [
                'account_id' => $account->id,
                'account_code' => $account->code,
                'account_name' => $account->name,
                'opening_debit' => bccomp($openingDebit, $openingCredit, 2) >= 0 ? bcsub($openingDebit, $openingCredit, 2) : '0.00',
                'opening_credit' => bccomp($openingCredit, $openingDebit, 2) > 0 ? bcsub($openingCredit, $openingDebit, 2) : '0.00',
                'movement_debit' => $movementDebit,
                'movement_credit' => $movementCredit,
                'ending_debit' => bccomp($ending, '0.00', 2) >= 0 ? $ending : '0.00',
                'ending_credit' => bccomp($ending, '0.00', 2) < 0 ? bcsub('0.00', $ending, 2) : '0.00',
            ];
        })->values();

        return [
            'rows' => $rows,
            'totals' => [
                'opening_debit' => $this->sum($rows->pluck('opening_debit')),
                'opening_credit' => $this->sum($rows->pluck('opening_credit')),
                'movement_debit' => $this->sum($rows->pluck('movement_debit')),
                'movement_credit' => $this->sum($rows->pluck('movement_credit')),
                'ending_debit' => $this->sum($rows->pluck('ending_debit')),
                'ending_credit' => $this->sum($rows->pluck('ending_credit')),
            ],
        ];
    }

    /**
     * @return array{receivables: array<string, mixed>, payables: array<string, mixed>}
     */
    public function receivablePayableAging(string $asOf): array
    {
        $receivableLedger = app(PiutangLedgerService::class);
        $payableLedger = app(HutangLedgerService::class);

        $receivables = Penjualan::query()->whereNotNull('tgl_jatuh_tempo')->get()->map(function (Penjualan $sale) use ($receivableLedger, $asOf): ?array {
            $balance = $receivableLedger->outstandingForSale($sale);
            if (bccomp($balance, '0.00', 2) <= 0) {
                return null;
            }

            return ['amount' => $balance, 'bucket' => $receivableLedger->agingBucketForSale($sale, new \DateTimeImmutable($asOf))];
        })->filter()->values();
        $payables = Pembelian::query()->whereNotNull('tgl_jatuh_tempo')->get()->map(function (Pembelian $purchase) use ($payableLedger, $asOf): ?array {
            $balance = $payableLedger->outstandingForPurchase($purchase);
            if (bccomp($balance, '0.00', 2) <= 0) {
                return null;
            }

            return ['amount' => $balance, 'bucket' => $payableLedger->agingBucketForPurchase($purchase, new \DateTimeImmutable($asOf))];
        })->filter()->values();

        return [
            'receivables' => $this->agingSummary($receivables),
            'payables' => $this->agingSummary($payables),
        ];
    }

    /** @param Collection<int, array{amount: string, bucket: string}> $rows */
    private function agingSummary(Collection $rows): array
    {
        $buckets = collect(['current', '1-30', '31-60', '61-90', '90+'])
            ->mapWithKeys(fn (string $bucket): array => [$bucket => $this->sum($rows->where('bucket', $bucket)->pluck('amount'))])
            ->all();

        return ['rows' => $rows, 'buckets' => $buckets, 'total' => $this->sum($rows->pluck('amount'))];
    }

    /** @param array<string, mixed> $filters */
    private function postedEntries(array $filters): Builder
    {
        $query = JournalEntry::query();
        $this->applyEntryFilters($query, $filters);

        if (($filters['account_id'] ?? null) !== null) {
            $query->whereHas('lines', fn (Builder $lines): Builder => $lines->where('account_id', $filters['account_id']));
        }

        return $query;
    }

    /** @param array<string, mixed> $filters */
    private function applyEntryFilters(Builder $query, array $filters): Builder
    {
        $query->where('status', 'posted');
        if (($filters['date_from'] ?? null) !== null) {
            $query->whereDate('journal_date', '>=', $filters['date_from']);
        }
        if (($filters['date_to'] ?? null) !== null) {
            $query->whereDate('journal_date', '<=', $filters['date_to']);
        }
        if (($filters['source'] ?? null) !== null && $filters['source'] !== '') {
            $query->where('source_type', $filters['source']);
        }
        if (($filters['gudang_id'] ?? null) !== null) {
            $gudangId = (int) $filters['gudang_id'];
            $query->where(fn (Builder $warehouse): Builder => $warehouse
                ->where('gudang_id', $gudangId)
                ->orWhereNull('gudang_id'));
        }

        return $query;
    }

    private function applyWarehouseLineFilter(Builder $query, ?int $gudangId): void
    {
        if ($gudangId !== null) {
            $query->where(fn (Builder $warehouse): Builder => $warehouse->where('gudang_id', $gudangId)->orWhereNull('gudang_id'));
        }
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

    /**
     * Aggregate debit/credit per account_id from posted journal lines,
     * optionally filtered by date range at the SQL level.
     *
     * @return array<string, array{debit: string, credit: string}>
     */
    private function trialBalanceAggregate(?string $from, ?string $to): array
    {
        $query = JournalLine::query()
            ->selectRaw('account_id, SUM(debit) as total_debit, SUM(credit) as total_credit')
            ->whereHas('journalEntry', function (Builder $q) use ($from, $to): void {
                $q->where('status', 'posted');
                if ($from !== null) {
                    $q->whereDate('journal_date', '>=', $from);
                }
                if ($to !== null) {
                    $q->whereDate('journal_date', '<=', $to);
                }
            })
            ->groupBy('account_id');

        $results = [];
        foreach ($query->get() as $row) {
            $results[(string) $row->account_id] = [
                'debit' => (string) $row->total_debit,
                'credit' => (string) $row->total_credit,
            ];
        }

        return $results;
    }
}
