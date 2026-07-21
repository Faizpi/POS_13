<?php

namespace App\Exports\Concerns;

use Maatwebsite\Excel\Concerns\RegistersEventListeners;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\DataType;

/**
 * Forces item-code columns to be stored as TYPE_STRING in the generated XLSX.
 *
 * Root cause: PhpSpreadsheet's DefaultValueBinder matches numeric-looking strings
 * (e.g. "8991980000000") via regex and binds them as TYPE_NUMERIC. The FORMAT_TEXT
 * column format and mso-number-format CSS only change the display format — they do
 * NOT change the stored cell type. Excel then renders the numeric value in scientific
 * notation (8.99198E+12).
 *
 * Fix: After the HTML reader has populated the sheet, walk every data row in the
 * item-code column(s) and re-bind the value with setCellValueExplicit(..., TYPE_STRING).
 * This preserves the exact string, prevents scientific notation, and preserves leading
 * zeros. Only the designated code columns are affected — monetary/quantity cells remain
 * numeric.
 */
trait ForcesItemCodeAsString
{
    use RegistersEventListeners;

    /**
     * Return the column letter(s) that contain item codes (e.g. ['B'] or ['O']).
     *
     * @return array<string>
     */
    abstract protected function itemCodeColumns(): array;

    /**
     * First data row (1-indexed). Rows before this are headers/summaries.
     */
    protected function itemCodeDataStartRow(): int
    {
        return 2;
    }

    /**
     * Last data row (1-indexed). Override to limit the range.
     * Return null to auto-detect from the sheet's highest row.
     */
    protected function itemCodeDataEndRow(): ?int
    {
        return null;
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event): void {
                $sheet = $event->sheet->getDelegate();
                $columns = $this->itemCodeColumns();

                if ($columns === []) {
                    return;
                }

                $startRow = $this->itemCodeDataStartRow();
                $endRow = $this->itemCodeDataEndRow() ?? $sheet->getHighestDataRow();

                foreach ($columns as $col) {
                    for ($row = $startRow; $row <= $endRow; $row++) {
                        $coordinate = $col.$row;
                        $cell = $sheet->getCell($coordinate);
                        $value = $cell->getValue();

                        // Only re-bind non-empty values that aren't already strings
                        if ($value !== null && $value !== '' && $cell->getDataType() !== DataType::TYPE_STRING) {
                            $sheet->setCellValueExplicit(
                                $coordinate,
                                (string) $value,
                                DataType::TYPE_STRING
                            );
                        }
                    }
                }
            },
        ];
    }
}
