<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('penerimaan_barangs', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->constrained();
            $table->unsignedBigInteger('approver_id')->nullable();
            $table->unsignedBigInteger('gudang_id')->index();
            $table->unsignedBigInteger('pembelian_id')->index();
            $table->integer('no_urut_harian')->nullable();
            $table->string('nomor')->nullable();
            $table->date('tgl_penerimaan')->nullable()->index();
            $table->string('no_surat_jalan', 100)->nullable();
            $table->text('lampiran_paths')->nullable();
            $table->text('keterangan')->nullable();
            $table->string('status')->default('Pending')->index();
            $table->timestamps();

            $table->foreign('gudang_id', 'penerimaan_barangs_gudang_fk')->references('id')->on('gudangs');
            $table->foreign('pembelian_id', 'penerimaan_barangs_pembelian_fk')->references('id')->on('pembelians');
        });

        Schema::create('penerimaan_barang_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('penerimaan_barang_id')->constrained('penerimaan_barangs')->cascadeOnDelete();
            $table->foreignId('produk_id')->constrained('produks');
            $table->integer('qty_diterima')->default(0);
            $table->integer('qty_reject')->default(0);
            $table->string('tipe_stok')->default('penjualan');
            $table->string('batch_number', 100)->nullable();
            $table->date('expired_date')->nullable();
            $table->text('keterangan')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('penerimaan_barang_items');
        Schema::dropIfExists('penerimaan_barangs');
    }
};
