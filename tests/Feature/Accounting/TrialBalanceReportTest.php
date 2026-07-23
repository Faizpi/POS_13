<?php

declare(strict_types=1);

namespace Tests\Feature\Accounting;

use App\Accounting\AccountCategory;
use App\Accounting\JournalStatus;
use App\Accounting\JournalType;
use App\Accounting\LineOrder;
use App\Accounting\Money;
use App\Accounting\SourceIdentity;
use App\Models\Account;
use App\Services\Accounting\AccountingReportService;
use App\Services\Accounting\LedgerPersistenceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class TrialBalanceReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_consolidated_trial_balance_has_exact_opening_movement_ending_and_balanced_totals(): void
    {
        $asset = Account::factory()->create(['code' => '1-1101', 'name' => 'Kas', 'category' => AccountCategory::Aset, 'is_active' => true, 'is_postable' => true]);
        $revenue = Account::factory()->create(['code' => '4-4101', 'name' => 'Pendapatan', 'category' => AccountCategory::Pendapatan, 'is_active' => true, 'is_postable' => true]);
        $this->postJournal($asset, $revenue, 1, '2026-07-20', 1, '100.00');
        $this->postJournal($asset, $revenue, 2, '2026-07-22', 2, '25.00');

        $report = app(AccountingReportService::class)->trialBalance(['date_from' => '2026-07-22', 'date_to' => '2026-07-22']);

        $kas = $report['rows']->firstWhere('account_id', $asset->id);
        $pendapatan = $report['rows']->firstWhere('account_id', $revenue->id);
        $this->assertSame('100.00', $kas['opening_debit']);
        $this->assertSame('25.00', $kas['movement_debit']);
        $this->assertSame('125.00', $kas['ending_debit']);
        $this->assertSame('100.00', $pendapatan['opening_credit']);
        $this->assertSame('25.00', $pendapatan['movement_credit']);
        $this->assertSame('125.00', $pendapatan['ending_credit']);
        $this->assertSame($report['totals']['ending_debit'], $report['totals']['ending_credit']);
    }

    private function postJournal(Account $debit, Account $credit, int $sourceId, string $date, int $sequence, string $amount): void
    {
        $journal = app(LedgerPersistenceService::class)->persist(new SourceIdentity('sale', $sourceId, JournalType::Sale, 1), $date, 'Trial balance fixture', null, null, null, [
            ['account_id' => $debit->id, 'line_order' => new LineOrder(10), 'debit' => Money::fromDecimalString($amount), 'credit' => null],
            ['account_id' => $credit->id, 'line_order' => new LineOrder(20), 'debit' => null, 'credit' => Money::fromDecimalString($amount)],
        ]);
        $journal->update(['status' => JournalStatus::Approved]);
        $journal->update(['status' => JournalStatus::Posted, 'journal_number' => sprintf('JRN-%06d', $sequence), 'posting_sequence' => $sequence]);
    }
}
