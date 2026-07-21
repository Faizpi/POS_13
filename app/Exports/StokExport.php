<?php

namespace App\Exports;

use App\Exports\Concerns\ForcesItemCodeAsString;
use App\Models\Gudang;
use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class StokExport implements FromView, ShouldAutoSize, WithColumnFormatting, WithEvents, WithStyles, WithTitle
{
    use ForcesItemCodeAsString;

    protected Gudang $gudang;

    protected $stokData;

    protected ?string $generatedBy;

    public function __construct(Gudang $gudang, $stokData, ?string $generatedBy = null)
    {
        $this->gudang = $gudang;
        $this->stokData = $stokData;
        $this->generatedBy = $generatedBy ?? 'System';
    }

    public function view(): View
    {
        return view('reports.stok', [
            'gudang' => $this->gudang,
            'stokData' => $this->stokData,
            'generatedBy' => $this->generatedBy,
            'generatedAt' => now()->format('d/m/Y H:i:s'),
        ]);
    }

    public function title(): string
    {
        return 'Stok '.$this->gudang->nama_gudang;
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }

    public function columnFormats(): array
    {
        // Item Code column (B) - set as TEXT to prevent scientific notation.
        return ['B' => NumberFormat::FORMAT_TEXT];
    }

    protected function itemCodeColumns(): array
    {
        return ['B'];
    }
}
