<?php

declare(strict_types=1);

namespace Tests\Feature\Accounting;

use App\Accounting\AccountCategory;
use App\Accounting\MappingKey;
use App\Models\Account;
use App\Models\JournalEntry;
use App\Models\User;
use App\Services\Accounting\AccountMappingService;
use App\Services\Accounting\HutangPostingService;
use App\Services\PaymentSettlementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\BuildsTransactionFixtures;
use Tests\TestCase;

final class HutangPostingTest extends TestCase
{
    use BuildsTransactionFixtures;
    use RefreshDatabase;

    public function test_credit_purchase_posts_purchase_debit_and_ap_credit_once(): void
    {
        $actor = User::factory()->create(['role' => 'super_admin']);
        $purchase = $this->transactionPembelian($actor, null, ['syarat_pembayaran' => 'Net 30', 'grand_total' => 100000]);
        $this->map($actor, MappingKey::PurchaseInventory, AccountCategory::Aset);
        $this->map($actor, MappingKey::ApPayable, AccountCategory::Kewajiban);

        $journal = app(HutangPostingService::class)->postPurchase($actor, $purchase);

        $this->assertSame('purchase', $journal->journal_type->value);
        $this->assertSame($purchase->id, $journal->source_id);
        $this->assertSame('100000.00', $journal->total_debit);
        $this->assertSame('100000.00', $journal->total_credit);
        $this->assertSame($journal->id, app(HutangPostingService::class)->postPurchase($actor, $purchase)->id);
    }

    public function test_api_credit_purchase_approval_posts_and_cancellation_reverses(): void
    {
        $superAdmin = $this->transactionUser('super_admin');
        $gudang = $this->transactionGudang();
        $admin = $this->transactionUser('admin', $gudang);
        $purchase = $this->transactionPembelian(null, $gudang, ['syarat_pembayaran' => 'Net 30', 'grand_total' => 100000]);
        $this->map($superAdmin, MappingKey::PurchaseInventory, AccountCategory::Aset);
        $this->map($superAdmin, MappingKey::ApPayable, AccountCategory::Kewajiban);

        $this->postJson("/api/v1/pembelian/{$purchase->id}/approve", [], $this->authHeaderFor($admin))->assertOk();
        $original = JournalEntry::query()
            ->where('source_type', 'purchase')
            ->where('source_id', $purchase->id)
            ->where('journal_type', 'purchase')
            ->where('status', 'posted')
            ->firstOrFail();

        $this->postJson("/api/v1/pembelian/{$purchase->id}/cancel", [], $this->authHeaderFor($superAdmin))->assertOk();
        $this->assertDatabaseHas('journal_entries', [
            'original_journal_entry_id' => $original->id,
            'journal_type' => 'reversal',
        ]);
        $this->assertSame('Canceled', $purchase->fresh()->status);
    }

    public function test_cash_purchase_is_excluded_from_ap_source_path(): void
    {
        $actor = User::factory()->create(['role' => 'super_admin']);
        $purchase = $this->transactionPembelian($actor, null, ['syarat_pembayaran' => 'Cash']);

        $this->assertNull(app(HutangPostingService::class)->postPurchase($actor, $purchase));
        $this->assertDatabaseCount('journal_entries', 0);
    }

    public function test_approved_ap_payment_posts_ap_debit_and_bank_credit(): void
    {
        $actor = User::factory()->create(['role' => 'super_admin']);
        $purchase = $this->transactionPembelian($actor, null, ['syarat_pembayaran' => 'Net 30', 'status' => 'Approved', 'grand_total' => 100000]);
        $payment = $this->transactionPembayaranHutang($purchase, $actor, ['status' => 'Approved', 'jumlah_bayar' => 40000, 'metode_pembayaran' => 'Transfer']);
        $this->map($actor, MappingKey::PurchaseInventory, AccountCategory::Aset);
        $this->map($actor, MappingKey::ApPayable, AccountCategory::Kewajiban);
        $this->map($actor, MappingKey::BankDefault, AccountCategory::Aset);
        app(HutangPostingService::class)->postPurchase($actor, $purchase);

        $journal = app(HutangPostingService::class)->postPayment($actor, $payment);

        $this->assertSame('ap_payment', $journal->journal_type->value);
        $this->assertSame('40000.00', $journal->total_debit);
        $this->assertSame('40000.00', $journal->total_credit);
    }

    public function test_payment_settlement_approval_posts_and_cancellation_reverses_ap_payment(): void
    {
        $superAdmin = $this->transactionUser('super_admin');
        $gudang = $this->transactionGudang();
        $admin = $this->transactionUser('admin', $gudang);
        $purchase = $this->transactionPembelian(null, $gudang, ['syarat_pembayaran' => 'Net 30', 'status' => 'Approved', 'grand_total' => 100000]);
        $payment = $this->transactionPembayaranHutang($purchase, null, ['status' => 'Pending', 'jumlah_bayar' => 40000, 'metode_pembayaran' => 'Transfer']);
        $this->map($superAdmin, MappingKey::PurchaseInventory, AccountCategory::Aset);
        $this->map($superAdmin, MappingKey::ApPayable, AccountCategory::Kewajiban);
        $this->map($superAdmin, MappingKey::BankDefault, AccountCategory::Aset);
        app(HutangPostingService::class)->postPurchase($superAdmin, $purchase);

        $approved = app(PaymentSettlementService::class)->approveHutangPayment($payment, $admin->id);
        $journal = JournalEntry::query()
            ->where('source_type', 'payment')
            ->where('source_id', $payment->id)
            ->where('journal_type', 'ap_payment')
            ->firstOrFail();
        $this->assertSame('Approved', $approved->status);
        $this->assertSame('40000.00', $journal->total_debit);

        app(PaymentSettlementService::class)->cancelHutangPayment($approved);
        $this->assertDatabaseHas('journal_entries', [
            'original_journal_entry_id' => $journal->id,
            'journal_type' => 'reversal',
        ]);
        $this->assertSame('Canceled', $payment->fresh()->status);
    }

    public function test_purchase_cancellation_reverses_and_reapproval_uses_next_source_version(): void
    {
        $actor = User::factory()->create(['role' => 'super_admin']);
        $purchase = $this->transactionPembelian($actor, null, ['syarat_pembayaran' => 'Net 30']);
        $this->map($actor, MappingKey::PurchaseInventory, AccountCategory::Aset);
        $this->map($actor, MappingKey::ApPayable, AccountCategory::Kewajiban);
        $service = app(HutangPostingService::class);
        $original = $service->postPurchase($actor, $purchase);

        $reversal = $service->reversePurchase($actor, $purchase, 'Purchase canceled');
        $reposted = $service->postPurchase($actor, $purchase->fresh());

        $this->assertSame($original->id, $reversal->original_journal_entry_id);
        $this->assertSame(2, $reposted->source_version);
    }

    private function map(User $actor, MappingKey $key, AccountCategory $category): Account
    {
        $account = Account::factory()->create(['category' => $category, 'is_active' => true, 'is_postable' => true]);
        app(AccountMappingService::class)->create($actor, $key, $account, '2026-01-01', isActive: true);

        return $account;
    }
}
