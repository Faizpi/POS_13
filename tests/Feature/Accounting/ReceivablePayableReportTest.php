<?php

declare(strict_types=1);

namespace Tests\Feature\Accounting;

use App\Accounting\AccountCategory;
use App\Accounting\MappingKey;
use App\Models\Account;
use App\Models\User;
use App\Services\Accounting\AccountingReportService;
use App\Services\Accounting\AccountMappingService;
use App\Services\Accounting\HutangLedgerService;
use App\Services\Accounting\HutangPostingService;
use App\Services\Accounting\PiutangPostingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\BuildsTransactionFixtures;
use Tests\TestCase;

final class ReceivablePayableReportTest extends TestCase
{
    use BuildsTransactionFixtures;
    use RefreshDatabase;

    public function test_aging_report_reuses_posted_ar_and_ap_ledger_balances(): void
    {
        $actor = User::factory()->create(['role' => 'super_admin']);
        $sale = $this->transactionPenjualan($actor, null, ['status' => 'Approved', 'syarat_pembayaran' => 'Net 30', 'grand_total' => '100.00', 'tgl_jatuh_tempo' => '2026-06-22']);
        $purchase = $this->transactionPembelian($actor, $sale->gudang, ['status' => 'Approved', 'grand_total' => '200.00', 'tgl_jatuh_tempo' => '2026-05-22']);
        $this->map($actor, MappingKey::ArReceivable, AccountCategory::Aset);
        $this->map($actor, MappingKey::SalesRetailRevenue, AccountCategory::Pendapatan);
        $this->map($actor, MappingKey::ApPayable, AccountCategory::Kewajiban);
        $this->map($actor, MappingKey::PurchaseInventory, AccountCategory::Aset);
        app(PiutangPostingService::class)->postSale($actor, $sale);
        app(HutangPostingService::class)->postPurchase($actor, $purchase);

        $this->assertNotEmpty(app(HutangLedgerService::class)->mutationsForPurchase($purchase));

        $report = app(AccountingReportService::class)->receivablePayableAging('2026-07-22');

        $this->assertSame('100.00', $report['receivables']['total']);
        $this->assertSame('100.00', $report['receivables']['buckets']['1-30']);
        $this->assertSame('200.00', $report['payables']['total']);
        $this->assertSame('200.00', $report['payables']['buckets']['61-90']);
    }

    private function map(User $actor, MappingKey $key, AccountCategory $category): Account
    {
        $account = Account::factory()->create(['category' => $category, 'is_active' => true, 'is_postable' => true]);
        app(AccountMappingService::class)->create($actor, $key, $account, '2026-01-01', isActive: true);

        return $account;
    }
}
