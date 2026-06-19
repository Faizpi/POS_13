<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fix-all migration: sync schema setelah import DB lama.
 * Idempotent — aman di-run berkali-kali.
 *
 * Jalankan: php artisan migrate --force
 */
return new class extends Migration
{
    public function up(): void
    {
        // ── 1. USERS: kolom baru ──────────────────────────────────────────
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'receives_transaction_whatsapp')) {
                $table->boolean('receives_transaction_whatsapp')
                    ->default(true)
                    ->after('receives_transaction_email');
            }
        });

        // ── 2. PEMBELIANS: kolom baru ─────────────────────────────────────
        Schema::table('pembelians', function (Blueprint $table) {
            if (!Schema::hasColumn('pembelians', 'kontak_id')) {
                $table->foreignId('kontak_id')
                    ->nullable()
                    ->after('gudang_id')
                    ->constrained('kontaks')
                    ->nullOnDelete();
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

        // ── 3. PENJUALANS: kolom baru ────────────────────────────────────
        Schema::table('penjualans', function (Blueprint $table) {
            if (!Schema::hasColumn('penjualans', 'no_resi')) {
                $table->string('no_resi')->nullable();
            }
            if (!Schema::hasColumn('penjualans', 'biaya_pengiriman')) {
                $table->decimal('biaya_pengiriman', 15, 2)->nullable()->default(0);
            }
        });

        // ── 4. PEMBAYARANS: kolom baru untuk hutang ──────────────────────
        Schema::table('pembayarans', function (Blueprint $table) {
            if (!Schema::hasColumn('pembayarans', 'type')) {
                $table->string('type')->default('piutang')
                    ->comment('piutang = dari penjualan, hutang = dari pembelian');
            }
            if (!Schema::hasColumn('pembayarans', 'pembelian_id')) {
                $table->unsignedBigInteger('pembelian_id')->nullable()->index();
                $table->foreign('pembelian_id', 'pembayarans_pembelian_fk')
                    ->references('id')->on('pembelians')->nullOnDelete();
            }
        });

        // ── 5. STOCK OPNAMES: tabel baru ──────────────────────────────────
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

        if (!Schema::hasTable('stock_opname_items')) {
            Schema::create('stock_opname_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('stock_opname_id')->constrained('stock_opnames')->cascadeOnDelete();
                $table->foreignId('produk_id')->constrained('produks');
                $table->string('batch_number')->nullable();
                $table->date('expired_date')->nullable();
                $table->decimal('qty_system', 10, 2)->default(0);
                $table->decimal('qty_aktual', 10, 2)->default(0);
                $table->decimal('selisih', 10, 2)->default(0);
                $table->text('keterangan')->nullable();
                $table->timestamps();
            });
        }

        // ── 6. TUTUP BUKU: tabel baru ────────────────────────────────────
        if (!Schema::hasTable('tutup_buku')) {
            Schema::create('tutup_buku', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->foreignId('gudang_id')->constrained('gudangs');
                $table->date('tgl_tutup_buku');
                $table->decimal('total_penjualan', 15, 2)->default(0);
                $table->decimal('total_pembelian', 15, 2)->default(0);
                $table->decimal('total_biaya', 15, 2)->default(0);
                $table->decimal('total_piutang', 15, 2)->default(0);
                $table->decimal('total_hutang', 15, 2)->default(0);
                $table->text('catatan')->nullable();
                $table->string('status')->default('Draft');
                $table->timestamps();
            });
        }

        // ── 7. NOTIFICATIONS: tabel baru ──────────────────────────────────
        if (!Schema::hasTable('notifications')) {
            Schema::create('notifications', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('type');
                $table->morphs('notifiable');
                $table->text('data');
                $table->timestamp('read_at')->nullable();
                $table->timestamps();
            });
        }

        // ── 8. SESSIONS: tabel baru ───────────────────────────────────────
        if (!Schema::hasTable('sessions')) {
            Schema::create('sessions', function (Blueprint $table) {
                $table->string('id')->primary();
                $table->foreignId('user_id')->nullable()->index();
                $table->string('ip_address', 45)->nullable();
                $table->text('user_agent')->nullable();
                $table->longText('payload');
                $table->integer('last_activity')->index();
            });
        }

        // ── 9. CACHE: tabel baru ──────────────────────────────────────────
        if (!Schema::hasTable('cache')) {
            Schema::create('cache', function (Blueprint $table) {
                $table->string('key')->primary();
                $table->mediumText('value');
                $table->integer('expiration');
            });
        }

        if (!Schema::hasTable('cache_locks')) {
            Schema::create('cache_locks', function (Blueprint $table) {
                $table->string('key')->primary();
                $table->string('owner');
                $table->integer('expiration');
            });
        }

        // ── 10. JOBS: tabel baru ──────────────────────────────────────────
        if (!Schema::hasTable('jobs')) {
            Schema::create('jobs', function (Blueprint $table) {
                $table->id();
                $table->string('queue')->index();
                $table->longText('payload');
                $table->unsignedTinyInteger('attempts');
                $table->unsignedInteger('reserved_at')->nullable();
                $table->unsignedInteger('available_at');
                $table->unsignedInteger('created_at');
            });
        }

        if (!Schema::hasTable('failed_jobs')) {
            Schema::create('failed_jobs', function (Blueprint $table) {
                $table->id();
                $table->string('uuid')->unique();
                $table->text('connection');
                $table->text('queue');
                $table->longText('payload');
                $table->longText('exception');
                $table->timestamp('failed_at')->useCurrent();
            });
        }
    }

    public function down(): void
    {
        // No-op: schema sync migration, tidak boleh di-rollback
    }
};
