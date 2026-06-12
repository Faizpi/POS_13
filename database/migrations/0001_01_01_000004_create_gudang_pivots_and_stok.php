<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admin_gudang', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('gudang_id')->constrained('gudangs')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['user_id', 'gudang_id']);
        });

        Schema::create('spectator_gudang', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('gudang_id')->constrained('gudangs')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['user_id', 'gudang_id']);
        });

        Schema::create('gudang_produk', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gudang_id')->constrained('gudangs')->cascadeOnDelete();
            $table->foreignId('produk_id')->constrained('produks')->cascadeOnDelete();
            $table->integer('stok')->default(0);
            $table->integer('stok_penjualan')->default(0);
            $table->integer('stok_gratis')->default(0);
            $table->integer('stok_sample')->default(0);
            $table->unique(['gudang_id', 'produk_id']);
            $table->index(['gudang_id', 'stok']);
        });

        Schema::create('stok_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('gudang_produk_id')->nullable();
            $table->foreignId('produk_id')->constrained('produks');
            $table->foreignId('gudang_id')->constrained('gudangs');
            $table->foreignId('user_id')->constrained();
            $table->string('produk_nama');
            $table->string('gudang_nama');
            $table->string('user_nama');
            $table->integer('stok_sebelum');
            $table->integer('stok_sesudah');
            $table->integer('selisih');
            $table->text('keterangan')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stok_logs');
        Schema::dropIfExists('gudang_produk');
        Schema::dropIfExists('spectator_gudang');
        Schema::dropIfExists('admin_gudang');
    }
};
