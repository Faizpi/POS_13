<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cash_transfers', function (Blueprint $table): void {
            $table->id();
            $table->string('transfer_number', 50)->unique();
            $table->foreignId('source_cash_bank_account_id')->constrained('cash_bank_accounts')->restrictOnDelete();
            $table->foreignId('destination_cash_bank_account_id')->constrained('cash_bank_accounts')->restrictOnDelete();
            $table->string('mode', 20);
            $table->string('status', 20);
            $table->decimal('amount', 20, 2);
            $table->text('memo')->nullable();
            $table->foreignId('initiated_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('posted_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index(['source_cash_bank_account_id', 'status']);
            $table->index(['destination_cash_bank_account_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cash_transfers');
    }
};
