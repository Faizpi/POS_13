<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Accounting\AccountCategory;
use App\Accounting\CashAccountType;
use App\Models\Account;
use App\Models\CashBankAccount;
use App\Models\Gudang;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CashBankAccount>
 */
class CashBankAccountFactory extends Factory
{
    protected $model = CashBankAccount::class;

    public function definition(): array
    {
        $type = fake()->randomElement(CashAccountType::cases());

        return [
            'name' => ucfirst($type->value).' '.fake()->unique()->word(),
            'type' => $type,
            'account_id' => Account::factory()->state([
                'category' => AccountCategory::Aset,
                'subcategory' => $type->value,
                'is_active' => true,
                'is_postable' => true,
            ]),
            'gudang_id' => null,
            'bank_name' => $type === CashAccountType::Bank ? fake()->company() : null,
            'bank_account_number' => $type === CashAccountType::Bank ? fake()->numerify('##########') : null,
            'bank_account_holder' => $type === CashAccountType::Bank ? fake()->name() : null,
            'is_active' => true,
        ];
    }

    public function forGudang(Gudang $gudang): static
    {
        return $this->state(fn (array $attributes): array => [
            'gudang_id' => $gudang->id,
        ]);
    }
}
