<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Make penjualan_id nullable so hutang payments (which only have
        // pembelian_id) can be inserted on a fresh migration.
        // Idempotent: checks column metadata before altering.
        $columns = Schema::getColumns('pembayarans');
        $penjualanCol = collect($columns)->firstWhere('name', 'penjualan_id');

        if ($penjualanCol && $penjualanCol['nullable'] === false) {
            // Drop existing FK first, then alter column, then recreate FK with nullOnDelete
            $foreignKeys = Schema::getForeignKeys('pembayarans');
            $fkName = collect($foreignKeys)->first(function ($fk) {
                return in_array('penjualan_id', $fk['columns'], true);
            });

            if ($fkName) {
                Schema::table('pembayarans', function (Blueprint $table) use ($fkName) {
                    $table->dropForeign($fkName['name']);
                });
            }

            Schema::table('pembayarans', function (Blueprint $table) {
                $table->unsignedBigInteger('penjualan_id')->nullable()->change();
            });

            Schema::table('pembayarans', function (Blueprint $table) {
                $table->foreign('penjualan_id', 'pembayarans_penjualan_fk')
                    ->references('id')->on('penjualans')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        // Reverting to NOT NULL would corrupt hutang rows because valid hutang
        // payments intentionally have pembelian_id and NULL penjualan_id.
        // Fail before schema changes instead of assigning fake penjualan_id data.
        if (DB::table('pembayarans')->whereNull('penjualan_id')->exists()) {
            throw new RuntimeException(
                'Cannot rollback pembayarans.penjualan_id to NOT NULL while hutang/nullable payment rows exist.'
            );
        }

        $foreignKeys = Schema::getForeignKeys('pembayarans');
        $fkName = collect($foreignKeys)->first(function ($fk) {
            return in_array('penjualan_id', $fk['columns'], true);
        });

        if ($fkName) {
            Schema::table('pembayarans', function (Blueprint $table) use ($fkName) {
                $table->dropForeign($fkName['name']);
            });
        }

        Schema::table('pembayarans', function (Blueprint $table) {
            $table->unsignedBigInteger('penjualan_id')->nullable(false)->change();
        });

        Schema::table('pembayarans', function (Blueprint $table) {
            $table->foreign('penjualan_id', 'pembayarans_penjualan_fk')
                ->references('id')->on('penjualans');
        });
    }
};
