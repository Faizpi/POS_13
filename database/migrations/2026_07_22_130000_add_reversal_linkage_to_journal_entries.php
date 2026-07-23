<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Plain nullable columns + unique index only.
        // DB-level foreign keys are intentionally omitted: adding them via ALTER
        // forces a full SQLite table rebuild that collides with the journal_lines
        // immutability triggers. Referential integrity is enforced in
        // JournalReversalService, and the unique index guarantees one reversal per original.
        Schema::table('journal_entries', function (Blueprint $table): void {
            $table->unsignedBigInteger('original_journal_entry_id')->nullable()->after('id');
            $table->string('reversal_reason')->nullable()->after('description');
            $table->unsignedBigInteger('reversed_by')->nullable()->after('reversal_reason');
        });

        Schema::table('journal_entries', function (Blueprint $table): void {
            $table->unique('original_journal_entry_id', 'journal_entries_original_reversal_unique');
            $table->index('reversed_by', 'journal_entries_reversed_by_index');
        });
    }

    public function down(): void
    {
        Schema::table('journal_entries', function (Blueprint $table): void {
            $table->dropUnique('journal_entries_original_reversal_unique');
            $table->dropIndex('journal_entries_reversed_by_index');
            $table->dropColumn(['original_journal_entry_id', 'reversal_reason', 'reversed_by']);
        });
    }
};
