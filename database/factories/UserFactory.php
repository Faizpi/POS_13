<?php

namespace Database\Factories;

use App\Models\Gudang;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
        ];
    }

    public function superAdmin(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => 'super_admin',
            'gudang_id' => null,
            'current_gudang_id' => null,
            'receives_transaction_email' => false,
            'receives_transaction_whatsapp' => false,
        ]);
    }

    public function admin(?Gudang $gudang = null): static
    {
        return $this
            ->state(fn (array $attributes) => [
                'role' => 'admin',
                'gudang_id' => null,
                'current_gudang_id' => $gudang?->id,
                'receives_transaction_email' => false,
                'receives_transaction_whatsapp' => false,
                'can_export_pdf' => true,
                'can_export_excel' => true,
            ])
            ->afterCreating(function (User $user) use ($gudang): void {
                if ($gudang !== null) {
                    $user->gudangs()->syncWithoutDetaching([$gudang->id]);
                }
            });
    }

    public function sales(?Gudang $gudang = null): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => 'user',
            'gudang_id' => $gudang?->id,
            'current_gudang_id' => null,
            'receives_transaction_email' => false,
            'receives_transaction_whatsapp' => false,
        ]);
    }

    public function spectator(?Gudang $gudang = null): static
    {
        return $this
            ->state(fn (array $attributes) => [
                'role' => 'spectator',
                'gudang_id' => null,
                'current_gudang_id' => $gudang?->id,
                'receives_transaction_email' => false,
                'receives_transaction_whatsapp' => false,
            ])
            ->afterCreating(function (User $user) use ($gudang): void {
                if ($gudang !== null) {
                    $user->spectatorGudangs()->syncWithoutDetaching([$gudang->id]);
                }
            });
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }
}
