<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kunjungans', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->constrained();
            $table->unsignedBigInteger('approver_id')->nullable();
            $table->foreignId('gudang_id')->nullable()->constrained('gudangs');
            $table->foreignId('kontak_id')->nullable()->constrained('kontaks');
            $table->integer('no_urut_harian')->nullable();
            $table->string('nomor')->nullable()->index();
            $table->string('sales_nama')->nullable();
            $table->string('sales_no_telepon')->nullable();
            $table->text('sales_alamat')->nullable();
            $table->date('tgl_kunjungan')->nullable();
            $table->string('tujuan')->nullable();
            $table->string('koordinat')->nullable();
            $table->text('memo')->nullable();
            $table->string('lampiran_path')->nullable();
            $table->json('lampiran_paths')->nullable();
            $table->string('status')->default('Pending');
            $table->timestamps();
        });

        Schema::create('kunjungan_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kunjungan_id')->constrained('kunjungans')->cascadeOnDelete();
            $table->foreignId('produk_id')->constrained('produks');
            $table->integer('jumlah')->default(1);
            $table->string('batch_number')->nullable();
            $table->date('expired_date')->nullable();
            $table->text('keterangan')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kunjungan_items');
        Schema::dropIfExists('kunjungans');
    }
};
