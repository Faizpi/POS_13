<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Helper function to check if foreign key exists
        $foreignKeyExists = function ($tableName, $columnName) {
            $result = DB::select("
                SELECT CONSTRAINT_NAME 
                FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE 
                WHERE TABLE_NAME = ? AND COLUMN_NAME = ? AND REFERENCED_TABLE_NAME IS NOT NULL
            ", [$tableName, $columnName]);
            return count($result) > 0;
        };

        // Add FK for stok_logs.gudang_produk_id
        if (!$foreignKeyExists('stok_logs', 'gudang_produk_id')) {
            // First, make the column nullable (it might be NOT NULL in old databases)
            Schema::table('stok_logs', function (Blueprint $table) {
                $table->unsignedBigInteger('gudang_produk_id')->nullable()->change();
            });

            // Then, clean up orphaned records (set gudang_produk_id to NULL if it doesn't exist in gudang_produk)
            DB::statement('
                UPDATE stok_logs 
                SET gudang_produk_id = NULL 
                WHERE gudang_produk_id IS NOT NULL 
                AND gudang_produk_id NOT IN (SELECT id FROM gudang_produk)
            ');

            Schema::table('stok_logs', function (Blueprint $table) {
                $table->foreign('gudang_produk_id')
                    ->references('id')
                    ->on('gudang_produk')
                    ->nullOnDelete();
            });
        }

        // Add FK for approver_id in transaction tables
        // Using nullOnDelete to preserve audit history while maintaining referential integrity
        $tables = ['pembelians', 'penjualans', 'pembayarans', 'kunjungans', 'penerimaan_barangs'];

        foreach ($tables as $table) {
            if (Schema::hasColumn($table, 'approver_id') && !$foreignKeyExists($table, 'approver_id')) {
                Schema::table($table, function (Blueprint $table) {
                    $table->foreign('approver_id')
                        ->references('id')
                        ->on('users')
                        ->nullOnDelete();
                });
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('stok_logs', function (Blueprint $table) {
            $table->dropForeign(['gudang_produk_id']);
        });

        $tables = ['pembelians', 'penjualans', 'pembayarans', 'kunjungans', 'penerimaan_barangs'];

        foreach ($tables as $table) {
            if (Schema::hasColumn($table, 'approver_id')) {
                Schema::table($table, function (Blueprint $table) {
                    $table->dropForeign(['approver_id']);
                });
            }
        }
    }
};
