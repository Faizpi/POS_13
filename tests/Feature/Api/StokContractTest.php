<?php

namespace Tests\Feature\Api;

use App\Models\Gudang;
use App\Models\GudangProduk;
use App\Models\Produk;
use App\Models\StokLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StokContractTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    // === INDEX ===

    public function test_index_forbidden_for_user(): void
    {
        $token = $this->login('salesa@hibiscusefsya.com');

        $response = $this->getJson('/api/v1/stok', ['Authorization' => "Bearer $token"]);

        $response->assertStatus(403)
            ->assertJson(['message' => 'Unauthorized']);
    }

    public function test_index_returns_gudangs_with_stok_for_super_admin(): void
    {
        $token = $this->login('superadmin@hibiscusefsya.com');

        $response = $this->getJson('/api/v1/stok', ['Authorization' => "Bearer $token"]);

        $response->assertStatus(200);
        $this->assertGreaterThanOrEqual(2, count($response->json()));
    }

    public function test_index_normalizes_stok_total(): void
    {
        $token = $this->login('admin@hibiscusefsya.com');

        $response = $this->getJson('/api/v1/stok', ['Authorization' => "Bearer $token"]);

        $response->assertStatus(200);
        $gudangs = $response->json();
        // Verify stok is normalized (sum of types)
        if (! empty($gudangs) && isset($gudangs[0]['gudang_produks'])) {
            $first = $gudangs[0]['gudang_produks'][0];
            $expected = $first['stok_penjualan'] + $first['stok_gratis'] + $first['stok_sample'];
            $this->assertEquals($expected, $first['stok']);
        }
    }

    // === STORE (Manual stok update) ===

    public function test_store_forbidden_for_non_super_admin(): void
    {
        $token = $this->login('admin@hibiscusefsya.com');

        $response = $this->postJson('/api/v1/stok', [
            'gudang_id' => Gudang::first()->id,
            'produk_id' => Produk::first()->id,
            'stok_penjualan' => 100,
            'stok_gratis' => 10,
            'stok_sample' => 5,
        ], ['Authorization' => "Bearer $token"]);

        $response->assertStatus(403)
            ->assertJson(['message' => 'Hanya Super Admin yang boleh mengubah stok manual.']);
    }

    public function test_store_success_creates_stok_log(): void
    {
        $token = $this->login('superadmin@hibiscusefsya.com');
        $gudang = Gudang::first();
        $produk = Produk::first();

        $response = $this->postJson('/api/v1/stok', [
            'gudang_id' => $gudang->id,
            'produk_id' => $produk->id,
            'stok_penjualan' => 200,
            'stok_gratis' => 20,
            'stok_sample' => 15,
            'keterangan' => 'Restock test',
        ], ['Authorization' => "Bearer $token"]);

        $response->assertStatus(200)
            ->assertJson(['message' => 'Stok berhasil diperbarui.']);

        // Verify database
        $this->assertDatabaseHas('gudang_produk', [
            'gudang_id' => $gudang->id,
            'produk_id' => $produk->id,
            'stok' => 235, // 200+20+15
            'stok_penjualan' => 200,
            'stok_gratis' => 20,
            'stok_sample' => 15,
        ]);

        // StokLog should be created because total changed
        $this->assertDatabaseHas('stok_logs', [
            'produk_id' => $produk->id,
            'gudang_id' => $gudang->id,
            'stok_sesudah' => 235,
            'keterangan' => 'Restock test',
        ]);
    }

    public function test_store_no_log_if_no_change(): void
    {
        $token = $this->login('superadmin@hibiscusefsya.com');
        $gp = GudangProduk::first();

        // Set same values as existing
        $response = $this->postJson('/api/v1/stok', [
            'gudang_id' => $gp->gudang_id,
            'produk_id' => $gp->produk_id,
            'stok_penjualan' => $gp->stok_penjualan,
            'stok_gratis' => $gp->stok_gratis,
            'stok_sample' => $gp->stok_sample,
        ], ['Authorization' => "Bearer $token"]);

        $response->assertStatus(200);

        // No log should be created
        $this->assertEquals(0, StokLog::count());
    }

    // === LOG ===

    public function test_log_forbidden_for_spectator(): void
    {
        $token = $this->login('spectator@hibiscusefsya.com');

        $response = $this->getJson('/api/v1/stok/log', ['Authorization' => "Bearer $token"]);

        $response->assertStatus(403)
            ->assertJson(['message' => 'Unauthorized']);
    }

    public function test_log_returns_paginated(): void
    {
        $token = $this->login('superadmin@hibiscusefsya.com');

        $response = $this->getJson('/api/v1/stok/log', ['Authorization' => "Bearer $token"]);

        $response->assertStatus(200)
            ->assertJsonStructure(['data', 'current_page', 'per_page']);
    }

    private function login(string $email): string
    {
        return $this->postJson('/api/v1/login', [
            'email' => $email, 'password' => 'password123',
        ])->json('token');
    }
}
