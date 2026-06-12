<?php

namespace Tests\Feature\Api;

use App\Models\Gudang;
use App\Models\GudangProduk;
use App\Models\Penjualan;
use App\Models\Produk;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PenjualanContractTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    // === INDEX ===

    public function test_index_returns_paginated_for_super_admin(): void
    {
        $this->createPenjualan('salesa@hibiscusefsya.com');
        $token = $this->login('superadmin@hibiscusefsya.com');

        $response = $this->getJson('/api/v1/penjualan', ['Authorization' => "Bearer $token"]);

        $response->assertStatus(200)
            ->assertJsonStructure(['data', 'current_page', 'per_page', 'total']);
    }

    public function test_user_sees_only_own_penjualan(): void
    {
        $this->createPenjualan('salesa@hibiscusefsya.com');
        $this->createPenjualan('salesb@hibiscusefsya.com');
        $token = $this->login('salesa@hibiscusefsya.com');

        $response = $this->getJson('/api/v1/penjualan', ['Authorization' => "Bearer $token"]);

        $response->assertStatus(200);
        $this->assertEquals(1, $response->json('total'));
    }

    // === STORE ===

    public function test_store_success_creates_pending(): void
    {
        $token = $this->login('salesa@hibiscusefsya.com');
        $gudangA = Gudang::where('nama_gudang', 'Gudang A')->first();
        $produk = Produk::first();

        $response = $this->postJson('/api/v1/penjualan', [
            'pelanggan' => 'Customer Test',
            'tgl_transaksi' => '2026-06-05',
            'syarat_pembayaran' => 'Cash',
            'gudang_id' => $gudangA->id,
            'tax_percentage' => 0,
            'items' => [
                ['produk_id' => $produk->id, 'kuantitas' => 2],
            ],
        ], ['Authorization' => "Bearer $token"]);

        $response->assertStatus(201)
            ->assertJson(['message' => 'Penjualan berhasil dibuat.'])
            ->assertJsonPath('data.status', 'Pending');

        // Verify nomor generated
        $this->assertStringStartsWith('INV-', $response->json('data.nomor'));
    }

    public function test_store_insufficient_stock_returns_422(): void
    {
        $token = $this->login('salesa@hibiscusefsya.com');
        $gudangA = Gudang::where('nama_gudang', 'Gudang A')->first();
        $produk = Produk::first();

        $response = $this->postJson('/api/v1/penjualan', [
            'pelanggan' => 'Customer',
            'tgl_transaksi' => '2026-06-05',
            'syarat_pembayaran' => 'Cash',
            'gudang_id' => $gudangA->id,
            'tax_percentage' => 0,
            'items' => [
                ['produk_id' => $produk->id, 'kuantitas' => 9999],
            ],
        ], ['Authorization' => "Bearer $token"]);

        $response->assertStatus(422)
            ->assertJson(['message' => 'Stok tidak mencukupi.'])
            ->assertJsonStructure(['errors']);
    }

    public function test_store_spectator_forbidden(): void
    {
        $token = $this->login('spectator@hibiscusefsya.com');
        $gudangA = Gudang::where('nama_gudang', 'Gudang A')->first();
        $produk = Produk::first();

        $response = $this->postJson('/api/v1/penjualan', [
            'pelanggan' => 'Test',
            'tgl_transaksi' => '2026-06-05',
            'syarat_pembayaran' => 'Cash',
            'gudang_id' => $gudangA->id,
            'tax_percentage' => 0,
            'items' => [['produk_id' => $produk->id, 'kuantitas' => 1]],
        ], ['Authorization' => "Bearer $token"]);

        $response->assertStatus(403)
            ->assertJson(['message' => 'Spectator tidak bisa membuat transaksi.']);
    }

    public function test_store_calculates_grosir_price(): void
    {
        $token = $this->login('salesa@hibiscusefsya.com');
        $gudangA = Gudang::where('nama_gudang', 'Gudang A')->first();
        $produk = Produk::where('item_code', 'SBN-001')->first(); // harga 25000, grosir 22000

        $response = $this->postJson('/api/v1/penjualan', [
            'pelanggan' => 'Grosir Customer',
            'tgl_transaksi' => '2026-06-05',
            'syarat_pembayaran' => 'Net 30',
            'gudang_id' => $gudangA->id,
            'tipe_harga' => 'grosir',
            'tax_percentage' => 0,
            'diskon_akhir' => 0,
            'items' => [
                ['produk_id' => $produk->id, 'kuantitas' => 10, 'diskon' => 0],
            ],
        ], ['Authorization' => "Bearer $token"]);

        $response->assertStatus(201);
        // 10 * 22000 = 220000
        $this->assertEquals(220000, $response->json('data.grand_total'));
    }

    // === APPROVE ===

    public function test_approve_pending_to_approved(): void
    {
        $penjualan = $this->createPenjualan('salesa@hibiscusefsya.com');
        $token = $this->login('admin@hibiscusefsya.com');

        $response = $this->postJson("/api/v1/penjualan/{$penjualan->id}/approve", [], ['Authorization' => "Bearer $token"]);

        $response->assertStatus(200)
            ->assertJson(['message' => 'Penjualan berhasil di-approve.'])
            ->assertJsonPath('data.status', 'Approved');
    }

    public function test_approve_non_pending_fails(): void
    {
        $penjualan = $this->createPenjualan('salesa@hibiscusefsya.com');
        $penjualan->update(['status' => 'Approved']);
        $token = $this->login('superadmin@hibiscusefsya.com');

        $response = $this->postJson("/api/v1/penjualan/{$penjualan->id}/approve", [], ['Authorization' => "Bearer $token"]);

        $response->assertStatus(422)
            ->assertJson(['message' => 'Hanya transaksi Pending yang bisa di-approve.']);
    }

    // === CANCEL ===

    public function test_cancel_own_transaction(): void
    {
        $penjualan = $this->createPenjualan('salesa@hibiscusefsya.com');
        $token = $this->login('salesa@hibiscusefsya.com');

        $response = $this->postJson("/api/v1/penjualan/{$penjualan->id}/cancel", [], ['Authorization' => "Bearer $token"]);

        $response->assertStatus(200)
            ->assertJson(['message' => 'Penjualan berhasil dibatalkan.']);

        $this->assertDatabaseHas('penjualans', ['id' => $penjualan->id, 'status' => 'Canceled']);
    }

    public function test_cancel_other_user_forbidden(): void
    {
        $penjualan = $this->createPenjualan('salesa@hibiscusefsya.com');
        $token = $this->login('salesb@hibiscusefsya.com');

        $response = $this->postJson("/api/v1/penjualan/{$penjualan->id}/cancel", [], ['Authorization' => "Bearer $token"]);

        $response->assertStatus(403);
    }

    // === UNCANCEL ===

    public function test_uncancel_super_admin_only(): void
    {
        $penjualan = $this->createPenjualan('salesa@hibiscusefsya.com');
        $penjualan->update(['status' => 'Canceled']);
        $token = $this->login('superadmin@hibiscusefsya.com');

        $response = $this->postJson("/api/v1/penjualan/{$penjualan->id}/uncancel", [], ['Authorization' => "Bearer $token"]);

        $response->assertStatus(200)
            ->assertJson(['message' => 'Transaksi berhasil di-uncancel. Status kembali ke Pending.'])
            ->assertJsonPath('data.status', 'Pending');
    }

    public function test_uncancel_non_super_admin_forbidden(): void
    {
        $penjualan = $this->createPenjualan('salesa@hibiscusefsya.com');
        $penjualan->update(['status' => 'Canceled']);
        $token = $this->login('admin@hibiscusefsya.com');

        $response = $this->postJson("/api/v1/penjualan/{$penjualan->id}/uncancel", [], ['Authorization' => "Bearer $token"]);

        $response->assertStatus(403)
            ->assertJson(['message' => 'Hanya Super Admin yang dapat membatalkan pembatalan.']);
    }

    // === MARK AS PAID ===

    public function test_mark_paid_approved_to_lunas(): void
    {
        $penjualan = $this->createPenjualan('salesa@hibiscusefsya.com');
        $penjualan->update(['status' => 'Approved']);
        $token = $this->login('admin@hibiscusefsya.com');

        $response = $this->postJson("/api/v1/penjualan/{$penjualan->id}/mark-paid", [], ['Authorization' => "Bearer $token"]);

        $response->assertStatus(200)
            ->assertJson(['message' => 'Penjualan ditandai LUNAS.'])
            ->assertJsonPath('data.status', 'Lunas');
    }

    public function test_mark_paid_non_approved_fails(): void
    {
        $penjualan = $this->createPenjualan('salesa@hibiscusefsya.com');
        $token = $this->login('superadmin@hibiscusefsya.com');

        $response = $this->postJson("/api/v1/penjualan/{$penjualan->id}/mark-paid", [], ['Authorization' => "Bearer $token"]);

        $response->assertStatus(422)
            ->assertJson(['message' => 'Hanya transaksi Approved yang bisa ditandai Lunas.']);
    }

    // === UNMARK AS PAID ===

    public function test_unmark_paid_lunas_to_approved(): void
    {
        $penjualan = $this->createPenjualan('salesa@hibiscusefsya.com');
        $penjualan->update(['status' => 'Lunas']);
        $token = $this->login('superadmin@hibiscusefsya.com');

        $response = $this->postJson("/api/v1/penjualan/{$penjualan->id}/unmark-paid", [], ['Authorization' => "Bearer $token"]);

        $response->assertStatus(200)
            ->assertJson(['message' => 'Status penjualan dikembalikan ke Approved.'])
            ->assertJsonPath('data.status', 'Approved');
    }

    public function test_unmark_paid_non_super_admin_forbidden(): void
    {
        $penjualan = $this->createPenjualan('salesa@hibiscusefsya.com');
        $penjualan->update(['status' => 'Lunas']);
        $token = $this->login('admin@hibiscusefsya.com');

        $response = $this->postJson("/api/v1/penjualan/{$penjualan->id}/unmark-paid", [], ['Authorization' => "Bearer $token"]);

        $response->assertStatus(403)
            ->assertJson(['message' => 'Hanya Super Admin yang dapat melakukan ini.']);
    }

    // === HELPERS ===

    private function createPenjualan(string $email): Penjualan
    {
        $user = User::where('email', $email)->first();
        $gudang = $user->gudang ?? Gudang::first();
        $produk = Produk::first();

        return Penjualan::create([
            'user_id' => $user->id,
            'gudang_id' => $gudang->id,
            'nomor' => 'INV-TEST-' . rand(1000, 9999),
            'pelanggan' => 'Test Customer',
            'tgl_transaksi' => now(),
            'syarat_pembayaran' => 'Cash',
            'status' => 'Pending',
            'grand_total' => 50000,
            'tax_percentage' => 0,
            'diskon_akhir' => 0,
            'tipe_harga' => 'retail',
            'lampiran_paths' => [],
        ]);
    }

    private function login(string $email): string
    {
        return $this->postJson('/api/v1/login', [
            'email' => $email, 'password' => 'password123',
        ])->json('token');
    }
}
