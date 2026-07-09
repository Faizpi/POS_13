<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Helper function to check if index exists
        $indexExists = function ($tableName, $indexName) {
            $result = DB::select("
                SELECT INDEX_NAME 
                FROM INFORMATION_SCHEMA.STATISTICS 
                WHERE TABLE_NAME = ? AND INDEX_NAME = ?
            ", [$tableName, $indexName]);
            return count($result) > 0;
        };

        // Add unique indexes on nomor columns (nullable-aware)
        // These tables already have nomor columns, we add unique constraints

        if (!$indexExists('penjualans', 'penjualans_nomor_unique')) {
            Schema::table('penjualans', function (Blueprint $table) {
                $table->unique('nomor', 'penjualans_nomor_unique');
            });
        }

        if (!$indexExists('pembelians', 'pembelians_nomor_unique')) {
            Schema::table('pembelians', function (Blueprint $table) {
                $table->unique('nomor', 'pembelians_nomor_unique');
            });
        }

        if (!$indexExists('pembayarans', 'pembayarans_nomor_unique')) {
            Schema::table('pembayarans', function (Blueprint $table) {
                $table->unique('nomor', 'pembayarans_nomor_unique');
            });
        }

        if (!$indexExists('penerimaan_barangs', 'penerimaan_barangs_nomor_unique')) {
            Schema::table('penerimaan_barangs', function (Blueprint $table) {
                $table->unique('nomor', 'penerimaan_barangs_nomor_unique');
            });
        }

        if (!$indexExists('stock_opnames', 'stock_opnames_nomor_unique')) {
            Schema::table('stock_opnames', function (Blueprint $table) {
                $table->unique('nomor', 'stock_opnames_nomor_unique');
            });
        }

        if (!$indexExists('biayas', 'biayas_nomor_unique')) {
            Schema::table('biayas', function (Blueprint $table) {
                $table->unique('nomor', 'biayas_nomor_unique');
            });
        }

        if (!$indexExists('kunjungans', 'kunjungans_nomor_unique')) {
            Schema::table('kunjungans', function (Blueprint $table) {
                $table->unique('nomor', 'kunjungans_nomor_unique');
            });
        }
    }

    public function down(): void
    {
        Schema::table('penjualans', function (Blueprint $table) {
            $table->dropUnique('penjualans_nomor_unique');
        });

        Schema::table('pembelians', function (Blueprint $table) {
            $table->dropUnique('pembelians_nomor_unique');
        });

        Schema::table('pembayarans', function (Blueprint $table) {
            $table->dropUnique('pembayarans_nomor_unique');
        });

        Schema::table('penerimaan_barangs', function (Blueprint $table) {
            $table->dropUnique('penerimaan_barangs_nomor_unique');
        });

        Schema::table('stock_opnames', function (Blueprint $table) {
            $table->dropUnique('stock_opnames_nomor_unique');
        });

        Schema::table('biayas', function (Blueprint $table) {
            $table->dropUnique('biayas_nomor_unique');
        });

        Schema::table('kunjungans', function (Blueprint $table) {
            $table->dropUnique('kunjungans_nomor_unique');
        });
    }
};
