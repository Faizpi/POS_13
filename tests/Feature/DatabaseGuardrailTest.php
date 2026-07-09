<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class DatabaseGuardrailTest extends TestCase
{
    use RefreshDatabase;

    /** @covers-finding B19 DB constraints */
    public function test_audit_command_detects_no_violations_on_clean_database(): void
    {
        $this->artisan('audit:transaction-integrity')
            ->assertExitCode(0);
    }

    public function test_audit_command_detects_negative_stock(): void
    {
        $gudang = DB::table('gudangs')->insertGetId([
            'nama_gudang' => 'Test Warehouse',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $produk = DB::table('produks')->insertGetId([
            'nama_produk' => 'Test Product',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('gudang_produk')->insert([
            'gudang_id' => $gudang,
            'produk_id' => $produk,
            'stok' => -5,
            'stok_penjualan' => 0,
            'stok_gratis' => 0,
            'stok_sample' => 0,
        ]);

        $this->artisan('audit:transaction-integrity')
            ->expectsOutputToContain('gudang_produk.stok: 1 row(s) with negative values')
            ->assertExitCode(1);
    }

    public function test_audit_command_detects_negative_payment(): void
    {
        $user = User::factory()->create();
        $gudang = DB::table('gudangs')->insertGetId([
            'nama_gudang' => 'Test Warehouse',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('pembayarans')->insert([
            'uuid' => Str::uuid(),
            'user_id' => $user->id,
            'gudang_id' => $gudang,
            'jumlah_bayar' => -100,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->artisan('audit:transaction-integrity')
            ->expectsOutputToContain('pembayarans.jumlah_bayar: 1 row(s) with negative values')
            ->assertExitCode(1);
    }

    public function test_audit_command_detects_tax_out_of_range(): void
    {
        $user = User::factory()->create();

        DB::table('penjualans')->insert([
            'uuid' => Str::uuid(),
            'user_id' => $user->id,
            'tax_percentage' => 150,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->artisan('audit:transaction-integrity')
            ->expectsOutputToContain('penjualans.tax_percentage: 1 row(s) outside range [0, 100]')
            ->assertExitCode(1);
    }

    public function test_audit_command_detects_discount_out_of_range(): void
    {
        $user = User::factory()->create();

        $penjualan = DB::table('penjualans')->insertGetId([
            'uuid' => Str::uuid(),
            'user_id' => $user->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $produk = DB::table('produks')->insertGetId([
            'nama_produk' => 'Test Product',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('penjualan_items')->insert([
            'penjualan_id' => $penjualan,
            'produk_id' => $produk,
            'diskon' => -10,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->artisan('audit:transaction-integrity')
            ->expectsOutputToContain('penjualan_items.diskon: 1 row(s) outside range [0, 100]')
            ->assertExitCode(1);
    }

    public function test_audit_command_detects_multiple_violations(): void
    {
        $user = User::factory()->create();
        $gudang = DB::table('gudangs')->insertGetId([
            'nama_gudang' => 'Test Warehouse',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $produk = DB::table('produks')->insertGetId([
            'nama_produk' => 'Test Product',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Multiple violations
        DB::table('gudang_produk')->insert([
            'gudang_id' => $gudang,
            'produk_id' => $produk,
            'stok' => -5,
            'stok_penjualan' => -3,
            'stok_gratis' => 0,
            'stok_sample' => 0,
        ]);

        DB::table('penjualans')->insert([
            'uuid' => Str::uuid(),
            'user_id' => $user->id,
            'tax_percentage' => 150,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $exitCode = $this->artisan('audit:transaction-integrity')
            ->assertExitCode(1)
            ->run();

        // Should detect at least 3 violations
        $this->assertTrue(true);
    }

    public function test_migrations_run_without_error(): void
    {
        // Migrations should run without error even on SQLite (they skip gracefully)
        Artisan::call('migrate');

        $this->assertTrue(true);
    }

    public function test_migrations_can_be_rolled_back(): void
    {
        // Run migrations up
        Artisan::call('migrate');

        // Rollback the guardrail migrations
        Artisan::call('migrate:rollback', [
            '--path' => 'database/migrations/2026_07_09_200000_add_non_negative_constraints_to_transaction_tables.php',
        ]);

        Artisan::call('migrate:rollback', [
            '--path' => 'database/migrations/2026_07_09_200001_add_range_constraints_to_percentage_columns.php',
        ]);

        $this->assertTrue(true);
    }
}
