<?php

declare(strict_types=1);

namespace Tests\Feature\Accounting;

use App\Models\Account;
use Database\Factories\AccountFactory;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\HibiscusEfsyaChartOfAccountsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Baseline characterization test for existing migration/seed behavior.
 *
 * This test characterizes the state BEFORE implementing Todo 5 (COA persistence).
 * It documents what exists and what doesn't exist yet.
 */
class BaselineSeedMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_baseline_existing_tables_exist_after_fresh_migration(): void
    {
        // These tables should exist from existing migrations
        $this->assertTrue(Schema::hasTable('users'));
        $this->assertTrue(Schema::hasTable('gudangs'));
        $this->assertTrue(Schema::hasTable('produks'));
        $this->assertTrue(Schema::hasTable('kontaks'));
        $this->assertTrue(Schema::hasTable('penjualans'));
        $this->assertTrue(Schema::hasTable('pembelians'));
        $this->assertTrue(Schema::hasTable('pembayarans'));
        $this->assertTrue(Schema::hasTable('penerimaan_barangs'));
        $this->assertTrue(Schema::hasTable('biayas'));
        $this->assertTrue(Schema::hasTable('kunjungans'));
    }

    public function test_baseline_accounts_table_exists_after_todo5(): void
    {
        // accounts table SHOULD exist after Todo 5 implementation
        $this->assertTrue(Schema::hasTable('accounts'));
    }

    public function test_baseline_database_seeder_runs_without_error(): void
    {
        // DatabaseSeeder should run successfully with existing fixture data
        $this->seed(DatabaseSeeder::class);

        // Verify some expected fixture data exists
        $this->assertDatabaseHas('gudangs', ['nama_gudang' => 'Gudang A']);
        $this->assertDatabaseHas('gudangs', ['nama_gudang' => 'Gudang B']);
        $this->assertDatabaseHas('users', ['email' => 'superadmin@hibiscusefsya.com']);
        $this->assertDatabaseHas('users', ['email' => 'admin@hibiscusefsya.com']);

        // Verify accounts were seeded
        $this->assertDatabaseHas('accounts', ['code' => '1-0000']);
        $this->assertDatabaseHas('accounts', ['code' => '1-1100']);
    }

    public function test_baseline_account_model_exists_after_todo5(): void
    {
        // Account model SHOULD exist after Todo 5 implementation
        $this->assertTrue(class_exists(Account::class));
    }

    public function test_baseline_account_factory_exists_after_todo5(): void
    {
        // AccountFactory SHOULD exist after Todo 5 implementation
        $this->assertTrue(class_exists(AccountFactory::class));
    }

    public function test_baseline_hibiscus_chart_of_accounts_seeder_exists_after_todo5(): void
    {
        // HibiscusEfsyaChartOfAccountsSeeder SHOULD exist after Todo 5 implementation
        $this->assertTrue(class_exists(HibiscusEfsyaChartOfAccountsSeeder::class));
    }
}
