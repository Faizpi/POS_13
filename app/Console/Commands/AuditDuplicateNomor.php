<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AuditDuplicateNomor extends Command
{
    protected $signature = 'audit:duplicate-nomor';

    protected $description = 'Check for duplicate nomor values in transaction tables before applying unique indexes';

    protected $tables = [
        'penjualans',
        'pembelians',
        'pembayarans',
        'penerimaan_barangs',
        'stock_opnames',
        'biayas',
        'kunjungans',
    ];

    public function handle(): int
    {
        $this->info('Checking for duplicate nomor values in transaction tables...');
        $this->newLine();

        $hasDuplicates = false;

        foreach ($this->tables as $table) {
            if (! Schema::hasTable($table)) {
                $this->warn("Table {$table} does not exist, skipping...");

                continue;
            }

            if (! Schema::hasColumn($table, 'nomor')) {
                $this->warn("Table {$table} does not have nomor column, skipping...");

                continue;
            }

            $duplicateCount = $this->checkDuplicates($table);
            if ($duplicateCount > 0) {
                $hasDuplicates = true;
            }
        }

        $this->newLine();

        if ($hasDuplicates) {
            $this->error('Duplicate nomor values found!');
            $this->warn('Please resolve duplicates before applying unique indexes.');

            return self::FAILURE;
        }

        $this->info('✓ No duplicate nomor values found. Safe to apply unique indexes.');

        return self::SUCCESS;
    }

    protected function checkDuplicates(string $table): int
    {
        $duplicates = DB::table($table)
            ->select('nomor', DB::raw('COUNT(*) as count'))
            ->whereNotNull('nomor')
            ->groupBy('nomor')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        if ($duplicates->isEmpty()) {
            return 0;
        }

        $this->error("Table {$table} has {$duplicates->count()} duplicate nomor value(s):");
        foreach ($duplicates as $dup) {
            $this->error("  - {$dup->nomor} (appears {$dup->count} times)");
        }

        return $duplicates->count();
    }
}
