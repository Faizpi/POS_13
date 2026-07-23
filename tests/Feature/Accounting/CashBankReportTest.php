<?php

declare(strict_types=1);

namespace Tests\Feature\Accounting;

use App\Accounting\AccountCategory;
use App\Accounting\CashAccountType;
use App\Accounting\JournalStatus;
use App\Accounting\JournalType;
use App\Accounting\LineOrder;
use App\Accounting\Money;
use App\Accounting\SourceIdentity;
use App\Models\Account;
use App\Models\CashBankAccount;
use App\Services\Accounting\CashBankReportService;
use App\Services\Accounting\LedgerPersistenceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class CashBankReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_posted_cash_lines_have_opening_movement_and_running_balance(): void
    {
        $cash = Account::factory()->create(['category' => AccountCategory::Aset, 'subcategory' => 'kas', 'is_active' => true, 'is_postable' => true]);
        $counterpart = Account::factory()->create(['category' => AccountCategory::Pendapatan, 'is_active' => true, 'is_postable' => true]);
        $master = CashBankAccount::factory()->create(['account_id' => $cash->id, 'type' => CashAccountType::Kas]);
        $this->postJournal($cash, $counterpart, 1, '2026-07-20', 1, '100.00');
        $this->postJournal($cash, $counterpart, 2, '2026-07-22', 2, '25.00');

        $report = app(CashBankReportService::class)->report($master, ['date_from' => '2026-07-22', 'date_to' => '2026-07-22']);

        $this->assertSame('100.00', $report['opening_balance']);
        $this->assertSame('25.00', $report['movement_debit']);
        $this->assertSame('125.00', $report['ending_balance']);
        $this->assertSame('125.00', $report['rows'][0]['running_balance']);
    }

    private function postJournal(Account $debit, Account $credit, int $sourceId, string $date, int $sequence, string $amount): void
    {
        $journal = app(LedgerPersistenceService::class)->persist(new SourceIdentity('sale', $sourceId, JournalType::Sale, 1), $date, 'Cash report fixture', null, null, null, [
            ['account_id' => $debit->id, 'line_order' => new LineOrder(10), 'debit' => Money::fromDecimalString($amount), 'credit' => null],
            ['account_id' => $credit->id, 'line_order' => new LineOrder(20), 'debit' => null, 'credit' => Money::fromDecimalString($amount)],
        ]);
        $journal->update(['status' => JournalStatus::Approved]);
        $journal->update(['status' => JournalStatus::Posted, 'journal_number' => sprintf('JRN-%06d', $sequence), 'posting_sequence' => $sequence]);
    }
}
