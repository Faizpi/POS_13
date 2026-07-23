<?php

declare(strict_types=1);

namespace Tests\Feature\Accounting;

use App\Accounting\AccountCategory;
use App\Accounting\MappingKey;
use App\Models\Account;
use App\Models\User;
use App\Services\Accounting\AccountMappingService;
use App\Services\Accounting\PiutangLedgerService;
use App\Services\Accounting\PiutangPostingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\BuildsTransactionFixtures;
use Tests\TestCase;

final class PiutangAgingTest extends TestCase
{
    use BuildsTransactionFixtures;
    use RefreshDatabase;

    public function test_posted_credit_sale_and_payment_produce_exact_running_ar_balance(): void
    {
        $actor = User::factory()->create(['role' => 'super_admin']);
        $sale = $this->transactionPenjualan($actor, null, [
            'syarat_pembayaran' => 'Net 30',
            'status' => 'Approved',
            'grand_total' => '1000000.00',
        ]);
        $payment = $this->transactionPembayaran($sale, $actor, [
            'status' => 'Approved',
            'jumlah_bayar' => '400000.00',
            'metode_pembayaran' => 'Cash',
        ]);
        $this->map($actor, MappingKey::ArReceivable, AccountCategory::Aset);
        $this->map($actor, MappingKey::SalesRetailRevenue, AccountCategory::Pendapatan);
        $this->map($actor, MappingKey::CashDefault, AccountCategory::Aset);
        app(PiutangPostingService::class)->postSale($actor, $sale);
        app(PiutangPostingService::class)->postPayment($actor, $payment);

        $mutations = app(PiutangLedgerService::class)->mutationsForSale($sale);

        $this->assertCount(2, $mutations);
        $this->assertSame('1000000.00', $mutations[0]['debit']);
        $this->assertSame('0.00', $mutations[0]['credit']);
        $this->assertSame('1000000.00', $mutations[0]['running_balance']);
        $this->assertSame('0.00', $mutations[1]['debit']);
        $this->assertSame('400000.00', $mutations[1]['credit']);
        $this->assertSame('600000.00', $mutations[1]['running_balance']);
        $this->assertSame('600000.00', app(PiutangLedgerService::class)->outstandingForSale($sale));
    }

    public function test_aging_bucket_uses_outstanding_posted_ledger_balance_at_boundaries(): void
    {
        $actor = User::factory()->create(['role' => 'super_admin']);
        $sale = $this->transactionPenjualan($actor, null, [
            'syarat_pembayaran' => 'Net 30',
            'status' => 'Approved',
            'grand_total' => '100.00',
            'tgl_jatuh_tempo' => '2026-06-22',
        ]);
        $this->map($actor, MappingKey::ArReceivable, AccountCategory::Aset);
        $this->map($actor, MappingKey::SalesRetailRevenue, AccountCategory::Pendapatan);
        app(PiutangPostingService::class)->postSale($actor, $sale);

        $ledger = app(PiutangLedgerService::class);
        $this->assertSame('1-30', $ledger->agingBucketForSale($sale, new \DateTimeImmutable('2026-07-22')));
        $this->assertSame('31-60', $ledger->agingBucketForSale($sale, new \DateTimeImmutable('2026-07-23')));
    }

    private function map(User $actor, MappingKey $key, AccountCategory $category): Account
    {
        $account = Account::factory()->create(['category' => $category, 'is_active' => true, 'is_postable' => true]);
        app(AccountMappingService::class)->create($actor, $key, $account, '2026-01-01', isActive: true);

        return $account;
    }
}
