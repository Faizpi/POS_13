<?php

declare(strict_types=1);

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

final readonly class AccountingReportExport implements FromCollection, WithHeadings, WithTitle
{
    /** @param array<string, mixed> $report */
    public function __construct(
        private string $title,
        private array $report,
    ) {}

    /** @return Collection<int, array<string, mixed>> */
    public function rows(): Collection
    {
        return $this->report['rows'];
    }

    /** @return Collection<int, array<int, string>> */
    public function collection(): Collection
    {
        return $this->rows()->map(fn (array $row): array => [
            $row['journal_date'],
            $row['journal_number'],
            $row['source_type'],
            (string) $row['source_id'],
            (string) ($row['debit'] ?? $row['total_debit']),
            (string) ($row['credit'] ?? $row['total_credit']),
            (string) ($row['running_balance'] ?? ''),
        ]);
    }

    /** @return list<string> */
    public function headings(): array
    {
        return ['Tanggal', 'Nomor Jurnal', 'Sumber', 'ID Sumber', 'Debit', 'Kredit', 'Saldo Berjalan'];
    }

    public function title(): string
    {
        return $this->title;
    }

    /** @return array<string, mixed> */
    public function metadata(): array
    {
        return [
            'title' => $this->title,
            'total_debit' => $this->report['total_debit'] ?? $this->report['movement_debit'] ?? '0.00',
            'total_credit' => $this->report['total_credit'] ?? $this->report['movement_credit'] ?? '0.00',
            'is_management_view' => $this->report['is_management_view'],
            'warehouse_treatment' => $this->report['warehouse_treatment'],
        ];
    }
}
