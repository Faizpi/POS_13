<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pembayarans', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->constrained();
            $table->unsignedBigInteger('approver_id')->nullable();
            $table->unsignedBigInteger('gudang_id')->index();
            $table->unsignedBigInteger('penjualan_id')->index();
            $table->integer('no_urut_harian')->nullable();
            $table->string('nomor')->nullable();
            $table->date('tgl_pembayaran')->nullable()->index();
            $table->string('metode_pembayaran')->nullable();
            $table->decimal('jumlah_bayar', 15, 2)->default(0);
            $table->string('bukti_bayar')->nullable();
            $table->text('lampiran_paths')->nullable();
            $table->text('keterangan')->nullable();
            $table->string('status')->default('Pending')->index();
            $table->timestamps();

            $table->foreign('gudang_id', 'pembayarans_gudang_fk')->references('id')->on('gudangs');
            $table->foreign('penjualan_id', 'pembayarans_penjualan_fk')->references('id')->on('penjualans');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pembayarans');
    }
};
