<?php

namespace Tests\Feature\Api;

use App\Models\Gudang;
use App\Models\Produk;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProdukContractTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    // === INDEX ===

    public function test_index_returns_paginated_produk(): void
    {
        $token = $this->login('superadmin@hibiscusefsya.com');

        $response = $this->getJson('/api/v1/produk', ['Authorization' => "Bearer $token"]);

        $response->assertStatus(200)
            ->assertJsonStructure(['data', 'current_page', 'per_page', 'total']);
        $this->assertEquals(3, $response->json('total'));
    }

    public function test_user_sees_only_gudang_products(): void
    {
        $token = $this->login('salesa@hibiscusefsya.com');

        $response = $this->getJson('/api/v1/produk', ['Authorization' => "Bearer $token"]);

        $response->assertStatus(200);
        // Gudang A has 3 products
        $this->assertEquals(3, $response->json('total'));
    }

    public function test_index_search_filter(): void
    {
        $token = $this->login('superadmin@hibiscusefsya.com');

        $response = $this->getJson('/api/v1/produk?search=Sabun', ['Authorization' => "Bearer $token"]);

        $response->assertStatus(200);
        $this->assertEquals(1, $response->json('total'));
    }

    // === SHOW ===

    public function test_show_includes_stok_relation(): void
    {
        $token = $this->login('superadmin@hibiscusefsya.com');
        $produk = Produk::first();

        $response = $this->getJson("/api/v1/produk/{$produk->id}", ['Authorization' => "Bearer $token"]);

        $response->assertStatus(200)
            ->assertJsonStructure(['id', 'nama_produk', 'item_code', 'harga', 'stok_di_gudang']);
    }

    // === STOK BY GUDANG ===

    public function test_stok_by_gudang_forbidden_for_unassigned(): void
    {
        $token = $this->login('salesb@hibiscusefsya.com');
        $gudangA = Gudang::where('nama_gudang', 'Gudang A')->first();

        $response = $this->getJson("/api/v1/produk/stok/{$gudangA->id}", ['Authorization' => "Bearer $token"]);

        $response->assertStatus(403)
            ->assertJson(['message' => 'Unauthorized']);
    }

    public function test_stok_by_gudang_success(): void
    {
        $token = $this->login('salesa@hibiscusefsya.com');
        $gudangA = Gudang::where('nama_gudang', 'Gudang A')->first();

        $response = $this->getJson("/api/v1/produk/stok/{$gudangA->id}", ['Authorization' => "Bearer $token"]);

        $response->assertStatus(200);
        $this->assertCount(3, $response->json());
    }

    // === STORE ===

    public function test_store_forbidden_for_non_super_admin(): void
    {
        $token = $this->login('admin@hibiscusefsya.com');

        $response = $this->postJson('/api/v1/produk', [
            'nama_produk' => 'Test', 'harga' => 1000, 'satuan' => 'Pcs',
        ], ['Authorization' => "Bearer $token"]);

        $response->assertStatus(403)
            ->assertJson(['message' => 'Unauthorized']);
    }

    public function test_store_success_super_admin(): void
    {
        $token = $this->login('superadmin@hibiscusefsya.com');

        $response = $this->postJson('/api/v1/produk', [
            'nama_produk' => 'Produk Baru',
            'item_code' => 'NEW-001',
            'harga' => 50000,
            'harga_grosir' => 45000,
            'satuan' => 'Pcs',
            'deskripsi' => 'Deskripsi produk baru',
        ], ['Authorization' => "Bearer $token"]);

        $response->assertStatus(201)
            ->assertJson(['message' => 'Produk berhasil dibuat.'])
            ->assertJsonPath('data.nama_produk', 'Produk Baru');
    }

    public function test_store_validation_satuan(): void
    {
        $token = $this->login('superadmin@hibiscusefsya.com');

        $response = $this->postJson('/api/v1/produk', [
            'nama_produk' => 'Test', 'harga' => 1000, 'satuan' => 'Invalid',
        ], ['Authorization' => "Bearer $token"]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['satuan']);
    }

    // === UPDATE ===

    public function test_update_success(): void
    {
        $token = $this->login('superadmin@hibiscusefsya.com');
        $produk = Produk::first();

        $response = $this->putJson("/api/v1/produk/{$produk->id}", [
            'nama_produk' => 'Updated Name',
            'harga' => 30000,
            'satuan' => 'Lusin',
        ], ['Authorization' => "Bearer $token"]);

        $response->assertStatus(200)
            ->assertJson(['message' => 'Produk berhasil diupdate.']);
    }

    // === DELETE ===

    public function test_destroy_success(): void
    {
        $token = $this->login('superadmin@hibiscusefsya.com');
        $produk = Produk::create([
            'nama_produk' => 'To Delete', 'harga' => 1000, 'satuan' => 'Pcs',
        ]);

        $response = $this->deleteJson("/api/v1/produk/{$produk->id}", [], ['Authorization' => "Bearer $token"]);

        $response->assertStatus(200)
            ->assertJson(['message' => 'Produk berhasil dihapus.']);
        $this->assertDatabaseMissing('produks', ['id' => $produk->id]);
    }

    private function login(string $email): string
    {
        return $this->postJson('/api/v1/login', [
            'email' => $email, 'password' => 'password123',
        ])->json('token');
    }
}
