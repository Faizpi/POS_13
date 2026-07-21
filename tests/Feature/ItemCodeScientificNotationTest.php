<?php

namespace Tests\Feature;

use App\Exports\StokExport;
use App\Exports\TransactionsExport;
use App\Models\Gudang;
use App\Models\Produk;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx;
use Tests\TestCase;

class ItemCodeScientificNotationTest extends TestCase
{
    private const LONG_ITEM_CODE = '8991980000000';

    /**
     * Regression: StokExport must store item_code as TYPE_STRING in the XLSX,
     * not TYPE_NUMERIC, to prevent scientific notation display (8.99198E+12).
     */
    public function test_stok_export_preserves_item_code_as_string(): void
    {
        $gudang = new Gudang([
            'nama_gudang' => 'Test Gudang',
            'alamat_gudang' => 'Test Address',
        ]);

        $produk = new Produk([
            'nama_produk' => 'Test Product',
            'item_code' => self::LONG_ITEM_CODE,
            'harga' => 10000,
            'satuan' => 'Pcs',
        ]);

        $stokData = collect([
            (object) [
                'produk' => $produk,
                'stok_penjualan' => 50,
                'stok_gratis' => 5,
                'stok_sample' => 2,
            ],
        ]);

        $export = new StokExport($gudang, $stokData, 'Test');
        $filename = 'stok_test_'.uniqid().'.xlsx';
        Excel::store($export, $filename, 'local');
        $tempFile = storage_path('app/private/'.$filename);

        try {
            $this->assertFileExists($tempFile);

            $reader = new Xlsx;
            $spreadsheet = $reader->load($tempFile);
            $sheet = $spreadsheet->getActiveSheet();

            // Row 1 = header, Row 2 = first data row. Column B = item_code.
            $cell = $sheet->getCell('B2');
            $value = $cell->getValue();
            $datatype = $cell->getDataType();

            $this->assertSame(self::LONG_ITEM_CODE, (string) $value,
                "Item code must be stored as exact string, got: {$value}");
            $this->assertSame(DataType::TYPE_STRING, $datatype,
                "Item code must be TYPE_STRING, got: {$datatype}");
        } finally {
            @unlink($tempFile);
        }
    }

    /**
     * Regression: TransactionsExport (penjualan) must store item_code as TYPE_STRING.
     */
    public function test_transactions_export_penjualan_preserves_item_code_as_string(): void
    {
        $transactions = $this->buildPenjualanTransactions();
        $export = new TransactionsExport($transactions, 'penjualan', 'Test');

        $filename = 'penjualan_test_'.uniqid().'.xlsx';
        Excel::store($export, $filename, 'local');
        $tempFile = storage_path('app/private/'.$filename);

        try {
            $this->assertFileExists($tempFile);

            $reader = new Xlsx;
            $spreadsheet = $reader->load($tempFile);
            $sheet = $spreadsheet->getActiveSheet();

            // Row 1 = header, Row 2 = first data row. Column O = item_code for penjualan.
            $cell = $sheet->getCell('O2');
            $value = $cell->getValue();
            $datatype = $cell->getDataType();

            $this->assertSame(self::LONG_ITEM_CODE, (string) $value,
                "Item code must be stored as exact string, got: {$value}");
            $this->assertSame(DataType::TYPE_STRING, $datatype,
                "Item code must be TYPE_STRING, got: {$datatype}");
        } finally {
            @unlink($tempFile);
        }
    }

    /**
     * Regression: TransactionsExport (pembelian) must store item_code as TYPE_STRING.
     */
    public function test_transactions_export_pembelian_preserves_item_code_as_string(): void
    {
        $transactions = $this->buildPembelianTransactions();
        $export = new TransactionsExport($transactions, 'pembelian', 'Test');

        $filename = 'pembelian_test_'.uniqid().'.xlsx';
        Excel::store($export, $filename, 'local');
        $tempFile = storage_path('app/private/'.$filename);

        try {
            $this->assertFileExists($tempFile);

            $reader = new Xlsx;
            $spreadsheet = $reader->load($tempFile);
            $sheet = $spreadsheet->getActiveSheet();

            // Row 1 = header, Row 2 = first data row. Column P = item_code for pembelian.
            $cell = $sheet->getCell('P2');
            $value = $cell->getValue();
            $datatype = $cell->getDataType();

            $this->assertSame(self::LONG_ITEM_CODE, (string) $value,
                "Item code must be stored as exact string, got: {$value}");
            $this->assertSame(DataType::TYPE_STRING, $datatype,
                "Item code must be TYPE_STRING, got: {$datatype}");
        } finally {
            @unlink($tempFile);
        }
    }

    /**
     * Regression: TransactionsExport (kunjungan) must store item_code as TYPE_STRING.
     */
    public function test_transactions_export_kunjungan_preserves_item_code_as_string(): void
    {
        $transactions = $this->buildKunjunganTransactions();
        $export = new TransactionsExport($transactions, 'kunjungan', 'Test');

        $filename = 'kunjungan_test_'.uniqid().'.xlsx';
        Excel::store($export, $filename, 'local');
        $tempFile = storage_path('app/private/'.$filename);

        try {
            $this->assertFileExists($tempFile);

            $reader = new Xlsx;
            $spreadsheet = $reader->load($tempFile);
            $sheet = $spreadsheet->getActiveSheet();

            // Row 1 = header, Row 2 = first data row. Column M = item_code for kunjungan.
            $cell = $sheet->getCell('M2');
            $value = $cell->getValue();
            $datatype = $cell->getDataType();

            $this->assertSame(self::LONG_ITEM_CODE, (string) $value,
                "Item code must be stored as exact string, got: {$value}");
            $this->assertSame(DataType::TYPE_STRING, $datatype,
                "Item code must be TYPE_STRING, got: {$datatype}");
        } finally {
            @unlink($tempFile);
        }
    }

    /**
     * Build mock penjualan transactions matching the blade template's expected shape.
     */
    private function buildPenjualanTransactions(): Collection
    {
        $produk = new Produk([
            'nama_produk' => 'Test Product',
            'item_code' => self::LONG_ITEM_CODE,
            'harga' => 10000,
            'satuan' => 'Pcs',
        ]);

        $item = (object) [
            'produk' => $produk,
            'kuantitas' => 2,
            'harga_satuan' => 10000,
            'jumlah_baris' => 20000,
            'expired_date' => null,
        ];

        $transaction = (object) [
            'nomor' => 'INV-TEST-001',
            'tgl_transaksi' => now()->toDateString(),
            'pelanggan' => 'Test Customer',
            'no_telepon' => '081234567890',
            'no_telp_kontak' => '081234567890',
            'status' => 'Completed',
            'grand_total' => 20000,
            'items' => collect([$item]),
        ];

        // Add relationLoaded method via anonymous class
        $transaction = new class($transaction)
        {
            public function __construct(private object $inner) {}

            public function __get($name)
            {
                return $this->inner->$name ?? null;
            }

            public function __isset($name)
            {
                return isset($this->inner->$name);
            }

            public function relationLoaded($name)
            {
                return $name === 'items';
            }
        };

        return collect([$transaction]);
    }

    private function buildPembelianTransactions(): Collection
    {
        $produk = new Produk([
            'nama_produk' => 'Test Product',
            'item_code' => self::LONG_ITEM_CODE,
            'harga' => 5000,
            'satuan' => 'Pcs',
        ]);

        $item = (object) [
            'produk' => $produk,
            'kuantitas' => 1,
            'harga_satuan' => 5000,
            'jumlah_baris' => 5000,
        ];

        $transaction = (object) [
            'nomor' => 'PO-TEST-001',
            'tgl_transaksi' => now()->toDateString(),
            'status' => 'Completed',
            'grand_total' => 5000,
            'items' => collect([$item]),
        ];

        $transaction = new class($transaction)
        {
            public function __construct(private object $inner) {}

            public function __get($name)
            {
                return $this->inner->$name ?? null;
            }

            public function __isset($name)
            {
                return isset($this->inner->$name);
            }

            public function relationLoaded($name)
            {
                return $name === 'items';
            }
        };

        return collect([$transaction]);
    }

    private function buildKunjunganTransactions(): Collection
    {
        $produk = new Produk([
            'nama_produk' => 'Test Product',
            'item_code' => self::LONG_ITEM_CODE,
            'harga' => 10000,
            'satuan' => 'Pcs',
        ]);

        $item = (object) [
            'produk' => $produk,
            'jumlah' => 3,
            'expired_date' => null,
        ];

        $transaction = (object) [
            'nomor' => 'VIS-TEST-001',
            'tgl_kunjungan' => now()->toDateString(),
            'tujuan' => 'Test Tujuan',
            'sales_nama' => 'Test Sales',
            'sales_no_telepon' => '081234567890',
            'display_contact_name' => 'Test Contact',
            'status' => 'Completed',
            'items' => collect([$item]),
        ];

        $transaction = new class($transaction)
        {
            public function __construct(private object $inner) {}

            public function __get($name)
            {
                return $this->inner->$name ?? null;
            }

            public function __isset($name)
            {
                return isset($this->inner->$name);
            }

            public function relationLoaded($name)
            {
                return $name === 'items';
            }
        };

        return collect([$transaction]);
    }
}
