<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kontaks', function (Blueprint $table) {
            $table->id();
            $table->string('kode_kontak', 50)->nullable();
            $table->string('nama')->index();
            $table->string('email')->nullable()->index();
            $table->string('no_telp', 20)->nullable();
            $table->string('pin', 6)->nullable();
            $table->text('alamat')->nullable();
            $table->decimal('diskon_persen', 5, 2)->default(0);
            $table->foreignId('gudang_id')->nullable()->constrained('gudangs')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kontaks');
    }
};
