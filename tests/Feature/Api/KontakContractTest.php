<?php

namespace Tests\Feature\Api;

use App\Models\Kontak;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class KontakContractTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    // === INDEX ===

    public function test_super_admin_sees_all_kontaks(): void
    {
        $token = $this->login('superadmin@hibiscusefsya.com');

        $response = $this->getJson('/api/v1/kontak', ['Authorization' => "Bearer $token"]);

        $response->assertStatus(200)
            ->assertJsonStructure(['data', 'current_page', 'total']);
        $this->assertEquals(2, $response->json('total'));
    }

    public function test_user_sees_only_own_kontaks(): void
    {
        $token = $this->login('salesa@hibiscusefsya.com');

        $response = $this->getJson('/api/v1/kontak', ['Authorization' => "Bearer $token"]);

        $response->assertStatus(200);
        // Sales A created Toko Melati
        $this->assertEquals(1, $response->json('total'));
    }

    public function test_spectator_sees_all_kontaks(): void
    {
        $token = $this->login('spectator@hibiscusefsya.com');

        $response = $this->getJson('/api/v1/kontak', ['Authorization' => "Bearer $token"]);

        $response->assertStatus(200);
        $this->assertEquals(2, $response->json('total'));
    }

    // === STORE ===

    public function test_store_success(): void
    {
        $token = $this->login('salesa@hibiscusefsya.com');

        $response = $this->postJson('/api/v1/kontak', [
            'nama' => 'Toko Baru',
            'no_telp' => '083333333333',
            'alamat' => 'Jl. Baru',
        ], ['Authorization' => "Bearer $token"]);

        $response->assertStatus(201)
            ->assertJson(['message' => 'Kontak berhasil dibuat.'])
            ->assertJsonPath('data.nama', 'Toko Baru');

        // Verify created_by is set
        $this->assertDatabaseHas('kontaks', [
            'nama' => 'Toko Baru',
            'created_by' => \App\Models\User::where('email', 'salesa@hibiscusefsya.com')->first()->id,
        ]);
    }

    public function test_store_validation_pin_size(): void
    {
        $token = $this->login('salesa@hibiscusefsya.com');

        $response = $this->postJson('/api/v1/kontak', [
            'nama' => 'Test',
            'pin' => '123', // Must be 6 chars
        ], ['Authorization' => "Bearer $token"]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['pin']);
    }

    // === SHOW ===

    public function test_show_forbidden_for_other_user(): void
    {
        $token = $this->login('salesb@hibiscusefsya.com');
        $kontakA = Kontak::where('nama', 'Toko Melati')->first();

        $response = $this->getJson("/api/v1/kontak/{$kontakA->id}", ['Authorization' => "Bearer $token"]);

        $response->assertStatus(403)
            ->assertJson(['message' => 'Unauthorized']);
    }

    public function test_show_success_for_owner(): void
    {
        $token = $this->login('salesa@hibiscusefsya.com');
        $kontak = Kontak::where('nama', 'Toko Melati')->first();

        $response = $this->getJson("/api/v1/kontak/{$kontak->id}", ['Authorization' => "Bearer $token"]);

        $response->assertStatus(200)
            ->assertJsonPath('nama', 'Toko Melati');
    }

    // === UPDATE ===

    public function test_update_success(): void
    {
        $token = $this->login('salesa@hibiscusefsya.com');
        $kontak = Kontak::where('nama', 'Toko Melati')->first();

        $response = $this->putJson("/api/v1/kontak/{$kontak->id}", [
            'nama' => 'Toko Melati Updated',
            'no_telp' => '089999999999',
        ], ['Authorization' => "Bearer $token"]);

        $response->assertStatus(200)
            ->assertJson(['message' => 'Kontak berhasil diupdate.']);
    }

    // === DELETE ===

    public function test_delete_success(): void
    {
        $token = $this->login('salesa@hibiscusefsya.com');
        $kontak = Kontak::where('nama', 'Toko Melati')->first();

        $response = $this->deleteJson("/api/v1/kontak/{$kontak->id}", [], ['Authorization' => "Bearer $token"]);

        $response->assertStatus(200)
            ->assertJson(['message' => 'Kontak berhasil dihapus.']);
    }

    public function test_delete_forbidden_other_user(): void
    {
        $token = $this->login('salesb@hibiscusefsya.com');
        $kontak = Kontak::where('nama', 'Toko Melati')->first();

        $response = $this->deleteJson("/api/v1/kontak/{$kontak->id}", [], ['Authorization' => "Bearer $token"]);

        $response->assertStatus(403);
    }

    private function login(string $email): string
    {
        return $this->postJson('/api/v1/login', [
            'email' => $email, 'password' => 'password123',
        ])->json('token');
    }
}
