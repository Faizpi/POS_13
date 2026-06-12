<?php

namespace Tests\Feature\Api;

use App\Models\PersonalAccessToken;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthContractTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    // === LOGIN ===

    public function test_login_success_returns_correct_shape(): void
    {
        $response = $this->postJson('/api/v1/login', [
            'email' => 'salesa@hibiscusefsya.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'message',
                'token',
                'user' => ['id', 'name', 'email', 'role', 'alamat', 'no_telp', 'avatar_url', 'gudang_id', 'current_gudang_id'],
            ])
            ->assertJson(['message' => 'Login berhasil.']);

        // Token must be 64 chars
        $this->assertEquals(64, strlen($response->json('token')));

        // User shape
        $this->assertEquals('user', $response->json('user.role'));
    }

    public function test_login_invalid_credentials_returns_401(): void
    {
        $response = $this->postJson('/api/v1/login', [
            'email' => 'salesa@hibiscusefsya.com',
            'password' => 'wrongpassword',
        ]);

        $response->assertStatus(401)
            ->assertJson(['message' => 'Email atau password salah.']);
    }

    public function test_login_validation_returns_422(): void
    {
        $response = $this->postJson('/api/v1/login', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email', 'password']);
    }

    public function test_login_creates_hashed_token_in_database(): void
    {
        $response = $this->postJson('/api/v1/login', [
            'email' => 'salesa@hibiscusefsya.com',
            'password' => 'password123',
        ]);

        $plainToken = $response->json('token');
        $hashed = hash('sha256', $plainToken);

        $this->assertDatabaseHas('personal_access_tokens', [
            'token' => $hashed,
            'user_id' => $response->json('user.id'),
        ]);
    }

    // === PROTECTED ENDPOINT WITHOUT TOKEN ===

    public function test_missing_bearer_returns_401_unauthenticated(): void
    {
        $response = $this->getJson('/api/v1/profile');

        $response->assertStatus(401)
            ->assertJson(['message' => 'Unauthenticated.']);
    }

    public function test_invalid_bearer_returns_401_expired(): void
    {
        $response = $this->getJson('/api/v1/profile', [
            'Authorization' => 'Bearer invalidtokenhere',
        ]);

        $response->assertStatus(401)
            ->assertJson(['message' => 'Token invalid atau sudah expired.']);
    }

    public function test_expired_token_returns_401(): void
    {
        $user = User::where('email', 'salesa@hibiscusefsya.com')->first();
        $plainToken = \Illuminate\Support\Str::random(64);

        PersonalAccessToken::create([
            'user_id' => $user->id,
            'name' => 'test',
            'token' => hash('sha256', $plainToken),
            'expires_at' => now()->subDay(),
        ]);

        $response = $this->getJson('/api/v1/profile', [
            'Authorization' => 'Bearer ' . $plainToken,
        ]);

        $response->assertStatus(401)
            ->assertJson(['message' => 'Token invalid atau sudah expired.']);
    }

    // === PROFILE ===

    public function test_profile_returns_user_and_gudang(): void
    {
        $token = $this->loginAndGetToken('salesa@hibiscusefsya.com');

        $response = $this->getJson('/api/v1/profile', [
            'Authorization' => 'Bearer ' . $token,
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'user' => ['id', 'name', 'email', 'role', 'alamat', 'no_telp', 'avatar_url', 'gudang_id', 'current_gudang_id'],
                'gudang',
            ]);
    }

    // === LOGOUT ===

    public function test_logout_deletes_token(): void
    {
        $token = $this->loginAndGetToken('salesa@hibiscusefsya.com');
        $hashed = hash('sha256', $token);

        $this->assertDatabaseHas('personal_access_tokens', ['token' => $hashed]);

        $response = $this->postJson('/api/v1/logout', [], [
            'Authorization' => 'Bearer ' . $token,
        ]);

        $response->assertStatus(200)
            ->assertJson(['message' => 'Logout berhasil.']);

        $this->assertDatabaseMissing('personal_access_tokens', ['token' => $hashed]);
    }

    // === CHANGE PASSWORD ===

    public function test_change_password_wrong_old_returns_422(): void
    {
        $token = $this->loginAndGetToken('salesa@hibiscusefsya.com');

        $response = $this->postJson('/api/v1/change-password', [
            'current_password' => 'wrongold',
            'new_password' => 'newpass123',
            'new_password_confirmation' => 'newpass123',
        ], ['Authorization' => 'Bearer ' . $token]);

        $response->assertStatus(422)
            ->assertJson(['message' => 'Password lama salah.']);
    }

    public function test_change_password_success(): void
    {
        $token = $this->loginAndGetToken('salesa@hibiscusefsya.com');

        $response = $this->postJson('/api/v1/change-password', [
            'current_password' => 'password123',
            'new_password' => 'newpassword1',
            'new_password_confirmation' => 'newpassword1',
        ], ['Authorization' => 'Bearer ' . $token]);

        $response->assertStatus(200)
            ->assertJson(['message' => 'Password berhasil diubah.']);
    }

    // === HELPER ===

    private function loginAndGetToken(string $email): string
    {
        $response = $this->postJson('/api/v1/login', [
            'email' => $email,
            'password' => 'password123',
        ]);

        return $response->json('token');
    }
}
