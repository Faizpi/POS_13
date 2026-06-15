<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ── 1. pembelians: tambah field baru ──────────────────────────────────
        Schema::table('pembelians', function (Blueprint $table) {
            $table->string('tipe_harga')->default('retail')->after('kontak_id');
            $table->string('no_referensi')->nullable()->after('tipe_harga');
            $table->string('no_resi')->nullable()->after('no_referensi');
            $table->decimal('biaya_pengiriman', 15, 2)->nullable()->default(0)->after('no_resi');
        });

        // ── 2. penjualans: tambah field baru ─────────────────────────────────
        Schema::table('penjualans', function (Blueprint $table) {
            $table->string('no_resi')->nullable()->after('no_referensi');
            $table->decimal('biaya_pengiriman', 15, 2)->nullable()->default(0)->after('no_resi');
        });

        // ── 3. pembayarans: extend untuk support hutang (pembelian) ──────────
        Schema::table('pembayarans', function (Blueprint $table) {
            $table->string('type')->default('piutang')->after('id')->comment('piutang = dari penjualan, hutang = dari pembelian');
            $table->unsignedBigInteger('pembelian_id')->nullable()->after('penjualan_id')->index();
            $table->foreign('pembelian_id', 'pembayarans_pembelian_fk')
                ->references('id')->on('pembelians')->nullOnDelete();
            // penjualan_id boleh null sekarang (untuk hutang)
            $table->unsignedBigInteger('penjualan_id')->nullable()->change();
        });

        // ── 4. stock_opnames: tabel baru ──────────────────────────────────────
        Schema::create('stock_opnames', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('approver_id')->nullable()->index();
            $table->foreignId('gudang_id')->constrained('gudangs');
            $table->integer('no_urut_harian')->nullable();
            $table->string('nomor')->nullable()->index();
            $table->date('tgl_opname')->nullable()->index();
            $table->string('status')->default('Draft')->index(); // Draft | Submitted | Applied
            $table->text('memo')->nullable();
            $table->json('lampiran_paths')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index('created_at');
        });

        // ── 5. stock_opname_items: detail per produk ──────────────────────────
        Schema::create('stock_opname_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_opname_id')->constrained('stock_opnames')->cascadeOnDelete();
            $table->foreignId('produk_id')->constrained('produks');
            $table->string('batch_number')->nullable();
            $table->date('expired_date')->nullable();
            $table->decimal('qty_system', 10, 2)->default(0)->comment('Stok di sistem sebelum opname');
            $table->decimal('qty_aktual', 10, 2)->default(0)->comment('Stok hasil hitung fisik');
            $table->decimal('selisih', 10, 2)->default(0)->comment('qty_aktual - qty_system');
            $table->text('keterangan')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_opname_items');
        Schema::dropIfExists('stock_opnames');

        Schema::table('pembayarans', function (Blueprint $table) {
            $table->dropForeign('pembayarans_pembelian_fk');
            $table->dropColumn(['type', 'pembelian_id']);
            $table->unsignedBigInteger('penjualan_id')->nullable(false)->change();
        });

        Schema::table('penjualans', function (Blueprint $table) {
            $table->dropColumn(['no_resi', 'biaya_pengiriman']);
        });

        Schema::table('pembelians', function (Blueprint $table) {
            $table->dropColumn(['tipe_harga', 'no_referensi', 'no_resi', 'biaya_pengiriman']);
        });
    }
};
