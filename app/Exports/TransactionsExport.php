<?php

namespace App\Exports;

use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class TransactionsExport implements FromView, ShouldAutoSize, WithColumnFormatting, WithStyles, WithTitle
{
    protected $transactions;

    protected string $exportType;

    protected ?string $generatedBy;

    public function __construct($transactions, string $exportType = 'all', ?string $generatedBy = null)
    {
        $this->transactions = $transactions;
        $this->exportType = $exportType;
        $this->generatedBy = $generatedBy ?? 'System';
    }

    public function view(): View
    {
        $viewName = match ($this->exportType) {
            'penjualan' => 'reports.penjualan',
            'pembelian' => 'reports.pembelian',
            'biaya' => 'reports.biaya',
            'kunjungan' => 'reports.kunjungan',
            'pembayaran' => 'reports.pembayaran',
            default => 'reports.transactions',
        };

        return view($viewName, [
            'transactions' => $this->transactions,
            'exportType' => $this->exportType,
            'generatedBy' => $this->generatedBy,
            'generatedAt' => now()->format('d/m/Y H:i:s'),
        ]);
    }

    public function title(): string
    {
        return match ($this->exportType) {
            'penjualan' => 'Laporan Penjualan',
            'pembelian' => 'Laporan Pembelian',
            'biaya' => 'Laporan Biaya',
            'kunjungan' => 'Laporan Kunjungan',
            'pembayaran' => 'Laporan Pembayaran',
            default => 'Semua Transaksi',
        };
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }

    public function columnFormats(): array
    {
        // Phone number columns - set as TEXT to prevent Excel from auto-converting.
        $phoneColumns = match ($this->exportType) {
            'penjualan' => ['E'],
            'kunjungan' => ['F', 'H'],
            'biaya' => ['G'],
            'pembayaran' => ['G'],
            default => ['I'],
        };

        return array_fill_keys($phoneColumns, NumberFormat::FORMAT_TEXT);
    }
}
