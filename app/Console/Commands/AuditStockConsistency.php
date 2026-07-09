<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\GudangProduk;
use Illuminate\Console\Command;

class AuditStockConsistency extends Command
{
    protected $signature = 'audit:stock-consistency
                            {--fix : Attempt to fix inconsistencies by recalculating stok from subtypes}';

    protected $description = 'Check for stock consistency violations where stok != stok_penjualan + stok_gratis + stok_sample';

    public function handle(): int
    {
        $this->info('Checking stock consistency...');
        $this->newLine();

        $violations = GudangProduk::whereRaw('stok != (stok_penjualan + stok_gratis + stok_sample)')
            ->orWhereRaw('stok_penjualan < 0')
            ->orWhereRaw('stok_gratis < 0')
            ->orWhereRaw('stok_sample < 0')
            ->with(['gudang', 'produk'])
            ->get();

        if ($violations->isEmpty()) {
            $this->info('✓ No stock consistency violations found.');

            return self::SUCCESS;
        }

        $this->error("Found {$violations->count()} stock consistency violation(s):");
        $this->newLine();

        $rows = $violations->map(fn (GudangProduk $gp) => [
            $gp->id,
            $gp->gudang?->nama_gudang ?? "Gudang #{$gp->gudang_id}",
            $gp->produk?->nama_produk ?? "Produk #{$gp->produk_id}",
            $gp->stok,
            $gp->stok_penjualan + $gp->stok_gratis + $gp->stok_sample,
            $gp->stok_penjualan,
            $gp->stok_gratis,
            $gp->stok_sample,
        ])->all();

        $this->table(
            ['ID', 'Gudang', 'Produk', 'Stok', 'Expected', 'Penjualan', 'Gratis', 'Sample'],
            $rows
        );

        if ($this->option('fix')) {
            $this->newLine();
            $this->info('Attempting to fix violations...');

            $fixed = 0;
            foreach ($violations as $violation) {
                $expected = $violation->stok_penjualan + $violation->stok_gratis + $violation->stok_sample;
                $violation->stok = $expected;
                $violation->save();
                $fixed++;

                $this->line("  Fixed GudangProduk #{$violation->id}: stok {$expected}");
            }

            $this->newLine();
            $this->info("✓ Fixed {$fixed} violation(s).");

            return self::SUCCESS;
        }

        $this->newLine();
        $this->warn('Run with --fix to automatically correct violations by recalculating stok from subtypes.');

        return self::FAILURE;
    }
}
