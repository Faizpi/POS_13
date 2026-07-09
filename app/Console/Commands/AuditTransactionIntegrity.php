<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class AuditTransactionIntegrity extends Command
{
    protected $signature = 'audit:transaction-integrity';

    protected $description = 'Audit transaction integrity by checking for constraint violations before applying database guardrails';

    public function handle(): int
    {
        $this->info('Auditing transaction integrity...');
        $this->newLine();

        $violations = [];

        // Check gudang_produk stock columns
        $violations = array_merge($violations, $this->checkNonNegative('gudang_produk', 'stok'));
        $violations = array_merge($violations, $this->checkNonNegative('gudang_produk', 'stok_penjualan'));
        $violations = array_merge($violations, $this->checkNonNegative('gudang_produk', 'stok_gratis'));
        $violations = array_merge($violations, $this->checkNonNegative('gudang_produk', 'stok_sample'));

        // Check pembayarans
        $violations = array_merge($violations, $this->checkNonNegative('pembayarans', 'jumlah_bayar'));

        // Check penjualans
        $violations = array_merge($violations, $this->checkNonNegative('penjualans', 'grand_total'));
        $violations = array_merge($violations, $this->checkRange('penjualans', 'tax_percentage', 0, 100));

        // Check pembelians
        $violations = array_merge($violations, $this->checkNonNegative('pembelians', 'grand_total'));
        $violations = array_merge($violations, $this->checkRange('pembelians', 'tax_percentage', 0, 100));

        // Check penjualan_items
        $violations = array_merge($violations, $this->checkNonNegative('penjualan_items', 'jumlah_baris'));
        $violations = array_merge($violations, $this->checkRange('penjualan_items', 'diskon', 0, 100));

        // Check pembelian_items
        $violations = array_merge($violations, $this->checkNonNegative('pembelian_items', 'jumlah_baris'));
        $violations = array_merge($violations, $this->checkRange('pembelian_items', 'diskon', 0, 100));

        if (empty($violations)) {
            $this->info('✓ No integrity violations found.');

            return 0;
        }

        $this->error('✗ Found '.count($violations).' violation(s):');
        $this->newLine();

        foreach ($violations as $violation) {
            $this->line("  • {$violation}");
        }

        $this->newLine();
        $this->warn('Fix these violations before running migrations that add CHECK constraints.');

        return 1;
    }

    private function checkNonNegative(string $table, string $column): array
    {
        $count = DB::table($table)->where($column, '<', 0)->count();

        if ($count === 0) {
            return [];
        }

        return ["{$table}.{$column}: {$count} row(s) with negative values"];
    }

    private function checkRange(string $table, string $column, int $min, int $max): array
    {
        $count = DB::table($table)
            ->where(function ($query) use ($column, $min, $max) {
                $query->where($column, '<', $min)
                    ->orWhere($column, '>', $max);
            })
            ->count();

        if ($count === 0) {
            return [];
        }

        return ["{$table}.{$column}: {$count} row(s) outside range [{$min}, {$max}]"];
    }
}
