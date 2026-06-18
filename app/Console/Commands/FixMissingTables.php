<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

#[Signature('fix-tables')]
#[Description('Create missing tables that migrations should have created (useful after SQL import)')]
class FixMissingTables extends Command
{
    /**
     * Maps table names to the migration files that create them.
     */
    private array $tableToMigration = [
        // Core tables
        'gudangs'       => '0001_01_01_000001_create_gudangs_table.php',
        'produks'       => '0001_01_01_000002_create_produks_table.php',
        'users'         => '0001_01_01_000003_create_users_table.php',
        'password_resets' => '0001_01_01_000003_create_users_table.php',
        'sessions'      => '0001_01_01_000003_create_users_table.php',
        'admin_gudang'  => '0001_01_01_000004_create_gudang_pivots_and_stok.php',
        'spectator_gudang' => '0001_01_01_000004_create_gudang_pivots_and_stok.php',
        'gudang_produk' => '0001_01_01_000004_create_gudang_pivots_and_stok.php',
        'stok_logs'     => '0001_01_01_000004_create_gudang_pivots_and_stok.php',
        'kontaks'       => '0001_01_01_000005_create_kontaks_table.php',
        'penjualans'    => '0001_01_01_000006_create_penjualans_table.php',
        'penjualan_items' => '0001_01_01_000006_create_penjualans_table.php',
        'pembelians'    => '0001_01_01_000007_create_pembelians_table.php',
        'pembelian_items' => '0001_01_01_000007_create_pembelians_table.php',
        'biayas'        => '0001_01_01_000008_create_biayas_table.php',
        'biaya_items'   => '0001_01_01_000008_create_biayas_table.php',
        'kunjungans'    => '0001_01_01_000009_create_kunjungans_table.php',
        'kunjungan_items' => '0001_01_01_000009_create_kunjungans_table.php',
        'pembayarans'   => '0001_01_01_000010_create_pembayarans_table.php',
        'penerimaan_barangs' => '0001_01_01_000011_create_penerimaan_barangs_table.php',
        'penerimaan_barang_items' => '0001_01_01_000011_create_penerimaan_barangs_table.php',
        'personal_access_tokens' => '0001_01_01_000012_create_personal_access_tokens_table.php',
        'cache'         => '0001_01_01_000012_create_personal_access_tokens_table.php',
        'cache_locks'   => '0001_01_01_000012_create_personal_access_tokens_table.php',
        'jobs'          => '0001_01_01_000012_create_personal_access_tokens_table.php',
        'job_batches'   => '0001_01_01_000012_create_personal_access_tokens_table.php',
        'failed_jobs'   => '0001_01_01_000012_create_personal_access_tokens_table.php',
        // Later migrations
        'tutup_buku'    => '2026_06_12_193338_create_tutup_buku_table.php',
        'archive_penjualans' => '2026_06_12_193338_create_archive_tables.php',
        'archive_penjualan_items' => '2026_06_12_193338_create_archive_tables.php',
        'archive_pembelians' => '2026_06_12_193338_create_archive_tables.php',
        'archive_pembelian_items' => '2026_06_12_193338_create_archive_tables.php',
        'archive_biayas' => '2026_06_12_193338_create_archive_tables.php',
        'archive_biaya_items' => '2026_06_12_193338_create_archive_tables.php',
        'archive_kunjungans' => '2026_06_12_193338_create_archive_tables.php',
        'archive_kunjungan_items' => '2026_06_12_193338_create_archive_tables.php',
        'archive_pembayarans' => '2026_06_12_193338_create_archive_tables.php',
        'archive_penerimaan_barangs' => '2026_06_12_193338_create_archive_tables.php',
        'archive_penerimaan_barang_items' => '2026_06_12_193338_create_archive_tables.php',
        'notifications' => '2026_06_12_200146_create_notifications_table.php',
        'stock_opnames' => '2026_06_15_120443_fix_2026_06_15_110705_idempotent.php',
        'stock_opname_items' => '2026_06_15_120443_fix_2026_06_15_110705_idempotent.php',
    ];

    public function handle()
    {
        $missing = [];

        foreach (array_keys($this->tableToMigration) as $table) {
            if (!Schema::hasTable($table)) {
                $missing[] = $table;
            }
        }

        if (empty($missing)) {
            $this->info('All ' . count($this->tableToMigration) . ' expected tables exist. No fix needed.');
            return 0;
        }

        // Collect which migration files need to run
        $migrationsToRun = [];
        foreach ($missing as $table) {
            $migrationFile = $this->tableToMigration[$table] ?? null;
            if ($migrationFile) {
                $migrationsToRun[$migrationFile] = true;
            }
        }

        $this->info('Found ' . count($missing) . ' missing table(s).');
        $this->info('Will run ' . count($migrationsToRun) . ' migration file(s):');
        foreach (array_keys($migrationsToRun) as $f) {
            $this->line("  - {$f}");
        }
        $this->newLine();
        $this->info('Running migrations...');
        $this->newLine();

        $migrationPath = database_path('migrations');
        $successCount = 0;
        $failCount = 0;

        foreach (array_keys($migrationsToRun) as $migrationFile) {
            $fullPath = $migrationPath . '/' . $migrationFile;

            if (!file_exists($fullPath)) {
                $this->line("  ✗ {$migrationFile}: file not found");
                $failCount++;
                continue;
            }

            try {
                $migration = require $fullPath;

                // Ensure idempotent by wrapping in try/catch
                // The migrations themselves may create multiple tables,
                // some of which may already exist
                $migration->up();

                $tablesCreatedByThis = array_keys(array_filter($this->tableToMigration, fn($f) => $f === $migrationFile));
                $actuallyCreated = array_filter($tablesCreatedByThis, fn($t) => Schema::hasTable($t));

                foreach ($actuallyCreated as $t) {
                    $this->line("  ✓ {$t}");
                    $successCount++;
                }

                $stillMissing = array_filter($tablesCreatedByThis, fn($t) => !Schema::hasTable($t) && in_array($t, $missing));
                foreach ($stillMissing as $t) {
                    $this->line("  ✗ {$t}: table not created (migration may have failed silently)");
                    $failCount++;
                }
            } catch (\Throwable $e) {
                // Check which tables were actually created despite the error
                $tablesCreatedByThis = array_keys(array_filter($this->tableToMigration, fn($f) => $f === $migrationFile));
                $created = array_filter($tablesCreatedByThis, fn($t) => Schema::hasTable($t));

                if (!empty($created)) {
                    foreach ($created as $t) {
                        if (in_array($t, $missing)) {
                            $this->line("  ✓ {$t}");
                            $successCount++;
                        }
                    }
                }

                $stillMissing = array_filter($tablesCreatedByThis, fn($t) => !Schema::hasTable($t) && in_array($t, $missing));
                foreach ($stillMissing as $t) {
                    $this->line("  ✗ {$t}: " . $e->getMessage());
                    $failCount++;
                }
            }
        }

        $this->newLine();
        $this->info("Done. Created: {$successCount}, Failed: {$failCount}");

        if ($failCount > 0) {
            $this->newLine();
            $this->warn('Some tables could not be created. Check the errors above.');
        }

        return $failCount > 0 ? 1 : 0;
    }
}
