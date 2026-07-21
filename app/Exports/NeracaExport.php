<?php

namespace App\Exports;

use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class NeracaExport implements FromView, WithEvents, WithStyles, WithTitle
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
        return 'Neraca Keuangan';
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            // Title block
            1 => ['font' => ['bold' => true, 'size' => 14, 'color' => ['rgb' => '2D6A4F']]],
            2 => ['font' => ['size' => 10]],
            3 => ['font' => ['size' => 10]],
            4 => ['font' => ['size' => 9, 'color' => ['rgb' => '666666']]],
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event): void {
                $sheet = $event->sheet->getDelegate();
                $highestRow = $sheet->getHighestRow();

                // Number format for currency column (B)
                $currencyFormat = '#,##0';

                // Apply number format to all numeric cells in column B (rows 6+)
                for ($row = 6; $row <= $highestRow; $row++) {
                    $cell = $sheet->getCell("B{$row}");
                    $value = $cell->getValue();

                    // Only format cells that contain numeric values
                    if (is_numeric($value)) {
                        $sheet->getStyle("B{$row}")->getNumberFormat()->setFormatCode($currencyFormat);
                    }
                }

                // Style section headers (green background rows)
                for ($row = 6; $row <= $highestRow; $row++) {
                    $cellA = $sheet->getCell("A{$row}");
                    $fill = $cellA->getStyle()->getFill();

                    // Check if this is a section header (has green background from Blade)
                    if ($fill->getStartColor()->getRGB() === 'E2EFDA') {
                        $sheet->getStyle("A{$row}:B{$row}")->applyFromArray([
                            'font' => ['bold' => true, 'size' => 10, 'color' => ['rgb' => '2D6A4F']],
                            'fill' => [
                                'fillType' => 'solid',
                                'startColor' => ['rgb' => 'E2EFDA'],
                            ],
                            'borders' => [
                                'bottom' => ['style' => 'thin', 'color' => ['rgb' => 'C6E0B4']],
                            ],
                        ]);
                    }
                }

                // Style column header rows (gray background)
                for ($row = 6; $row <= $highestRow; $row++) {
                    $cellA = $sheet->getCell("A{$row}");
                    $fill = $cellA->getStyle()->getFill();

                    if ($fill->getStartColor()->getRGB() === 'F3F4F6') {
                        $sheet->getStyle("A{$row}:B{$row}")->applyFromArray([
                            'font' => ['bold' => true, 'size' => 9],
                            'fill' => [
                                'fillType' => 'solid',
                                'startColor' => ['rgb' => 'F3F4F6'],
                            ],
                            'borders' => [
                                'bottom' => ['style' => 'thin', 'color' => ['rgb' => 'D1D5DB']],
                            ],
                            'alignment' => [
                                'horizontal' => 'left',
                            ],
                        ]);
                        // Right-align column B headers
                        $sheet->getStyle("B{$row}")->getAlignment()->setHorizontal('right');
                    }
                }

                // Style total rows (bold, with top border)
                for ($row = 6; $row <= $highestRow; $row++) {
                    $cellA = $sheet->getCell("A{$row}");
                    $value = $cellA->getValue();

                    if ($value && str_starts_with(strtoupper((string) $value), 'TOTAL')) {
                        $sheet->getStyle("A{$row}:B{$row}")->applyFromArray([
                            'font' => ['bold' => true],
                            'borders' => [
                                'top' => ['style' => 'thin', 'color' => ['rgb' => '9CA3AF']],
                                'bottom' => ['style' => 'thin', 'color' => ['rgb' => '9CA3AF']],
                            ],
                        ]);

                        // Apply currency format to total in column B
                        $cellB = $sheet->getCell("B{$row}");
                        if (is_numeric($cellB->getValue())) {
                            $sheet->getStyle("B{$row}")->getNumberFormat()->setFormatCode($currencyFormat);
                        }
                    }
                }

                // Style danger row (Belum Lunas total)
                for ($row = 6; $row <= $highestRow; $row++) {
                    $cellA = $sheet->getCell("A{$row}");
                    $font = $cellA->getStyle()->getFont();

                    if ($font->getColor() && $font->getColor()->getRGB() === '991B1B') {
                        $sheet->getStyle("A{$row}:B{$row}")->applyFromArray([
                            'font' => ['bold' => true, 'color' => ['rgb' => '991B1B']],
                            'fill' => [
                                'fillType' => 'solid',
                                'startColor' => ['rgb' => 'FEE2E2'],
                            ],
                        ]);
                    }
                }

                // Set column widths
                $sheet->getColumnDimension('A')->setWidth(35);
                $sheet->getColumnDimension('B')->setWidth(20);

                // Freeze pane below header (row 5)
                $sheet->freezePane('A6');

                // Print setup
                $sheet->getPageSetup()->setOrientation('portrait');
                $sheet->getPageSetup()->setPaperSize(PageSetup::PAPERSIZE_A4);
                $sheet->getPageSetup()->setFitToPage(true);
                $sheet->getPageSetup()->setFitToWidth(1);
                $sheet->getPageSetup()->setFitToHeight(0);
                $sheet->getPageMargins()->setTop(0.5);
                $sheet->getPageMargins()->setBottom(0.5);
                $sheet->getPageMargins()->setLeft(0.5);
                $sheet->getPageMargins()->setRight(0.5);

                // Set print title rows (repeat header on each page)
                $sheet->getPageSetup()->setRowsToRepeatAtTop([1, 4]);
            },
        ];
    }
}
