<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\JournalEntry;
use Illuminate\Console\Command;

final class AccountingIntegrityCheck extends Command
{
    protected $signature = 'accounting:check';

    protected $description = 'Report posted journal integrity violations without modifying data.';

    public function handle(): int
    {
        $unbalanced = JournalEntry::query()
            ->where('status', 'posted')
            ->whereColumn('total_debit', '!=', 'total_credit')
            ->count();
        $duplicateSources = JournalEntry::query()
            ->selectRaw('source_type, source_id, journal_type, source_version')
            ->groupBy('source_type', 'source_id', 'journal_type', 'source_version')
            ->havingRaw('COUNT(*) > 1')
            ->count();

        if ($unbalanced === 0 && $duplicateSources === 0) {
            $this->info('Accounting integrity check passed');

            return self::SUCCESS;
        }

        $this->error("Accounting integrity check failed: {$unbalanced} unbalanced posted journal(s), {$duplicateSources} duplicate source identity group(s).");

        return self::FAILURE;
    }
}
