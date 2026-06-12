<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('biayas', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->constrained();
            $table->unsignedBigInteger('approver_id')->nullable()->index();
            $table->foreignId('gudang_id')->nullable()->constrained('gudangs')->nullOnDelete();
            $table->integer('no_urut_harian')->nullable();
            $table->string('nomor')->nullable()->index();
            $table->string('jenis_biaya')->default('keluar');
            $table->string('bayar_dari')->nullable();
            $table->string('penerima')->nullable();
            $table->text('alamat_penagihan')->nullable();
            $table->date('tgl_transaksi')->nullable()->index();
            $table->string('cara_pembayaran')->nullable();
            $table->string('tag')->nullable();
            $table->string('koordinat')->nullable();
            $table->text('memo')->nullable();
            $table->string('lampiran_path')->nullable();
            $table->json('lampiran_paths')->nullable();
            $table->string('status')->default('Pending')->index();
            $table->decimal('tax_percentage', 5, 2)->nullable();
            $table->decimal('grand_total', 15, 2)->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index(['approver_id', 'status']);
            $table->index('created_at');
        });

        Schema::create('biaya_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('biaya_id')->constrained('biayas')->cascadeOnDelete();
            $table->string('kategori')->index();
            $table->text('deskripsi')->nullable();
            $table->decimal('jumlah', 15, 2)->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('biaya_items');
        Schema::dropIfExists('biayas');
    }
};
