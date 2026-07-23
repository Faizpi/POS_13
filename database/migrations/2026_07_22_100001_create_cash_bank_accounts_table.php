<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cash_bank_accounts', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('type', 20)->index();
            $table->foreignId('account_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignId('gudang_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('bank_name')->nullable();
            $table->string('bank_account_number')->nullable();
            $table->string('bank_account_holder')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();

            $table->index(['gudang_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cash_bank_accounts');
    }
};
