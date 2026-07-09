<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Non-negative CHECK constraints for stock and money columns.
     * Prevents impossible states: negative stock, negative payment, negative totals.
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

        // Helper function to check if constraint exists
        $constraintExists = function ($tableName, $constraintName) {
            $result = DB::select("
                SELECT CONSTRAINT_NAME 
                FROM INFORMATION_SCHEMA.CHECK_CONSTRAINTS 
                WHERE TABLE_NAME = ? AND CONSTRAINT_NAME = ?
            ", [$tableName, $constraintName]);
            return count($result) > 0;
        };

        // ── gudang_produk: stock columns must be >= 0 ──────────────────
        if (!$constraintExists('gudang_produk', 'gudang_produk_stok_check')) {
            DB::statement('ALTER TABLE gudang_produk ADD CONSTRAINT gudang_produk_stok_check CHECK (stok >= 0)');
        }
        if (!$constraintExists('gudang_produk', 'gudang_produk_stok_penjualan_check')) {
            DB::statement('ALTER TABLE gudang_produk ADD CONSTRAINT gudang_produk_stok_penjualan_check CHECK (stok_penjualan >= 0)');
        }
        if (!$constraintExists('gudang_produk', 'gudang_produk_stok_gratis_check')) {
            DB::statement('ALTER TABLE gudang_produk ADD CONSTRAINT gudang_produk_stok_gratis_check CHECK (stok_gratis >= 0)');
        }
        if (!$constraintExists('gudang_produk', 'gudang_produk_stok_sample_check')) {
            DB::statement('ALTER TABLE gudang_produk ADD CONSTRAINT gudang_produk_stok_sample_check CHECK (stok_sample >= 0)');
        }

        // ── pembayarans: jumlah_bayar must be >= 0 ─────────────────────
        if (!$constraintExists('pembayarans', 'pembayarans_jumlah_bayar_check')) {
            DB::statement('ALTER TABLE pembayarans ADD CONSTRAINT pembayarans_jumlah_bayar_check CHECK (jumlah_bayar >= 0)');
        }

        // ── penjualans: grand_total must be >= 0 ───────────────────────
        if (!$constraintExists('penjualans', 'penjualans_grand_total_check')) {
            DB::statement('ALTER TABLE penjualans ADD CONSTRAINT penjualans_grand_total_check CHECK (grand_total >= 0)');
        }

        // ── pembelians: grand_total must be >= 0 ───────────────────────
        if (!$constraintExists('pembelians', 'pembelians_grand_total_check')) {
            DB::statement('ALTER TABLE pembelians ADD CONSTRAINT pembelians_grand_total_check CHECK (grand_total >= 0)');
        }

        // ── penjualan_items: jumlah_baris must be >= 0 ─────────────────
        if (!$constraintExists('penjualan_items', 'penjualan_items_jumlah_baris_check')) {
            DB::statement('ALTER TABLE penjualan_items ADD CONSTRAINT penjualan_items_jumlah_baris_check CHECK (jumlah_baris >= 0)');
        }

        // ── pembelian_items: jumlah_baris must be >= 0 ─────────────────
        if (!$constraintExists('pembelian_items', 'pembelian_items_jumlah_baris_check')) {
            DB::statement('ALTER TABLE pembelian_items ADD CONSTRAINT pembelian_items_jumlah_baris_check CHECK (jumlah_baris >= 0)');
        }
    }

    public function down(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'sqlite') {
            return;
        }

        $constraints = [
            'gudang_produk' => [
                'gudang_produk_stok_check',
                'gudang_produk_stok_penjualan_check',
                'gudang_produk_stok_gratis_check',
                'gudang_produk_stok_sample_check',
            ],
            'pembayarans' => ['pembayarans_jumlah_bayar_check'],
            'penjualans' => ['penjualans_grand_total_check'],
            'pembelians' => ['pembelians_grand_total_check'],
            'penjualan_items' => ['penjualan_items_jumlah_baris_check'],
            'pembelian_items' => ['pembelian_items_jumlah_baris_check'],
        ];

        foreach ($constraints as $table => $names) {
            foreach ($names as $name) {
                DB::statement("ALTER TABLE {$table} DROP CONSTRAINT {$name}");
            }
        }
    }
};
