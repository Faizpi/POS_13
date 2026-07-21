<?php

namespace Tests\Feature;

use App\Models\Kontak;
use App\Models\Pembayaran;
use App\Models\Pembelian;
use App\Models\Penjualan;
use App\Models\PenjualanItem;
use App\Models\Produk;
use App\Models\User;
use App\Services\ReportExportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PembayaranExportSplitTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    private function makeSuperAdmin(): User
    {
        return User::where('email', 'superadmin@hibiscusefsya.com')->firstOrFail();
    }

    private function makePenjualan(array $overrides = []): Penjualan
    {
        $user = User::where('email', 'salesa@hibiscusefsya.com')->firstOrFail();
        $produk = Produk::firstOrFail();

        $penjualan = Penjualan::create(array_merge([
            'user_id' => $user->id,
            'gudang_id' => $user->gudang_id,
            'pelanggan' => 'Pelanggan Test',
            'no_telepon' => '081234567890',
            'tgl_transaksi' => '2026-06-05',
            'tgl_jatuh_tempo' => '2026-06-12',
            'syarat_pembayaran' => 'Cash',
            'status' => 'Lunas',
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

    private function makePembelian(array $overrides = []): Pembelian
    {
        $user = User::where('email', 'admin@hibiscusefsya.com')->firstOrFail();
        $kontak = Kontak::firstOrCreate(
            ['nama' => 'Supplier Test'],
            ['no_telp' => '081999888777', 'gudang_id' => $user->gudang_id]
        );

        return Pembelian::create(array_merge([
            'user_id' => $user->id,
            'gudang_id' => $user->gudang_id,
            'kontak_id' => $kontak->id,
            'tgl_transaksi' => '2026-06-05',
            'tgl_jatuh_tempo' => '2026-06-12',
            'syarat_pembayaran' => 'Cash',
            'status' => 'Lunas',
            'diskon_akhir' => 0,
            'tax_percentage' => 0,
            'grand_total' => 50000,
            'lampiran_paths' => [],
        ], $overrides));
    }

    public function test_service_filters_piutang_only(): void
    {
        $penjualan = $this->makePenjualan();
        $pembelian = $this->makePembelian();

        Pembayaran::create([
            'type' => 'piutang',
            'penjualan_id' => $penjualan->id,
            'user_id' => $penjualan->user_id,
            'gudang_id' => $penjualan->gudang_id,
            'tgl_pembayaran' => '2026-06-06',
            'metode_pembayaran' => 'Transfer',
            'jumlah_bayar' => 25000,
            'status' => 'Approved',
        ]);

        Pembayaran::create([
            'type' => 'hutang',
            'pembelian_id' => $pembelian->id,
            'user_id' => $pembelian->user_id,
            'gudang_id' => $pembelian->gudang_id,
            'tgl_pembayaran' => '2026-06-06',
            'metode_pembayaran' => 'Transfer',
            'jumlah_bayar' => 50000,
            'status' => 'Approved',
        ]);

        $service = app(ReportExportService::class);
        $result = $service->buildExportData(
            $this->makeSuperAdmin(),
            'pembayaran_piutang',
            '2026-06-01',
            '2026-06-30',
        );

        $this->assertCount(1, $result);
        $this->assertSame('piutang', $result->first()->pembayaran_kind);
        $this->assertSame('Pelanggan Test', $result->first()->display_contact_name);
    }

    public function test_service_filters_hutang_only(): void
    {
        $penjualan = $this->makePenjualan();
        $pembelian = $this->makePembelian();

        Pembayaran::create([
            'type' => 'piutang',
            'penjualan_id' => $penjualan->id,
            'user_id' => $penjualan->user_id,
            'gudang_id' => $penjualan->gudang_id,
            'tgl_pembayaran' => '2026-06-06',
            'metode_pembayaran' => 'Transfer',
            'jumlah_bayar' => 25000,
            'status' => 'Approved',
        ]);

        Pembayaran::create([
            'type' => 'hutang',
            'pembelian_id' => $pembelian->id,
            'user_id' => $pembelian->user_id,
            'gudang_id' => $pembelian->gudang_id,
            'tgl_pembayaran' => '2026-06-06',
            'metode_pembayaran' => 'Transfer',
            'jumlah_bayar' => 50000,
            'status' => 'Approved',
        ]);

        $service = app(ReportExportService::class);
        $result = $service->buildExportData(
            $this->makeSuperAdmin(),
            'pembayaran_hutang',
            '2026-06-01',
            '2026-06-30',
        );

        $this->assertCount(1, $result);
        $this->assertSame('hutang', $result->first()->pembayaran_kind);
        $this->assertSame('Supplier Test', $result->first()->display_contact_name);
    }

    public function test_legacy_pembayaran_returns_both_types(): void
    {
        $penjualan = $this->makePenjualan();
        $pembelian = $this->makePembelian();

        Pembayaran::create([
            'type' => 'piutang',
            'penjualan_id' => $penjualan->id,
            'user_id' => $penjualan->user_id,
            'gudang_id' => $penjualan->gudang_id,
            'tgl_pembayaran' => '2026-06-06',
            'metode_pembayaran' => 'Transfer',
            'jumlah_bayar' => 25000,
            'status' => 'Approved',
        ]);

        Pembayaran::create([
            'type' => 'hutang',
            'pembelian_id' => $pembelian->id,
            'user_id' => $pembelian->user_id,
            'gudang_id' => $pembelian->gudang_id,
            'tgl_pembayaran' => '2026-06-06',
            'metode_pembayaran' => 'Transfer',
            'jumlah_bayar' => 50000,
            'status' => 'Approved',
        ]);

        $service = app(ReportExportService::class);
        $result = $service->buildExportData(
            $this->makeSuperAdmin(),
            'pembayaran',
            '2026-06-01',
            '2026-06-30',
        );

        $this->assertCount(2, $result);
    }

    public function test_api_export_piutang_excel(): void
    {
        $token = $this->postJson('/api/v1/login', [
            'email' => 'superadmin@hibiscusefsya.com',
            'password' => 'password123',
        ])->json('token');

        $penjualan = $this->makePenjualan();
        Pembayaran::create([
            'type' => 'piutang',
            'penjualan_id' => $penjualan->id,
            'user_id' => $penjualan->user_id,
            'gudang_id' => $penjualan->gudang_id,
            'tgl_pembayaran' => '2026-06-06',
            'metode_pembayaran' => 'Transfer',
            'jumlah_bayar' => 25000,
            'status' => 'Approved',
        ]);

        $response = $this->withHeader('Authorization', "Bearer $token")
            ->post('/api/v1/dashboard/export', [
                'date_from' => '2026-06-01',
                'date_to' => '2026-06-30',
                'transaction_type' => 'pembayaran_piutang',
                'export_format' => 'excel',
            ]);

        $response->assertOk();
        $disposition = $response->headers->get('content-disposition');
        $this->assertStringContainsString('Laporan_Pembayaran_Piutang_', $disposition);
        $this->assertStringContainsString('.xlsx', $disposition);
    }

    public function test_api_export_hutang_pdf(): void
    {
        $token = $this->postJson('/api/v1/login', [
            'email' => 'superadmin@hibiscusefsya.com',
            'password' => 'password123',
        ])->json('token');

        $pembelian = $this->makePembelian();
        Pembayaran::create([
            'type' => 'hutang',
            'pembelian_id' => $pembelian->id,
            'user_id' => $pembelian->user_id,
            'gudang_id' => $pembelian->gudang_id,
            'tgl_pembayaran' => '2026-06-06',
            'metode_pembayaran' => 'Transfer',
            'jumlah_bayar' => 50000,
            'status' => 'Approved',
        ]);

        $response = $this->withHeader('Authorization', "Bearer $token")
            ->post('/api/v1/dashboard/export', [
                'date_from' => '2026-06-01',
                'date_to' => '2026-06-30',
                'transaction_type' => 'pembayaran_hutang',
                'export_format' => 'pdf',
            ]);

        $response->assertOk()
            ->assertHeader('content-type', 'application/pdf');

        $disposition = $response->headers->get('content-disposition');
        $this->assertStringContainsString('Laporan_Pembayaran_Hutang_', $disposition);
        $this->assertStringStartsWith('%PDF', $response->getContent());
    }

    public function test_api_export_options_includes_new_types(): void
    {
        $token = $this->postJson('/api/v1/login', [
            'email' => 'superadmin@hibiscusefsya.com',
            'password' => 'password123',
        ])->json('token');

        $response = $this->withHeader('Authorization', "Bearer $token")
            ->get('/api/v1/dashboard/export-options');

        $response->assertOk();
        $types = collect($response->json('transaction_types'))->pluck('value')->toArray();
        $this->assertContains('pembayaran_piutang', $types);
        $this->assertContains('pembayaran_hutang', $types);
        $this->assertNotContains('pembayaran', $types);
    }
}
