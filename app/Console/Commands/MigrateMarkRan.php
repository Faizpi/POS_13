<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

#[Signature('migrate:mark-ran {--except-accounting : Leave accounting migrations pending so Laravel can execute them} ')]
#[Description('Mark existing legacy migrations as ran after importing an SQL dump')]
class MigrateMarkRan extends Command
{
    private const array ACCOUNTING_MIGRATIONS = [
        '0001_01_01_000013_create_accounts_table',
        '2026_07_22_100000_add_is_active_to_gudangs_table',
        '2026_07_22_100001_create_cash_bank_accounts_table',
        '2026_07_22_100002_create_account_mappings_table',
        '2026_07_22_100003_create_account_mapping_key_locks_table',
        '2026_07_22_110001_create_journal_entries_table',
        '2026_07_22_110002_create_journal_lines_table',
        '2026_07_22_110003_add_journal_database_guards',
        '2026_07_22_110004_create_journal_posting_sequences_table',
        '2026_07_22_120000_create_journal_source_locks_table',
        '2026_07_22_130000_add_reversal_linkage_to_journal_entries',
        '2026_07_22_140000_create_cash_transfers_table',
        '2026_07_22_140001_create_cash_transfer_sequences_table',
    ];

    public function handle(): int
    {
        $migrationPath = database_path('migrations');

        if (! File::isDirectory($migrationPath)) {
            $this->error('Migrations directory not found.');

            return 1;
        }

        $files = File::files($migrationPath);

        if (empty($files)) {
            $this->info('No migration files found.');

            return 0;
        }

        // Get all migration names from files
        $allMigrations = [];
        foreach ($files as $file) {
            $name = pathinfo($file->getFilename(), PATHINFO_FILENAME);
            $allMigrations[] = $name;
        }

        // Get already registered migrations
        $registered = DB::table('migrations')->pluck('migration')->toArray();

        // Find unregistered migrations. A legacy SQL dump must never mark the
        // new accounting schema as applied before its tables actually exist.
        $unregistered = array_diff($allMigrations, $registered);
        if ($this->option('except-accounting')) {
            $unregistered = array_values(array_diff($unregistered, self::ACCOUNTING_MIGRATIONS));
            $this->info('Accounting migrations will remain pending and run through normal Laravel migration.');
        }

        if (empty($unregistered)) {
            $this->info('All migrations are already registered.');
            $this->info('Total: '.count($allMigrations).' migrations');

            return 0;
        }

        $this->info('Found '.count($unregistered).' unregistered migration(s) out of '.count($allMigrations).' total.');
        $this->newLine();

        // Get the next batch number
        $maxBatch = DB::table('migrations')->max('batch') ?? 0;
        $nextBatch = $maxBatch + 1;

        $inserted = 0;
        foreach ($unregistered as $migration) {
            DB::table('migrations')->insert([
                'migration' => $migration,
                'batch' => $nextBatch,
            ]);
            $this->line("  ✓ {$migration}");
            $inserted++;
        }

        $this->newLine();
        $this->info("Successfully marked {$inserted} migration(s) as ran (batch {$nextBatch}).");
        $this->newLine();
        $this->info("Now run 'php artisan migrate:status' to verify.");

        return 0;
    }
}
