<?php

namespace Tests\Feature;

use App\Exports\StokExport;
use App\Exports\TransactionsExport;
use App\Models\Gudang;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use Tests\TestCase;

class ExcelColumnFormatTest extends TestCase
{
    /**
     * TransactionsExport::columnFormats() depends only on exportType — no DB needed.
     */
    public function test_transactions_export_penjualan_phone_and_item_code_columns(): void
    {
        $export = new TransactionsExport(collect(), 'penjualan');
        $formats = $export->columnFormats();

        // Phone column E
        $this->assertArrayHasKey('E', $formats);
        $this->assertSame(NumberFormat::FORMAT_TEXT, $formats['E']);

        // Item code column O
        $this->assertArrayHasKey('O', $formats);
        $this->assertSame(NumberFormat::FORMAT_TEXT, $formats['O']);

        // Should not have other item code columns
        $this->assertArrayNotHasKey('N', $formats);
        $this->assertArrayNotHasKey('P', $formats);
        $this->assertArrayNotHasKey('M', $formats);
    }

    public function test_transactions_export_pembelian_item_code_column(): void
    {
        $export = new TransactionsExport(collect(), 'pembelian');
        $formats = $export->columnFormats();

        // Item code column P
        $this->assertArrayHasKey('P', $formats);
        $this->assertSame(NumberFormat::FORMAT_TEXT, $formats['P']);

        // Should not have phone column (pembelian has no phone)
        $this->assertArrayNotHasKey('E', $formats);
        $this->assertArrayNotHasKey('G', $formats);
        $this->assertArrayNotHasKey('I', $formats);

        // Should not have other item code columns
        $this->assertArrayNotHasKey('N', $formats);
        $this->assertArrayNotHasKey('O', $formats);
        $this->assertArrayNotHasKey('M', $formats);
    }

    public function test_transactions_export_kunjungan_phone_and_item_code_columns(): void
    {
        $export = new TransactionsExport(collect(), 'kunjungan');
        $formats = $export->columnFormats();

        // Phone columns F, H
        $this->assertArrayHasKey('F', $formats);
        $this->assertArrayHasKey('H', $formats);
        $this->assertSame(NumberFormat::FORMAT_TEXT, $formats['F']);
        $this->assertSame(NumberFormat::FORMAT_TEXT, $formats['H']);

        // Item code column M
        $this->assertArrayHasKey('M', $formats);
        $this->assertSame(NumberFormat::FORMAT_TEXT, $formats['M']);

        // Should not have other item code columns
        $this->assertArrayNotHasKey('N', $formats);
        $this->assertArrayNotHasKey('O', $formats);
        $this->assertArrayNotHasKey('P', $formats);
    }

    public function test_transactions_export_biaya_only_phone_column(): void
    {
        $export = new TransactionsExport(collect(), 'biaya');
        $formats = $export->columnFormats();

        // Phone column G only
        $this->assertArrayHasKey('G', $formats);
        $this->assertSame(NumberFormat::FORMAT_TEXT, $formats['G']);

        // Should NOT have any item code columns (biaya has no item code)
        $this->assertArrayNotHasKey('N', $formats);
        $this->assertArrayNotHasKey('O', $formats);
        $this->assertArrayNotHasKey('P', $formats);
        $this->assertArrayNotHasKey('M', $formats);

        // Should only have G
        $this->assertCount(1, $formats);
    }

    public function test_transactions_export_pembayaran_variants_only_phone_column(): void
    {
        foreach (['pembayaran', 'pembayaran_piutang', 'pembayaran_hutang'] as $type) {
            $export = new TransactionsExport(collect(), $type);
            $formats = $export->columnFormats();

            // Phone column G only
            $this->assertArrayHasKey('G', $formats, "Failed for type: {$type}");
            $this->assertSame(NumberFormat::FORMAT_TEXT, $formats['G']);

            // Should NOT have any item code columns (pembayaran has no item code)
            $this->assertArrayNotHasKey('N', $formats, "Type {$type} should not have N");
            $this->assertArrayNotHasKey('O', $formats, "Type {$type} should not have O");
            $this->assertArrayNotHasKey('P', $formats, "Type {$type} should not have P");
            $this->assertArrayNotHasKey('M', $formats, "Type {$type} should not have M");

            // Should only have G
            $this->assertCount(1, $formats, "Type {$type} should have exactly 1 column format");
        }
    }

    public function test_transactions_export_all_phone_and_item_code_columns(): void
    {
        $export = new TransactionsExport(collect(), 'all');
        $formats = $export->columnFormats();

        // Phone column I
        $this->assertArrayHasKey('I', $formats);
        $this->assertSame(NumberFormat::FORMAT_TEXT, $formats['I']);

        // Item code column N
        $this->assertArrayHasKey('N', $formats);
        $this->assertSame(NumberFormat::FORMAT_TEXT, $formats['N']);

        // Should not have other item code columns
        $this->assertArrayNotHasKey('O', $formats);
        $this->assertArrayNotHasKey('P', $formats);
        $this->assertArrayNotHasKey('M', $formats);
    }

    public function test_stok_export_item_code_column(): void
    {
        $gudang = new Gudang;
        $gudang->nama_gudang = 'Test';

        $export = new StokExport($gudang, collect());
        $formats = $export->columnFormats();

        // Item code column B
        $this->assertArrayHasKey('B', $formats);
        $this->assertSame(NumberFormat::FORMAT_TEXT, $formats['B']);

        // Should only have B
        $this->assertCount(1, $formats);
    }
}
