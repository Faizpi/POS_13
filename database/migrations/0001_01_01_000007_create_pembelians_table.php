<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pembelians', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->constrained();
            $table->unsignedBigInteger('approver_id')->nullable()->index();
            $table->foreignId('gudang_id')->nullable()->constrained('gudangs');
            $table->integer('no_urut_harian')->nullable();
            $table->string('nomor')->nullable()->index();
            $table->string('staf_penyetuju')->nullable();
            $table->string('email_penyetuju')->nullable();
            $table->date('tgl_transaksi')->nullable()->index();
            $table->date('tgl_jatuh_tempo')->nullable()->index();
            $table->string('syarat_pembayaran')->nullable();
            $table->string('urgensi')->nullable()->index();
            $table->string('tahun_anggaran')->nullable();
            $table->string('tag')->nullable();
            $table->string('koordinat')->nullable();
            $table->text('memo')->nullable();
            $table->string('lampiran_path')->nullable();
            $table->json('lampiran_paths')->nullable();
            $table->string('status')->default('Pending')->index();
            $table->decimal('diskon_akhir', 15, 2)->nullable();
            $table->decimal('tax_percentage', 5, 2)->nullable();
            $table->decimal('grand_total', 15, 2)->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index(['approver_id', 'status']);
            $table->index('created_at');
        });

        Schema::create('pembelian_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pembelian_id')->constrained('pembelians')->cascadeOnDelete();
            $table->unsignedBigInteger('produk_id')->index();
            $table->string('deskripsi')->nullable();
            $table->decimal('kuantitas', 10, 2)->default(0);
            $table->string('unit')->nullable();
            $table->decimal('harga_satuan', 15, 2)->default(0);
            $table->decimal('diskon', 5, 2)->default(0);
            $table->decimal('jumlah_baris', 15, 2)->default(0);
            $table->timestamps();

            $table->foreign('produk_id', 'pembelian_items_produk_id_fk')->references('id')->on('produks');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pembelian_items');
        Schema::dropIfExists('pembelians');
    }
};
