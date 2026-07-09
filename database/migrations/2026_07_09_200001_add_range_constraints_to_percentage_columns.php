<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Range CHECK constraints for percentage columns.
     * Prevents impossible states: tax or discount outside 0-100 range.
     *
     * Note: CHECK constraints via ALTER TABLE are only supported on MySQL/MariaDB.
     * SQLite requires CHECK at table creation time, so we skip gracefully on SQLite.
     * The AuditTransactionIntegrity command provides application-level enforcement.
     */
    public function up(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'sqlite') {
            return;
        }

        // ── penjualans: tax_percentage BETWEEN 0 AND 100 ───────────────
        DB::statement('ALTER TABLE penjualans ADD CONSTRAINT penjualans_tax_percentage_range_check CHECK (tax_percentage BETWEEN 0 AND 100)');

        // ── pembelians: tax_percentage BETWEEN 0 AND 100 ───────────────
        DB::statement('ALTER TABLE pembelians ADD CONSTRAINT pembelians_tax_percentage_range_check CHECK (tax_percentage BETWEEN 0 AND 100)');

        // ── penjualan_items: diskon BETWEEN 0 AND 100 ──────────────────
        DB::statement('ALTER TABLE penjualan_items ADD CONSTRAINT penjualan_items_diskon_range_check CHECK (diskon BETWEEN 0 AND 100)');

        // ── pembelian_items: diskon BETWEEN 0 AND 100 ──────────────────
        DB::statement('ALTER TABLE pembelian_items ADD CONSTRAINT pembelian_items_diskon_range_check CHECK (diskon BETWEEN 0 AND 100)');
    }

    public function down(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'sqlite') {
            return;
        }

        $constraints = [
            'penjualans' => ['penjualans_tax_percentage_range_check'],
            'pembelians' => ['pembelians_tax_percentage_range_check'],
            'penjualan_items' => ['penjualan_items_diskon_range_check'],
            'pembelian_items' => ['pembelian_items_diskon_range_check'],
        ];

        foreach ($constraints as $table => $names) {
            foreach ($names as $name) {
                DB::statement("ALTER TABLE {$table} DROP CONSTRAINT {$name}");
            }
        }
    }
};
