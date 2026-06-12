<?php

namespace Tests\Feature\Api;

use App\Models\Gudang;
use App\Models\Pembayaran;
use App\Models\Penjualan;
use App\Models\PenjualanItem;
use App\Models\Produk;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExportAndPrintParityTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    public function test_gudang_stok_export_downloads_xlsx(): void
    {
        $token = $this->login('admin@hibiscusefsya.com');
        $gudang = Gudang::where('nama_gudang', 'Gudang A')->firstOrFail();

        $response = $this->withHeader('Authorization', "Bearer $token")
            ->get("/api/v1/gudang/stok/export?gudang_id={$gudang->id}");

        $response->assertOk();
        $this->assertStringContainsString('.xlsx', $response->headers->get('content-disposition'));
        $this->assertStringContainsString('Stok_Gudang_A_', $response->headers->get('content-disposition'));
    }

    public function test_dashboard_daily_report_pdf_downloads_pdf(): void
    {
        $token = $this->login('salesa@hibiscusefsya.com');
        $this->makePenjualan(['tgl_transaksi' => '2026-06-05']);

        $response = $this->withHeader('Authorization', "Bearer $token")
            ->get('/api/v1/dashboard/daily-report/pdf?date=2026-06-05');

        $response->assertOk()
            ->assertHeader('content-type', 'application/pdf');

        $this->assertStringStartsWith('%PDF', $response->getContent());
    }

    public function test_dashboard_export_all_pdf_downloads_pdf(): void
    {
        $token = $this->login('superadmin@hibiscusefsya.com');
        $this->makePenjualan(['tgl_transaksi' => '2026-06-05']);

        $response = $this->withHeader('Authorization', "Bearer $token")
            ->post('/api/v1/dashboard/export', [
                'date_from' => '2026-06-01',
                'date_to' => '2026-06-30',
                'transaction_type' => 'all',
                'export_format' => 'pdf',
            ]);

        $response->assertOk()
            ->assertHeader('content-type', 'application/pdf');

        $this->assertStringStartsWith('%PDF', $response->getContent());
    }

    public function test_pembayaran_harian_pdf_downloads_pdf(): void
    {
        $token = $this->login('admin@hibiscusefsya.com');
        $penjualan = $this->makePenjualan([
            'status' => 'Approved',
            'syarat_pembayaran' => 'Cash',
            'tgl_transaksi' => '2026-06-05',
            'tgl_jatuh_tempo' => '2026-06-05',
            'grand_total' => 100000,
        ]);

        Pembayaran::create([
            'user_id' => $penjualan->user_id,
            'gudang_id' => $penjualan->gudang_id,
            'penjualan_id' => $penjualan->id,
            'nomor' => 'PAY-PARTIAL-001',
            'tgl_pembayaran' => '2026-06-05',
            'metode_pembayaran' => 'Transfer',
            'jumlah_bayar' => 25000,
            'status' => 'Approved',
            'lampiran_paths' => [],
        ]);

        $response = $this->withHeader('Authorization', "Bearer $token")
            ->get('/api/v1/pembayaran/export-harian-pdf?tanggal_mulai=2026-06-05&tanggal_selesai=2026-06-05');

        $response->assertOk()
            ->assertHeader('content-type', 'application/pdf');

        $this->assertStringStartsWith('%PDF', $response->getContent());
    }

    public function test_bluetooth_print_api_returns_transaction_payload(): void
    {
        $token = $this->login('salesa@hibiscusefsya.com');
        $penjualan = $this->makePenjualan();

        $response = $this->getJson("/api/v1/print/penjualan/{$penjualan->id}/bluetooth", [
            'Authorization' => "Bearer $token",
        ]);

        $response->assertOk()
            ->assertJsonPath('nomor', 'INV-API-001')
            ->assertJsonPath('pelanggan', 'Toko Melati')
            ->assertJsonStructure(['items', 'subtotal', 'grand_total', 'invoice_url']);

        $this->assertNotEquals('Bluetooth data', $response->json('message'));
        $this->assertCount(1, $response->json('items'));
    }

    private function makePenjualan(array $overrides = []): Penjualan
    {
        $user = User::where('email', 'salesa@hibiscusefsya.com')->firstOrFail();
        $produk = Produk::firstOrFail();

        $penjualan = Penjualan::create(array_merge([
            'user_id' => $user->id,
            'gudang_id' => $user->gudang_id,
            'nomor' => 'INV-API-001',
            'tipe_harga' => 'retail',
            'pelanggan' => 'Toko Melati',
            'no_telepon' => '081111111111',
            'tgl_transaksi' => '2026-06-05',
            'tgl_jatuh_tempo' => '2026-06-12',
            'syarat_pembayaran' => 'Cash',
            'status' => 'Approved',
            'diskon_akhir' => 0,
            'tax_percentage' => 0,
            'grand_total' => 25000,
            'lampiran_paths' => [],
        ], $overrides));

        PenjualanItem::create([
            'penjualan_id' => $penjualan->id,
            'produk_id' => $produk->id,
            'kuantitas' => 1,
            'unit' => 'Pcs',
            'harga_satuan' => 25000,
            'diskon' => 0,
            'diskon_nominal' => 0,
            'jumlah_baris' => 25000,
        ]);

        return $penjualan->refresh();
    }

    private function login(string $email): string
    {
        return $this->postJson('/api/v1/login', [
            'email' => $email,
            'password' => 'password123',
        ])->json('token');
    }
}
