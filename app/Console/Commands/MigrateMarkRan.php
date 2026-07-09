<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

#[Signature('migrate:mark-ran')]
#[Description('Mark all existing migrations as already ran (useful after importing SQL dump)')]
class MigrateMarkRan extends Command
{
    public function handle()
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

        // Find unregistered migrations
        $unregistered = array_diff($allMigrations, $registered);

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
