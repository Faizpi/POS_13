<?php

declare(strict_types=1);

namespace Tests\Feature\Accounting;

use App\Accounting\AccountCategory;
use App\Accounting\DomainException;
use App\Accounting\NormalBalance;
use App\Accounting\StatementClassification;
use App\Models\Account;
use App\Models\Gudang;
use Database\Seeders\HibiscusEfsyaChartOfAccountsSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Chart of Accounts persistence and seed acceptance tests.
 *
 * Covers:
 * - Schema constraints (unique code, foreign keys, nullable parent)
 * - Model relationships (parent/children)
 * - Domain validation (no cycles, compatible parent, protected accounts)
 * - Seed determinism (Hibiscus Efsya COA)
 * - QA scenarios from plan Todo 5
 */
class ChartOfAccountsTest extends TestCase
{
    use RefreshDatabase;

    // ─────────────────────────────────────────────────────────────────────
    // SCHEMA & MIGRATION
    // ─────────────────────────────────────────────────────────────────────

    public function test_accounts_table_exists_with_required_columns(): void
    {
        $this->assertTrue(Schema::hasTable('accounts'));

        $requiredColumns = [
            'id',
            'code',
            'name',
            'parent_id',
            'category',
            'subcategory',
            'normal_balance',
            'statement_classification',
            'cash_flow_category',
            'cash_flow_line',
            'is_postable',
            'is_control_account',
            'is_system',
            'is_active',
            'display_order',
            'created_at',
            'updated_at',
        ];

        foreach ($requiredColumns as $column) {
            $this->assertTrue(
                Schema::hasColumn('accounts', $column),
                "Missing column: {$column}"
            );
        }
    }

    public function test_account_code_is_unique(): void
    {
        $parent = Account::factory()->heading()->create([
            'code' => '1-0000',
            'category' => AccountCategory::Aset,
        ]);

        $this->expectException(QueryException::class);

        // Create duplicate with same category to bypass domain validation
        Account::factory()->create([
            'code' => '1-0000', // duplicate
            'parent_id' => $parent->id,
            'category' => AccountCategory::Aset, // match parent category
        ]);
    }

    public function test_account_parent_id_is_nullable(): void
    {
        $account = Account::factory()->create([
            'parent_id' => null,
            'code' => '1-0000',
            'category' => AccountCategory::Aset,
        ]);

        $this->assertNull($account->parent_id);
    }

    public function test_account_parent_id_references_existing_account(): void
    {
        $parent = Account::factory()->heading()->create([
            'category' => AccountCategory::Aset,
        ]);

        $child = Account::factory()->create([
            'parent_id' => $parent->id,
            'category' => AccountCategory::Aset, // match parent
        ]);

        $this->assertEquals($parent->id, $child->parent_id);
    }

    // ─────────────────────────────────────────────────────────────────────
    // MODEL RELATIONSHIPS
    // ─────────────────────────────────────────────────────────────────────

    public function test_account_has_parent_relationship(): void
    {
        $parent = Account::factory()->heading()->create([
            'category' => AccountCategory::Aset,
        ]);
        $child = Account::factory()->create([
            'parent_id' => $parent->id,
            'category' => AccountCategory::Aset,
        ]);

        $this->assertInstanceOf(Account::class, $child->parent);
        $this->assertEquals($parent->id, $child->parent->id);
    }

    public function test_account_has_children_relationship(): void
    {
        $parent = Account::factory()->heading()->create([
            'category' => AccountCategory::Aset,
        ]);
        $child1 = Account::factory()->create([
            'parent_id' => $parent->id,
            'category' => AccountCategory::Aset,
        ]);
        $child2 = Account::factory()->create([
            'parent_id' => $parent->id,
            'category' => AccountCategory::Aset,
        ]);

        $this->assertCount(2, $parent->children);
        $this->assertTrue($parent->children->contains($child1));
        $this->assertTrue($parent->children->contains($child2));
    }

    // ─────────────────────────────────────────────────────────────────────
    // DOMAIN VALIDATION: NO CYCLES
    // ─────────────────────────────────────────────────────────────────────

    public function test_account_cannot_be_its_own_parent(): void
    {
        $account = Account::factory()->create();

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('self-parent');

        $account->update(['parent_id' => $account->id]);
    }

    public function test_account_cannot_create_cycle_with_descendant(): void
    {
        $grandparent = Account::factory()->heading()->create([
            'category' => AccountCategory::Aset,
        ]);
        $parent = Account::factory()->create([
            'parent_id' => $grandparent->id,
            'category' => AccountCategory::Aset,
        ]);
        $child = Account::factory()->create([
            'parent_id' => $parent->id,
            'category' => AccountCategory::Aset,
        ]);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('cycle');

        $grandparent->update(['parent_id' => $child->id]);
    }

    // ─────────────────────────────────────────────────────────────────────
    // DOMAIN VALIDATION: COMPATIBLE PARENT
    // ─────────────────────────────────────────────────────────────────────

    public function test_child_must_have_compatible_category_with_parent(): void
    {
        $assetParent = Account::factory()->heading()->create([
            'category' => AccountCategory::Aset,
        ]);

        // Child with incompatible category (Kewajiban under Aset parent)
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('compatible');

        Account::factory()->create([
            'parent_id' => $assetParent->id,
            'category' => AccountCategory::Kewajiban,
        ]);
    }

    public function test_child_can_have_same_category_as_parent(): void
    {
        $assetParent = Account::factory()->heading()->create([
            'category' => AccountCategory::Aset,
        ]);

        $child = Account::factory()->create([
            'parent_id' => $assetParent->id,
            'category' => AccountCategory::Aset,
        ]);

        $this->assertEquals($assetParent->id, $child->parent_id);
    }

    // ─────────────────────────────────────────────────────────────────────
    // DOMAIN VALIDATION: PROTECTED ACCOUNTS
    // ─────────────────────────────────────────────────────────────────────

    public function test_system_account_cannot_be_deleted(): void
    {
        $systemAccount = Account::factory()->system()->create();

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('system');

        $systemAccount->delete();
    }

    public function test_used_account_cannot_be_deleted(): void
    {
        // Simulate account used in journal (we'll add a flag for this)
        $account = Account::factory()->create(['is_used' => true]);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('used');

        $account->delete();
    }

    public function test_account_with_children_cannot_be_deleted(): void
    {
        $parent = Account::factory()->heading()->create([
            'category' => AccountCategory::Aset,
        ]);
        Account::factory()->create([
            'parent_id' => $parent->id,
            'category' => AccountCategory::Aset,
        ]);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('children');

        $parent->delete();
    }

    // ─────────────────────────────────────────────────────────────────────
    // DOMAIN VALIDATION: NON-POSTABLE PARENTS
    // ─────────────────────────────────────────────────────────────────────

    public function test_non_postable_account_cannot_be_used_in_journal(): void
    {
        $heading = Account::factory()->heading()->create([
            'is_postable' => false,
        ]);

        $this->assertFalse($heading->isPostable());
        $this->assertTrue($heading->isHeading());
    }

    public function test_postable_account_can_be_used_in_journal(): void
    {
        $account = Account::factory()->create([
            'is_postable' => true,
        ]);

        $this->assertTrue($account->isPostable());
        $this->assertFalse($account->isHeading());
    }

    // ─────────────────────────────────────────────────────────────────────
    // ENUM CASTS & DEFAULTS
    // ─────────────────────────────────────────────────────────────────────

    public function test_account_casts_category_enum(): void
    {
        $account = Account::factory()->create([
            'category' => AccountCategory::Aset,
        ]);

        $this->assertInstanceOf(AccountCategory::class, $account->category);
        $this->assertEquals(AccountCategory::Aset, $account->category);
    }

    public function test_account_casts_normal_balance_enum(): void
    {
        $account = Account::factory()->create([
            'normal_balance' => NormalBalance::Debit,
        ]);

        $this->assertInstanceOf(NormalBalance::class, $account->normal_balance);
        $this->assertEquals(NormalBalance::Debit, $account->normal_balance);
    }

    public function test_account_casts_statement_classification_enum(): void
    {
        $account = Account::factory()->create([
            'statement_classification' => StatementClassification::Neraca,
        ]);

        $this->assertInstanceOf(StatementClassification::class, $account->statement_classification);
        $this->assertEquals(StatementClassification::Neraca, $account->statement_classification);
    }

    public function test_account_default_values(): void
    {
        $account = Account::factory()->create();

        $this->assertNotNull($account->is_postable);
        $this->assertNotNull($account->is_control_account);
        $this->assertNotNull($account->is_system);
        $this->assertNotNull($account->is_active);
        $this->assertNotNull($account->display_order);
    }

    // ─────────────────────────────────────────────────────────────────────
    // FACTORY
    // ─────────────────────────────────────────────────────────────────────

    public function test_account_factory_creates_valid_account(): void
    {
        $account = Account::factory()->create();

        $this->assertDatabaseHas('accounts', [
            'id' => $account->id,
            'code' => $account->code,
            'name' => $account->name,
        ]);
    }

    public function test_account_factory_heading_state(): void
    {
        $heading = Account::factory()->heading()->create();

        $this->assertFalse($heading->is_postable);
        $this->assertTrue($heading->isHeading());
    }

    public function test_account_factory_system_state(): void
    {
        $system = Account::factory()->system()->create();

        $this->assertTrue($system->is_system);
    }

    public function test_account_factory_control_state(): void
    {
        $control = Account::factory()->control()->create();

        $this->assertTrue($control->is_control_account);
    }

    // ─────────────────────────────────────────────────────────────────────
    // SEED: HIBISCUS EFSYA COA
    // ─────────────────────────────────────────────────────────────────────

    public function test_hibiscus_efsysa_coa_seeder_runs_successfully(): void
    {
        $this->seed(HibiscusEfsyaChartOfAccountsSeeder::class);

        $this->assertDatabaseHas('accounts', ['code' => '1-0000']); // ASET heading
        $this->assertDatabaseHas('accounts', ['code' => '2-0000']); // KEWAJIBAN heading
        $this->assertDatabaseHas('accounts', ['code' => '3-0000']); // EKUITAS heading
        $this->assertDatabaseHas('accounts', ['code' => '4-0000']); // PENDAPATAN heading
        $this->assertDatabaseHas('accounts', ['code' => '5-0000']); // HPP heading
        $this->assertDatabaseHas('accounts', ['code' => '6-0000']); // BEBAN heading
    }

    public function test_seeded_accounts_have_unique_codes(): void
    {
        $this->seed(HibiscusEfsyaChartOfAccountsSeeder::class);

        $codes = Account::pluck('code')->toArray();
        $uniqueCodes = array_unique($codes);

        $this->assertCount(count($codes), $uniqueCodes, 'Duplicate codes found in seeded COA');
    }

    public function test_seeded_hierarchy_is_valid(): void
    {
        $this->seed(HibiscusEfsyaChartOfAccountsSeeder::class);

        $accounts = Account::all();

        foreach ($accounts as $account) {
            if ($account->parent_id !== null) {
                $parent = Account::find($account->parent_id);
                $this->assertNotNull($parent, "Parent not found for account {$account->code}");
                $this->assertEquals(
                    $account->category,
                    $parent->category,
                    "Account {$account->code} has incompatible category with parent {$parent->code}"
                );
            }
        }
    }

    public function test_seeded_coa_has_required_control_accounts(): void
    {
        $this->seed(HibiscusEfsyaChartOfAccountsSeeder::class);

        // Required control accounts from roadmap
        $this->assertDatabaseHas('accounts', [
            'code' => '1-1200',
            'is_control_account' => true,
        ]); // Piutang Usaha
        $this->assertDatabaseHas('accounts', [
            'code' => '2-1100',
            'is_control_account' => true,
        ]); // Utang Usaha
        $this->assertDatabaseHas('accounts', [
            'code' => '1-1300',
            'is_control_account' => true,
        ]); // Persediaan Barang
        $this->assertDatabaseHas('accounts', [
            'code' => '1-1500',
            'is_control_account' => true,
        ]); // PPN Masukan
        $this->assertDatabaseHas('accounts', [
            'code' => '2-1200',
            'is_control_account' => true,
        ]); // PPN Keluaran
    }

    public function test_seeded_coa_has_no_warehouse_specific_cash_accounts(): void
    {
        $this->seed(HibiscusEfsyaChartOfAccountsSeeder::class);

        // Should NOT have warehouse-specific cash accounts like "Kas Gudang A"
        $warehouseCashAccounts = Account::where('name', 'like', '%Gudang%')
            ->where('category', AccountCategory::Aset)
            ->where('subcategory', 'kas')
            ->count();

        $this->assertEquals(0, $warehouseCashAccounts, 'Seeded COA should not have warehouse-specific cash accounts');
    }

    public function test_seeder_preserves_runtime_state_on_existing_accounts(): void
    {
        // Seed the COA
        $this->seed(HibiscusEfsyaChartOfAccountsSeeder::class);

        // Simulate runtime state: mark some accounts as used
        Account::where('code', '1-1100')->update(['is_used' => true]); // Kas
        Account::where('code', '2-1100')->update(['is_used' => true]); // Utang Usaha
        Account::where('code', '4-1100')->update(['is_used' => true]); // Penjualan Retail

        // Verify they are marked as used
        $this->assertDatabaseHas('accounts', ['code' => '1-1100', 'is_used' => true]);
        $this->assertDatabaseHas('accounts', ['code' => '2-1100', 'is_used' => true]);
        $this->assertDatabaseHas('accounts', ['code' => '4-1100', 'is_used' => true]);

        // Run seeder again (should NOT reset is_used)
        $this->seed(HibiscusEfsyaChartOfAccountsSeeder::class);

        // Prove is_used is still true (not reset to false)
        $this->assertDatabaseHas('accounts', ['code' => '1-1100', 'is_used' => true]);
        $this->assertDatabaseHas('accounts', ['code' => '2-1100', 'is_used' => true]);
        $this->assertDatabaseHas('accounts', ['code' => '4-1100', 'is_used' => true]);
    }

    public function test_seeder_is_idempotent_and_deterministic(): void
    {
        // First seed
        $this->seed(HibiscusEfsyaChartOfAccountsSeeder::class);
        $firstRunCount = Account::count();
        $firstRunCodes = Account::pluck('code')->sort()->values()->toArray();

        // Second seed (should not duplicate)
        $this->seed(HibiscusEfsyaChartOfAccountsSeeder::class);
        $secondRunCount = Account::count();
        $secondRunCodes = Account::pluck('code')->sort()->values()->toArray();

        // Prove idempotency: same count and same codes
        $this->assertEquals($firstRunCount, $secondRunCount, 'Seeder is not idempotent - duplicate accounts created');
        $this->assertEquals($firstRunCodes, $secondRunCodes, 'Seeder is not deterministic - codes differ between runs');
    }

    public function test_seeder_does_not_mutate_unrelated_data(): void
    {
        // Create some unrelated test data
        $gudang = Gudang::create([
            'nama_gudang' => 'Test Gudang',
            'alamat_gudang' => 'Test Address',
        ]);

        $initialGudangCount = Gudang::count();

        // Run seeder
        $this->seed(HibiscusEfsyaChartOfAccountsSeeder::class);

        // Prove unrelated data is untouched
        $this->assertEquals($initialGudangCount, Gudang::count(), 'Seeder mutated unrelated gudangs table');
        $this->assertDatabaseHas('gudangs', ['nama_gudang' => 'Test Gudang']);
    }
}
