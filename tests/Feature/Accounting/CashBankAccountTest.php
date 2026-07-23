<?php

declare(strict_types=1);

namespace Tests\Feature\Accounting;

use App\Accounting\AccountCategory;
use App\Accounting\CashAccountType;
use App\Accounting\DomainException;
use App\Models\Account;
use App\Models\CashBankAccount;
use App\Models\Gudang;
use App\Models\User;
use App\Services\Accounting\AccountingAuthorization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CashBankAccountTest extends TestCase
{
    use RefreshDatabase;

    public function test_cash_bank_schema_and_model_are_available(): void
    {
        $this->assertTrue(class_exists('App\\Models\\CashBankAccount'), 'Cash/bank master model is missing.');
        $this->assertTrue(Schema::hasTable('cash_bank_accounts'), 'Cash/bank master table is missing.');

        foreach ([
            'name',
            'type',
            'account_id',
            'gudang_id',
            'bank_name',
            'bank_account_number',
            'bank_account_holder',
            'is_active',
        ] as $column) {
            $this->assertTrue(Schema::hasColumn('cash_bank_accounts', $column), "Missing column: {$column}");
        }

        $this->assertTrue(Schema::hasColumn('gudangs', 'is_active'), 'Gudang active state is missing.');
    }

    public function test_it_persists_distinct_cash_masters_for_one_active_warehouse(): void
    {
        $cashBankAccount = $this->cashBankAccountClass();
        $gudang = Gudang::create(['nama_gudang' => 'Gudang Kas', 'alamat_gudang' => 'Jl. Kas']);
        $firstAccount = $this->compatibleAccount(CashAccountType::Kas);
        $secondAccount = $this->compatibleAccount(CashAccountType::Kas);

        $first = $cashBankAccount::query()->create([
            'name' => 'Kas Operasional',
            'type' => CashAccountType::Kas,
            'account_id' => $firstAccount->id,
            'gudang_id' => $gudang->id,
            'is_active' => true,
        ]);
        $second = $cashBankAccount::query()->create([
            'name' => 'Kas Retur',
            'type' => CashAccountType::Kas,
            'account_id' => $secondAccount->id,
            'gudang_id' => $gudang->id,
            'is_active' => true,
        ]);

        $this->assertSame($gudang->id, $first->gudang_id);
        $this->assertSame($gudang->id, $second->gudang_id);
        $this->assertCount(2, $cashBankAccount::query()->where('gudang_id', $gudang->id)->get());
        $this->assertTrue($first->account->is($firstAccount));
        $this->assertTrue($first->gudang->is($gudang));
    }

    public function test_it_allows_a_global_bank_account_with_bank_metadata(): void
    {
        $cashBankAccount = $this->cashBankAccountClass();
        $account = $this->compatibleAccount(CashAccountType::Bank);

        $bank = $cashBankAccount::query()->create([
            'name' => 'BCA Operasional',
            'type' => CashAccountType::Bank,
            'account_id' => $account->id,
            'gudang_id' => null,
            'bank_name' => 'BCA',
            'bank_account_number' => '1234567890',
            'bank_account_holder' => 'PT Hibiscus Efsya',
            'is_active' => true,
        ]);

        $this->assertNull($bank->gudang_id);
        $this->assertSame(CashAccountType::Bank, $bank->type);
        $this->assertSame('BCA', $bank->bank_name);
        $this->assertSame('1234567890', $bank->bank_account_number);
        $this->assertSame('PT Hibiscus Efsya', $bank->bank_account_holder);
    }

    public function test_factory_creates_a_compatible_cash_bank_master(): void
    {
        $gudang = Gudang::create(['nama_gudang' => 'Gudang Factory', 'alamat_gudang' => 'Jl. Factory']);

        $cashBankAccount = CashBankAccount::factory()->forGudang($gudang)->create();

        $this->assertTrue($cashBankAccount->account->is_active);
        $this->assertTrue($cashBankAccount->account->isPostable());
        $this->assertSame(AccountCategory::Aset, $cashBankAccount->account->category);
        $this->assertSame($cashBankAccount->type->value, $cashBankAccount->account->subcategory);
        $this->assertSame($gudang->id, $cashBankAccount->gudang_id);
    }

    public function test_it_rejects_reusing_an_account_for_another_cash_bank_master(): void
    {
        $cashBankAccount = $this->cashBankAccountClass();
        $account = $this->compatibleAccount(CashAccountType::Kas);

        $cashBankAccount::query()->create([
            'name' => 'Kas Pertama',
            'type' => CashAccountType::Kas,
            'account_id' => $account->id,
            'is_active' => true,
        ]);

        $this->expectException(QueryException::class);

        $cashBankAccount::query()->create([
            'name' => 'Kas Duplikat',
            'type' => CashAccountType::Kas,
            'account_id' => $account->id,
            'is_active' => true,
        ]);
    }

    public function test_missing_account_foreign_key_rejects_the_insert_without_a_partial_row(): void
    {
        $this->expectException(QueryException::class);

        try {
            CashBankAccount::query()->create([
                'name' => 'Kas Akun Tidak Ada',
                'type' => CashAccountType::Kas,
                'account_id' => 999_999,
                'is_active' => true,
            ]);
        } finally {
            $this->assertDatabaseCount('cash_bank_accounts', 0);
        }
    }

    public function test_missing_gudang_foreign_key_rejects_the_insert_without_a_partial_row(): void
    {
        $this->expectException(QueryException::class);

        try {
            CashBankAccount::query()->create([
                'name' => 'Kas Gudang Tidak Ada',
                'type' => CashAccountType::Kas,
                'account_id' => $this->compatibleAccount(CashAccountType::Kas)->id,
                'gudang_id' => 999_999,
                'is_active' => true,
            ]);
        } finally {
            $this->assertDatabaseCount('cash_bank_accounts', 0);
        }
    }

    public function test_it_rejects_inactive_non_postable_and_incompatible_accounts(): void
    {
        $cashBankAccount = $this->cashBankAccountClass();

        foreach ([
            $this->compatibleAccount(CashAccountType::Kas, ['is_active' => false]),
            $this->compatibleAccount(CashAccountType::Kas, ['is_postable' => false]),
            Account::factory()->create(['category' => AccountCategory::Kewajiban, 'subcategory' => 'bank']),
            Account::factory()->create(['category' => AccountCategory::Aset, 'subcategory' => 'inventory']),
        ] as $account) {
            try {
                $cashBankAccount::query()->create([
                    'name' => 'Tidak Valid '.$account->id,
                    'type' => CashAccountType::Kas,
                    'account_id' => $account->id,
                    'is_active' => true,
                ]);
                $this->fail('Invalid COA compatibility was persisted.');
            } catch (DomainException) {
                $this->assertDatabaseMissing('cash_bank_accounts', ['account_id' => $account->id]);
            }
        }
    }

    public function test_it_rejects_an_account_whose_cash_type_does_not_match_the_master_type(): void
    {
        $cashBankAccount = $this->cashBankAccountClass();
        $bankAccount = $this->compatibleAccount(CashAccountType::Bank);

        $this->expectException(DomainException::class);

        $cashBankAccount::query()->create([
            'name' => 'Kas dengan COA Bank',
            'type' => CashAccountType::Kas,
            'account_id' => $bankAccount->id,
            'is_active' => true,
        ]);
    }

    public function test_it_rejects_an_inactive_warehouse_and_preserves_existing_warehouse_state(): void
    {
        $cashBankAccount = $this->cashBankAccountClass();
        $activeGudang = Gudang::create(['nama_gudang' => 'Gudang Lama', 'alamat_gudang' => 'Jl. Lama']);
        $inactiveGudang = Gudang::create([
            'nama_gudang' => 'Gudang Nonaktif',
            'alamat_gudang' => 'Jl. Nonaktif',
            'is_active' => false,
        ]);

        $this->assertTrue($activeGudang->is_active);

        $this->expectException(DomainException::class);

        $cashBankAccount::query()->create([
            'name' => 'Kas Gudang Nonaktif',
            'type' => CashAccountType::Kas,
            'account_id' => $this->compatibleAccount(CashAccountType::Kas)->id,
            'gudang_id' => $inactiveGudang->id,
            'is_active' => true,
        ]);
    }

    public function test_accounting_configuration_mutation_is_restricted_to_super_admin(): void
    {
        $authorization = app(AccountingAuthorization::class);

        $superAdmin = User::factory()->create(['role' => 'super_admin']);
        $admin = User::factory()->create(['role' => 'admin']);
        $spectator = User::factory()->create(['role' => 'spectator']);
        $sales = User::factory()->create(['role' => 'user']);

        $this->assertTrue($authorization->canManageConfig($superAdmin));
        $this->assertFalse($authorization->canManageConfig($admin));
        $this->assertFalse($authorization->canManageConfig($spectator));
        $this->assertFalse($authorization->canManageConfig($sales));
    }

    /** @return class-string<Model> */
    private function cashBankAccountClass(): string
    {
        $class = 'App\\Models\\CashBankAccount';
        $this->assertTrue(class_exists($class), 'Cash/bank master model is missing.');

        return $class;
    }

    /** @param array<string, mixed> $overrides */
    private function compatibleAccount(CashAccountType $type, array $overrides = []): Account
    {
        return Account::factory()->create([
            'category' => AccountCategory::Aset,
            'subcategory' => $type->value,
            'is_active' => true,
            'is_postable' => true,
            ...$overrides,
        ]);
    }
}
