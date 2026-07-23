<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('journal_lines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('journal_entry_id')->constrained()->restrictOnDelete();
            $table->foreignId('account_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('line_sequence');
            $table->foreignId('gudang_id')->nullable()->constrained('gudangs')->restrictOnDelete();
            $table->string('contact_type', 100)->nullable();
            $table->unsignedBigInteger('contact_id')->nullable();
            $table->decimal('debit', 20, 2)->default('0.00');
            $table->decimal('credit', 20, 2)->default('0.00');
            $table->string('description')->nullable();
            $table->timestamps();

            $table->unique(['journal_entry_id', 'line_sequence'], 'journal_lines_entry_sequence_unique');
            $table->index(['account_id', 'journal_entry_id'], 'journal_lines_account_entry_index');
            $table->index(['gudang_id', 'account_id'], 'journal_lines_gudang_account_index');
            $table->index(['contact_type', 'contact_id'], 'journal_lines_contact_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('journal_lines');
    }
};
