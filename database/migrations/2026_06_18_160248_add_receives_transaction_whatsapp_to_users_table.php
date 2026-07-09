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
        if (! Schema::hasColumn('users', 'receives_transaction_whatsapp')) {
            Schema::table('users', function (Blueprint $table) {
                $table->boolean('receives_transaction_whatsapp')->default(true)->after('receives_transaction_email');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('users', 'receives_transaction_whatsapp')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('receives_transaction_whatsapp');
            });
        }
    }
};
