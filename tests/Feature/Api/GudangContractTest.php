<?php

namespace Tests\Feature\Api;

use App\Models\Gudang;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GudangContractTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    // === INDEX ===

    public function test_super_admin_sees_all_gudangs(): void
    {
        $token = $this->login('superadmin@hibiscusefsya.com');

        $response = $this->getJson('/api/v1/gudang', ['Authorization' => "Bearer $token"]);

        $response->assertStatus(200);
        $this->assertCount(2, $response->json()); // Gudang A + B
    }

    public function test_admin_sees_only_assigned_gudangs(): void
    {
        $token = $this->login('admin@hibiscusefsya.com');

        $response = $this->getJson('/api/v1/gudang', ['Authorization' => "Bearer $token"]);

        $response->assertStatus(200);
        $this->assertCount(2, $response->json()); // admin assigned to both
    }

    public function test_user_sees_only_own_gudang(): void
    {
        $token = $this->login('salesa@hibiscusefsya.com');

        $response = $this->getJson('/api/v1/gudang', ['Authorization' => "Bearer $token"]);

        $response->assertStatus(200);
        $this->assertCount(1, $response->json());
        $this->assertEquals('Gudang A', $response->json('0.nama_gudang'));
    }

    // === SWITCH ===

    public function test_switch_gudang_success(): void
    {
        $token = $this->login('admin@hibiscusefsya.com');
        $gudangB = Gudang::where('nama_gudang', 'Gudang B')->first();

        $response = $this->postJson('/api/v1/gudang/switch', [
            'gudang_id' => $gudangB->id,
        ], ['Authorization' => "Bearer $token"]);

        $response->assertStatus(200)
            ->assertJson(['message' => 'Gudang berhasil diganti.'])
            ->assertJsonPath('current_gudang.nama_gudang', 'Gudang B');
    }

    public function test_switch_gudang_forbidden_for_unassigned(): void
    {
        $token = $this->login('salesa@hibiscusefsya.com');
        $gudangB = Gudang::where('nama_gudang', 'Gudang B')->first();

        $response = $this->postJson('/api/v1/gudang/switch', [
            'gudang_id' => $gudangB->id,
        ], ['Authorization' => "Bearer $token"]);

        $response->assertStatus(403)
            ->assertJson(['message' => 'Tidak memiliki akses ke gudang ini.']);
    }

    // === STOK ===

    public function test_stok_forbidden_for_user_role(): void
    {
        $token = $this->login('salesa@hibiscusefsya.com');

        $response = $this->getJson('/api/v1/gudang/stok', ['Authorization' => "Bearer $token"]);

        $response->assertStatus(403)
            ->assertJson(['message' => 'Unauthorized']);
    }

    public function test_stok_returns_data_for_admin(): void
    {
        $token = $this->login('admin@hibiscusefsya.com');

        $response = $this->getJson('/api/v1/gudang/stok', ['Authorization' => "Bearer $token"]);

        $response->assertStatus(200);
        $data = $response->json();
        $this->assertNotEmpty($data);
        // Verify produk relation is loaded
        $this->assertArrayHasKey('produk', $data[0]);
    }

    // === STOK LOG ===

    public function test_stok_log_forbidden_for_user(): void
    {
        $token = $this->login('salesa@hibiscusefsya.com');

        $response = $this->getJson('/api/v1/gudang/stok-log', ['Authorization' => "Bearer $token"]);

        $response->assertStatus(403);
    }

    public function test_stok_log_returns_paginated_for_admin(): void
    {
        $token = $this->login('admin@hibiscusefsya.com');

        $response = $this->getJson('/api/v1/gudang/stok-log', ['Authorization' => "Bearer $token"]);

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
