<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('account_mappings', function (Blueprint $table): void {
            $table->id();
            $table->string('mapping_key', 100)->index();
            $table->string('section', 100);
            $table->foreignId('account_id')->constrained()->restrictOnDelete();
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->boolean('is_protected')->default(false);
            $table->foreignId('changed_by')->constrained('users')->restrictOnDelete();
            $table->string('change_reason', 500)->nullable();
            $table->timestamps();

            $table->index(['mapping_key', 'effective_from']);
            $table->index(['mapping_key', 'effective_to']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('account_mappings');
    }
};
