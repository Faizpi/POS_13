<?php

namespace App\Exports;

use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class NeracaExport implements FromView, ShouldAutoSize, WithStyles, WithTitle
{
    protected array $data;

    protected ?string $from;

    protected ?string $to;

    protected ?string $gudang;

    public function __construct(array $data, ?string $from = null, ?string $to = null, ?string $gudang = null)
    {
        $this->data = $data;
        $this->from = $from;
        $this->to = $to;
        $this->gudang = $gudang ?? 'Semua Gudang';
    }

    public function view(): View
    {
        return view('reports.neraca-excel', [
            'data' => $this->data,
            'from' => $this->from,
            'to' => $this->to,
            'gudang' => $this->gudang,
            'generatedBy' => auth()->user()?->name ?? 'System',
            'generatedAt' => now()->format('d/m/Y H:i:s'),
        ]);
    }

    public function title(): string
    {
        return 'Neraca';
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true, 'size' => 14]],
            2 => ['font' => ['bold' => false, 'size' => 10]],
            3 => ['font' => ['bold' => false, 'size' => 10]],
            5 => ['font' => ['bold' => true], 'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => 'E2EFDA']]],
        ];
    }
}
