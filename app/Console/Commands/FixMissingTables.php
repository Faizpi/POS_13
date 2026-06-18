<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

#[Signature('fix-tables')]
#[Description('Create missing tables that migrations should have created (useful after SQL import)')]
class FixMissingTables extends Command
{
    public function handle()
    {
        $missing = [];

        $expectedTables = [
            'gudangs', 'produks', 'users', 'password_resets', 'sessions',
            'admin_gudang', 'spectator_gudang', 'gudang_produk', 'stok_logs',
            'kontaks', 'penjualans', 'penjualan_items', 'pembelians', 'pembelian_items',
            'biayas', 'biaya_items', 'kunjungans', 'kunjungan_items',
            'pembayarans', 'penerimaan_barangs', 'penerimaan_barang_items',
            'personal_access_tokens', 'cache', 'cache_locks', 'jobs', 'job_batches',
            'failed_jobs', 'notifications',
            'archive_penjualans', 'archive_penjualan_items',
            'archive_pembelians', 'archive_pembelian_items',
            'archive_biayas', 'archive_biaya_items',
            'archive_kunjungans', 'archive_kunjungan_items',
            'archive_pembayarans', 'archive_penerimaan_barangs',
            'archive_penerimaan_barang_items',
            'tutup_buku', 'stock_opnames', 'stock_opname_items',
        ];

        foreach ($expectedTables as $table) {
            if (!Schema::hasTable($table)) {
                $missing[] = $table;
            }
        }

        if (empty($missing)) {
            $this->info('All ' . count($expectedTables) . ' expected tables exist. No fix needed.');
            return 0;
        }

        $this->info('Found ' . count($missing) . ' missing table(s):');
        foreach ($missing as $t) {
            $this->line("  - {$t}");
        }
        $this->newLine();
        $this->info('Creating missing tables...');
        $this->newLine();

        foreach ($missing as $table) {
            try {
                $this->createTable($table);
                $this->line("  ✓ {$table}");
            } catch (\Throwable $e) {
                $this->line("  ✗ {$table}: " . $e->getMessage());
            }
        }

        $this->newLine();
        $this->info('Done. Run "php artisan cache:clear" to verify.');

        return 0;
    }

    private function createTable(string $table): void
    {
        Schema::create($table, function ($t) use ($table) {
            match ($table) {
                'sessions' => (function () use ($t) {
                    $t->string('id')->primary();
                    $t->foreignId('user_id')->nullable()->index();
                    $t->string('ip_address', 45)->nullable();
                    $t->text('user_agent')->nullable();
                    $t->longText('payload');
                    $t->integer('last_activity')->index();
                })(),
                'cache' => (function () use ($t) {
                    $t->string('key')->primary();
                    $t->mediumText('value');
                    $t->bigInteger('expiration')->index();
                })(),
                'cache_locks' => (function () use ($t) {
                    $t->string('key')->primary();
                    $t->string('owner');
                    $t->bigInteger('expiration')->index();
                })(),
                'jobs' => (function () use ($t) {
                    $t->bigIncrements('id');
                    $t->string('queue')->index();
                    $t->longText('payload');
                    $t->unsignedSmallInteger('attempts');
                    $t->unsignedInteger('reserved_at')->nullable();
                    $t->unsignedInteger('available_at');
                    $t->unsignedInteger('created_at');
                })(),
                'job_batches' => (function () use ($t) {
                    $t->string('id')->primary();
                    $t->string('name');
                    $t->integer('total_jobs');
                    $t->integer('pending_jobs');
                    $t->integer('failed_jobs');
                    $t->longText('failed_job_ids');
                    $t->mediumText('options')->nullable();
                    $t->integer('cancelled_at')->nullable();
                    $t->integer('created_at');
                    $t->integer('finished_at')->nullable();
                })(),
                'failed_jobs' => (function () use ($t) {
                    $t->id();
                    $t->string('uuid')->unique();
                    $t->string('connection');
                    $t->string('queue');
                    $t->longText('payload');
                    $t->longText('exception');
                    $t->timestamp('failed_at')->useCurrent();
                    $t->index(['connection', 'queue', 'failed_at']);
                })(),
                'notifications' => (function () use ($t) {
                    $t->uuid('id')->primary();
                    $t->string('type');
                    $t->morphs('notifiable');
                    $t->text('data');
                    $t->timestamp('read_at')->nullable();
                    $t->timestamps();
                })(),
                'password_resets' => (function () use ($t) {
                    $t->string('email')->index();
                    $t->string('token');
                    $t->timestamp('created_at')->nullable();
                })(),
                default => throw new \RuntimeException("No schema for table: {$table}"),
            };
        });
    }
}
