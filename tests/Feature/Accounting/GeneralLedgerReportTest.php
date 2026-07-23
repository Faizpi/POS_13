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

final class GeneralLedgerReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_account_ledger_shows_opening_movement_and_deterministic_running_balance(): void
    {
        [$cash, $revenue] = $this->accounts();
        $this->postJournal($cash, $revenue, 1, '2026-07-20', 1, '100.00');
        $this->postJournal($cash, $revenue, 2, '2026-07-22', 3, '20.00');
        $this->postJournal($cash, $revenue, 3, '2026-07-22', 2, '30.00');

        $report = app(AccountingReportService::class)->generalLedger($cash->id, [
            'date_from' => '2026-07-22',
            'date_to' => '2026-07-22',
        ]);

        $this->assertSame('100.00', $report['opening_balance']);
        $this->assertSame(['JRN-000002', 'JRN-000003'], $report['rows']->pluck('journal_number')->all());
        $this->assertSame(['130.00', '150.00'], $report['rows']->pluck('running_balance')->all());
        $this->assertSame('50.00', $report['movement_debit']);
        $this->assertSame('0.00', $report['movement_credit']);
        $this->assertSame('150.00', $report['ending_balance']);
    }

    private function accounts(): array
    {
        return [
            Account::factory()->create(['category' => AccountCategory::Aset, 'is_active' => true, 'is_postable' => true]),
            Account::factory()->create(['category' => AccountCategory::Pendapatan, 'is_active' => true, 'is_postable' => true]),
        ];
    }

    private function postJournal(Account $debit, Account $credit, int $sourceId, string $date, int $sequence, string $amount): void
    {
        $journal = app(LedgerPersistenceService::class)->persist(new SourceIdentity('sale', $sourceId, JournalType::Sale, 1), $date, 'Ledger fixture', null, null, null, [
            ['account_id' => $debit->id, 'line_order' => new LineOrder(10), 'debit' => Money::fromDecimalString($amount), 'credit' => null],
            ['account_id' => $credit->id, 'line_order' => new LineOrder(20), 'debit' => null, 'credit' => Money::fromDecimalString($amount)],
        ]);
        $journal->update(['status' => JournalStatus::Approved]);
        $journal->update(['status' => JournalStatus::Posted, 'journal_number' => sprintf('JRN-%06d', $sequence), 'posting_sequence' => $sequence]);
    }
}
