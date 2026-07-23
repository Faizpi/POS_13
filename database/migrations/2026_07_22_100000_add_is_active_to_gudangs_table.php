<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gudangs', function (Blueprint $table): void {
            $table->boolean('is_active')->default(true)->after('alamat_gudang');
        });
    }

    public function down(): void
    {
        Schema::table('gudangs', function (Blueprint $table): void {
            $table->dropColumn('is_active');
        });
    }
};
