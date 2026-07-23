<?php

declare(strict_types=1);

namespace Tests\Feature\Accounting;

use App\Accounting\AccountCategory;
use App\Accounting\MappingKey;
use App\Models\Account;
use App\Models\User;
use App\Services\Accounting\AccountMappingService;
use App\Services\Accounting\HutangLedgerService;
use App\Services\Accounting\HutangPostingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\BuildsTransactionFixtures;
use Tests\TestCase;

final class HutangAgingTest extends TestCase
{
    use BuildsTransactionFixtures;
    use RefreshDatabase;

    public function test_posted_credit_purchase_and_payment_use_liability_signs_for_running_balance(): void
    {
        $actor = User::factory()->create(['role' => 'super_admin']);
        $purchase = $this->transactionPembelian($actor, null, [
            'syarat_pembayaran' => 'Net 30',
            'status' => 'Approved',
            'grand_total' => '1000000.00',
        ]);
        $payment = $this->transactionPembayaranHutang($purchase, $actor, [
            'status' => 'Approved',
            'jumlah_bayar' => '400000.00',
            'metode_pembayaran' => 'Cash',
        ]);
        $this->map($actor, MappingKey::PurchaseInventory, AccountCategory::Aset);
        $this->map($actor, MappingKey::ApPayable, AccountCategory::Kewajiban);
        $this->map($actor, MappingKey::CashDefault, AccountCategory::Aset);
        app(HutangPostingService::class)->postPurchase($actor, $purchase);
        app(HutangPostingService::class)->postPayment($actor, $payment);

        $mutations = app(HutangLedgerService::class)->mutationsForPurchase($purchase);

        $this->assertCount(2, $mutations);
        $this->assertSame('0.00', $mutations[0]['debit']);
        $this->assertSame('1000000.00', $mutations[0]['credit']);
        $this->assertSame('1000000.00', $mutations[0]['running_balance']);
        $this->assertSame('400000.00', $mutations[1]['debit']);
        $this->assertSame('0.00', $mutations[1]['credit']);
        $this->assertSame('600000.00', $mutations[1]['running_balance']);
        $this->assertSame('600000.00', app(HutangLedgerService::class)->outstandingForPurchase($purchase));
    }

    public function test_aging_bucket_uses_outstanding_liability_at_boundaries(): void
    {
        $actor = User::factory()->create(['role' => 'super_admin']);
        $purchase = $this->transactionPembelian($actor, null, [
            'syarat_pembayaran' => 'Net 30',
            'status' => 'Approved',
            'grand_total' => '100.00',
            'tgl_jatuh_tempo' => '2026-06-22',
        ]);
        $this->map($actor, MappingKey::PurchaseInventory, AccountCategory::Aset);
        $this->map($actor, MappingKey::ApPayable, AccountCategory::Kewajiban);
        app(HutangPostingService::class)->postPurchase($actor, $purchase);

        $ledger = app(HutangLedgerService::class);
        $this->assertSame('1-30', $ledger->agingBucketForPurchase($purchase, new \DateTimeImmutable('2026-07-22')));
        $this->assertSame('31-60', $ledger->agingBucketForPurchase($purchase, new \DateTimeImmutable('2026-07-23')));
    }

    private function map(User $actor, MappingKey $key, AccountCategory $category): Account
    {
        $account = Account::factory()->create(['category' => $category, 'is_active' => true, 'is_postable' => true]);
        app(AccountMappingService::class)->create($actor, $key, $account, '2026-01-01', isActive: true);

        return $account;
    }
}
