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
        Schema::table('pembelians', function (Blueprint $table) {
            if (!Schema::hasColumn('pembelians', 'kontak_id')) {
                $table->foreignId('kontak_id')
                    ->nullable()
                    ->constrained('kontaks')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('pembelians', function (Blueprint $table) {
            if (Schema::hasColumn('pembelians', 'kontak_id')) {
                $table->dropForeign(['kontak_id']);
                $table->dropColumn('kontak_id');
            }
        });
    }
};
