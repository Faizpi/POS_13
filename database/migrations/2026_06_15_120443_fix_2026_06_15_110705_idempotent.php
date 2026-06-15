<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ── Idempotent migration: safe to run multiple times ─────────────────
        // Cek apakah kolom sudah ada sebelum ALTER untuk mencegah error duplikat.

        // 1. pembelians: tambah kolom kontak_id + 4 field baru
        Schema::table('pembelians', function (Blueprint $table) {
            if (!Schema::hasColumn('pembelians', 'kontak_id')) {
                $table->foreignId('kontak_id')->nullable()->after('gudang_id')->constrained('kontaks')->nullOnDelete();
            }
            if (!Schema::hasColumn('pembelians', 'tipe_harga')) {
                $table->string('tipe_harga')->default('retail');
            }
            if (!Schema::hasColumn('pembelians', 'no_referensi')) {
                $table->string('no_referensi')->nullable();
            }
            if (!Schema::hasColumn('pembelians', 'no_resi')) {
                $table->string('no_resi')->nullable();
            }
            if (!Schema::hasColumn('pembelians', 'biaya_pengiriman')) {
                $table->decimal('biaya_pengiriman', 15, 2)->nullable()->default(0);
            }
        });

        // 2. penjualans: tambah 2 field baru
        Schema::table('penjualans', function (Blueprint $table) {
            if (!Schema::hasColumn('penjualans', 'no_resi')) {
                $table->string('no_resi')->nullable();
            }
            if (!Schema::hasColumn('penjualans', 'biaya_pengiriman')) {
                $table->decimal('biaya_pengiriman', 15, 2)->nullable()->default(0);
            }
        });

        // 3. pembayarans: extend untuk support hutang
        Schema::table('pembayarans', function (Blueprint $table) {
            if (!Schema::hasColumn('pembayarans', 'type')) {
                $table->string('type')->default('piutang')->comment('piutang = dari penjualan, hutang = dari pembelian');
            }
            if (!Schema::hasColumn('pembayarans', 'pembelian_id')) {
                $table->unsignedBigInteger('pembelian_id')->nullable()->index();
                $table->foreign('pembelian_id', 'pembayarans_pembelian_fk')
                    ->references('id')->on('pembelians')->nullOnDelete();
            }
        });

        // 4. stock_opnames: tabel baru (jika belum ada)
        if (!Schema::hasTable('stock_opnames')) {
            Schema::create('stock_opnames', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->unsignedBigInteger('approver_id')->nullable()->index();
                $table->foreignId('gudang_id')->constrained('gudangs');
                $table->integer('no_urut_harian')->nullable();
                $table->string('nomor')->nullable()->index();
                $table->date('tgl_opname')->nullable()->index();
                $table->string('status')->default('Draft')->index();
                $table->text('memo')->nullable();
                $table->json('lampiran_paths')->nullable();
                $table->timestamps();

                $table->index(['user_id', 'status']);
                $table->index('created_at');
            });
        }

        // 5. stock_opname_items: tabel baru
        if (!Schema::hasTable('stock_opname_items')) {
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
    }

    public function down(): void
    {
        // No-op: hanya migrasi sebelumnya yang boleh di-down
    }
};
