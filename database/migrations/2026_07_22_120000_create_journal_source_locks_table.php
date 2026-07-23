<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('journal_source_locks', function (Blueprint $table): void {
            $table->id();
            $table->string('source_type', 100);
            $table->unsignedBigInteger('source_id');
            $table->string('journal_type', 50);
            $table->unsignedInteger('source_version');
            $table->timestamp('locked_at')->nullable();
            $table->timestamps();

            $table->unique(
                ['source_type', 'source_id', 'journal_type', 'source_version'],
                'journal_source_locks_identity_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('journal_source_locks');
    }
};
