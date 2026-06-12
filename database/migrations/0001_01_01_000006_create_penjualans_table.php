<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('penjualans', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->constrained();
            $table->unsignedBigInteger('approver_id')->nullable()->index();
            $table->foreignId('gudang_id')->nullable()->constrained('gudangs');
            $table->integer('no_urut_harian')->nullable();
            $table->string('nomor')->nullable()->index();
            $table->string('tipe_harga')->default('retail');
            $table->string('pelanggan')->nullable();
            $table->string('no_telepon')->nullable();
            $table->text('alamat_penagihan')->nullable();
            $table->date('tgl_transaksi')->nullable()->index();
            $table->date('tgl_jatuh_tempo')->nullable()->index();
            $table->string('syarat_pembayaran')->nullable();
            $table->string('no_referensi')->nullable();
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

            $table->index('user_id');
            $table->index('gudang_id');
            $table->index('created_at');
            $table->index(['user_id', 'status']);
            $table->index(['approver_id', 'status']);
        });

        Schema::create('penjualan_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('penjualan_id')->constrained('penjualans')->cascadeOnDelete();
            $table->foreignId('produk_id')->constrained('produks')->index();
            $table->string('deskripsi')->nullable();
            $table->decimal('kuantitas', 10, 2)->default(0);
            $table->string('unit')->nullable();
            $table->decimal('harga_satuan', 15, 2)->default(0);
            $table->decimal('diskon', 5, 2)->default(0);
            $table->decimal('diskon_nominal', 15, 2)->default(0);
            $table->string('batch_number')->nullable();
            $table->date('expired_date')->nullable();
            $table->decimal('jumlah_baris', 15, 2)->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('penjualan_items');
        Schema::dropIfExists('penjualans');
    }
};
