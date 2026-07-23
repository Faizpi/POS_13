<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accounts', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('name');
            $table->foreignId('parent_id')->nullable()->constrained('accounts')->nullOnDelete();
            $table->string('category')->index(); // AccountCategory enum value
            $table->string('subcategory')->nullable();
            $table->string('normal_balance')->index(); // NormalBalance enum value
            $table->string('statement_classification')->index(); // StatementClassification enum value
            $table->string('cash_flow_category')->nullable();
            $table->string('cash_flow_line')->nullable();
            $table->boolean('is_postable')->default(true);
            $table->boolean('is_control_account')->default(false);
            $table->boolean('is_system')->default(false);
            $table->boolean('is_active')->default(true);
            $table->boolean('is_used')->default(false);
            $table->unsignedInteger('display_order')->default(0);
            $table->timestamps();

            // Composite indexes for common queries
            $table->index(['category', 'is_active']);
            $table->index(['parent_id', 'display_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accounts');
    }
};
