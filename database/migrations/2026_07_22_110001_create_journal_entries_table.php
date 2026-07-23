<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('journal_entries', function (Blueprint $table): void {
            $table->id();
            $table->string('source_type', 100);
            $table->unsignedBigInteger('source_id');
            $table->string('journal_type', 50);
            $table->unsignedInteger('source_version');
            $table->date('journal_date')->index();
            $table->string('journal_number', 50)->nullable()->unique();
            $table->unsignedBigInteger('posting_sequence')->default(0)->index();
            $table->foreignId('gudang_id')->nullable()->constrained('gudangs')->restrictOnDelete();
            $table->string('contact_type', 100)->nullable();
            $table->unsignedBigInteger('contact_id')->nullable();
            $table->string('description');
            $table->string('status', 20)->default('draft')->index();
            $table->decimal('total_debit', 20, 2);
            $table->decimal('total_credit', 20, 2);
            $table->timestamps();

            $table->unique(['source_type', 'source_id', 'journal_type', 'source_version'], 'journal_entries_source_identity_unique');
            $table->index(['gudang_id', 'journal_date'], 'journal_entries_gudang_date_index');
            $table->index(['contact_type', 'contact_id'], 'journal_entries_contact_index');
            $table->index(['status', 'journal_date', 'posting_sequence'], 'journal_entries_status_date_sequence_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('journal_entries');
    }
};
