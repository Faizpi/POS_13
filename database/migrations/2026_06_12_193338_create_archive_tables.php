<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ===== ARCHIVE: PENJUALAN =====
        Schema::create('archive_penjualans', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('original_id')->index();
            $table->year('archive_tahun');
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->unsignedBigInteger('approver_id')->nullable()->index();
            $table->unsignedBigInteger('gudang_id')->nullable()->index();
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

            $table->index(['archive_tahun', 'status']);
        });

        Schema::create('archive_penjualan_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('original_id')->index();
            $table->year('archive_tahun');
            $table->unsignedBigInteger('archive_penjualan_id')->index();
            $table->unsignedBigInteger('produk_id')->nullable()->index();
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

        // ===== ARCHIVE: PEMBELIAN =====
        Schema::create('archive_pembelians', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('original_id')->index();
            $table->year('archive_tahun');
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->unsignedBigInteger('approver_id')->nullable()->index();
            $table->unsignedBigInteger('gudang_id')->nullable()->index();
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

            $table->index(['archive_tahun', 'status']);
        });

        Schema::create('archive_pembelian_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('original_id')->index();
            $table->year('archive_tahun');
            $table->unsignedBigInteger('archive_pembelian_id')->index();
            $table->unsignedBigInteger('produk_id')->nullable()->index();
            $table->string('deskripsi')->nullable();
            $table->decimal('kuantitas', 10, 2)->default(0);
            $table->string('unit')->nullable();
            $table->decimal('harga_satuan', 15, 2)->default(0);
            $table->decimal('diskon', 5, 2)->default(0);
            $table->decimal('jumlah_baris', 15, 2)->default(0);
            $table->timestamps();
        });

        // ===== ARCHIVE: BIAYA =====
        Schema::create('archive_biayas', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('original_id')->index();
            $table->year('archive_tahun');
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->unsignedBigInteger('approver_id')->nullable()->index();
            $table->unsignedBigInteger('gudang_id')->nullable()->index();
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

            $table->index(['archive_tahun', 'status']);
        });

        Schema::create('archive_biaya_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('original_id')->index();
            $table->year('archive_tahun');
            $table->unsignedBigInteger('archive_biaya_id')->index();
            $table->string('kategori')->nullable()->index();
            $table->text('deskripsi')->nullable();
            $table->decimal('jumlah', 15, 2)->default(0);
            $table->timestamps();
        });

        // ===== ARCHIVE: KUNJUNGAN =====
        Schema::create('archive_kunjungans', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('original_id')->index();
            $table->year('archive_tahun');
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->unsignedBigInteger('approver_id')->nullable()->index();
            $table->unsignedBigInteger('gudang_id')->nullable()->index();
            $table->unsignedBigInteger('kontak_id')->nullable()->index();
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

            $table->index(['archive_tahun', 'status']);
        });

        Schema::create('archive_kunjungan_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('original_id')->index();
            $table->year('archive_tahun');
            $table->unsignedBigInteger('archive_kunjungan_id')->index();
            $table->unsignedBigInteger('produk_id')->nullable()->index();
            $table->integer('jumlah')->default(1);
            $table->string('batch_number')->nullable();
            $table->date('expired_date')->nullable();
            $table->text('keterangan')->nullable();
            $table->timestamps();
        });

        // ===== ARCHIVE: PEMBAYARAN =====
        Schema::create('archive_pembayarans', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('original_id')->index();
            $table->year('archive_tahun');
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->unsignedBigInteger('approver_id')->nullable()->index();
            $table->unsignedBigInteger('gudang_id')->nullable()->index();
            $table->unsignedBigInteger('penjualan_id')->nullable()->index();
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

            $table->index(['archive_tahun', 'status']);
        });

        // ===== ARCHIVE: PENERIMAAN BARANG =====
        Schema::create('archive_penerimaan_barangs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('original_id')->index('arc_penerima_original_idx');
            $table->year('archive_tahun');
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('user_id')->nullable()->index('arc_penerima_user_idx');
            $table->unsignedBigInteger('approver_id')->nullable()->index('arc_penerima_approver_idx');
            $table->unsignedBigInteger('gudang_id')->nullable()->index('arc_penerima_gudang_idx');
            $table->unsignedBigInteger('pembelian_id')->nullable()->index('arc_penerima_pembelian_idx');
            $table->integer('no_urut_harian')->nullable();
            $table->string('nomor')->nullable();
            $table->date('tgl_penerimaan')->nullable()->index('arc_penerima_tgl_idx');
            $table->string('no_surat_jalan', 100)->nullable();
            $table->text('lampiran_paths')->nullable();
            $table->text('keterangan')->nullable();
            $table->string('status')->default('Pending')->index('arc_penerima_status_idx');
            $table->timestamps();

            $table->index(['archive_tahun', 'status'], 'arc_penerima_tahun_status_idx');
        });

        Schema::create('archive_penerimaan_barang_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('original_id')->index('arc_penerima_item_original_idx');
            $table->year('archive_tahun');
            $table->unsignedBigInteger('archive_penerimaan_barang_id')->index('arc_penerima_item_parent_idx');
            $table->unsignedBigInteger('produk_id')->nullable()->index('arc_penerima_item_produk_idx');
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
        Schema::dropIfExists('archive_penerimaan_barang_items');
        Schema::dropIfExists('archive_penerimaan_barangs');
        Schema::dropIfExists('archive_pembayarans');
        Schema::dropIfExists('archive_kunjungan_items');
        Schema::dropIfExists('archive_kunjungans');
        Schema::dropIfExists('archive_biaya_items');
        Schema::dropIfExists('archive_biayas');
        Schema::dropIfExists('archive_pembelian_items');
        Schema::dropIfExists('archive_pembelians');
        Schema::dropIfExists('archive_penjualan_items');
        Schema::dropIfExists('archive_penjualans');
    }
};
