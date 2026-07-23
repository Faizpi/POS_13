<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Accounting\AccountCategory;
use App\Models\Account;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Account>
 */
class AccountFactory extends Factory
{
    protected $model = Account::class;

    public function definition(): array
    {
        $category = fake()->randomElement(AccountCategory::cases());

        return [
            'code' => $this->generateUniqueCode(),
            'name' => fake()->words(3, true),
            'parent_id' => null,
            'category' => $category,
            'subcategory' => null,
            'normal_balance' => $category->normalBalance(),
            'statement_classification' => $category->statementClassification(),
            'cash_flow_category' => null,
            'cash_flow_line' => null,
            'is_postable' => true,
            'is_control_account' => false,
            'is_system' => false,
            'is_active' => true,
            'is_used' => false,
            'display_order' => fake()->numberBetween(0, 1000),
        ];
    }

    /**
     * Create an account with a specific parent, inheriting category.
     */
    public function withParent(Account $parent): static
    {
        return $this->state(fn (array $attributes) => [
            'parent_id' => $parent->id,
            'category' => $parent->category,
            'normal_balance' => $parent->category->normalBalance(),
            'statement_classification' => $parent->category->statementClassification(),
        ]);
    }

    public function heading(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_postable' => false,
        ]);
    }

    public function system(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_system' => true,
        ]);
    }

    public function control(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_control_account' => true,
        ]);
    }

    public function postable(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_postable' => true,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    private static int $codeSequence = 1;

    private function generateUniqueCode(): string
    {
        $code = 'F-'.str_pad((string) self::$codeSequence, 6, '0', STR_PAD_LEFT);
        self::$codeSequence++;

        return $code;
    }
}
