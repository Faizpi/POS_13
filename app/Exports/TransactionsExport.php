<?php

namespace App\Exports;

use App\Exports\Concerns\ForcesItemCodeAsString;
use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class TransactionsExport implements FromView, ShouldAutoSize, WithColumnFormatting, WithEvents, WithStyles, WithTitle
{
    use ForcesItemCodeAsString;

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
            'pembayaran_piutang' => 'reports.pembayaran_piutang',
            'pembayaran_hutang' => 'reports.pembayaran_hutang',
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
            'pembayaran_piutang' => 'Laporan Pembayaran Piutang',
            'pembayaran_hutang' => 'Laporan Pembayaran Hutang',
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
            'pembayaran', 'pembayaran_piutang', 'pembayaran_hutang' => ['G'],
            'pembelian' => [],
            default => ['I'],
        };

        // Item code columns - set as TEXT to prevent scientific notation (e.g., 8.99E+12).
        $itemCodeColumns = match ($this->exportType) {
            'penjualan' => ['O'],
            'pembelian' => ['P'],
            'kunjungan' => ['M'],
            'all' => ['N'],
            default => [],
        };

        $formats = array_fill_keys($phoneColumns, NumberFormat::FORMAT_TEXT);
        $formats = array_merge($formats, array_fill_keys($itemCodeColumns, NumberFormat::FORMAT_TEXT));

        return $formats;
    }

    protected function itemCodeColumns(): array
    {
        return match ($this->exportType) {
            'penjualan' => ['O'],
            'pembelian' => ['P'],
            'kunjungan' => ['M'],
            'all' => ['N'],
            default => [],
        };
    }
}
