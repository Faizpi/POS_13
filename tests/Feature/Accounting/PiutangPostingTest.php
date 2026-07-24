<?php

declare(strict_types=1);

namespace Tests\Feature\Accounting;

use App\Accounting\AccountCategory;
use App\Accounting\DomainException;
use App\Accounting\MappingKey;
use App\Models\Account;
use App\Models\JournalEntry;
use App\Models\User;
use App\Services\Accounting\AccountMappingService;
use App\Services\Accounting\PiutangPostingService;
use App\Services\PaymentSettlementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\BuildsTransactionFixtures;
use Tests\TestCase;

final class PiutangPostingTest extends TestCase
{
    use BuildsTransactionFixtures;
    use RefreshDatabase;

    public function test_credit_sale_posts_ar_debit_and_revenue_credit_once(): void
    {
        $actor = User::factory()->create(['role' => 'super_admin']);
        $sale = $this->transactionPenjualan($actor, null, ['syarat_pembayaran' => 'Net 30', 'grand_total' => 100000]);
        $this->map($actor, MappingKey::ArReceivable, AccountCategory::Aset);
        $this->map($actor, MappingKey::SalesRetailRevenue, AccountCategory::Pendapatan);

        $journal = app(PiutangPostingService::class)->postSale($actor, $sale);

        $this->assertSame('posted', $journal->status->value);
        $this->assertSame('sale', $journal->source_type);
        $this->assertSame($sale->id, $journal->source_id);
        $this->assertSame('1', (string) $journal->source_version);
        $this->assertSame(['100000.00', '100000.00'], [$journal->total_debit, $journal->total_credit]);
        $this->assertCount(2, $journal->lines);
        $this->assertNotSame($journal->lines->first()->account_id, $journal->lines->last()->account_id);
        $this->assertSame($journal->id, app(PiutangPostingService::class)->postSale($actor, $sale)->id);
    }

    public function test_api_credit_sale_approval_posts_and_cancellation_reverses_atomically(): void
    {
        $superAdmin = $this->transactionUser('super_admin');
        $gudang = $this->transactionGudang();
        $admin = $this->transactionUser('admin', $gudang);
        $sale = $this->transactionPenjualan(null, $gudang, ['syarat_pembayaran' => 'Net 30', 'grand_total' => 100000]);
        $this->map($superAdmin, MappingKey::ArReceivable, AccountCategory::Aset);
        $this->map($superAdmin, MappingKey::SalesRetailRevenue, AccountCategory::Pendapatan);

        $this->postJson("/api/v1/penjualan/{$sale->id}/approve", [], $this->authHeaderFor($admin))
            ->assertOk();
        $this->assertDatabaseHas('journal_entries', [
            'source_type' => 'sale',
            'source_id' => $sale->id,
            'journal_type' => 'sale',
            'source_version' => 1,
            'status' => 'posted',
        ]);

        $cancelResponse = $this->postJson("/api/v1/penjualan/{$sale->id}/cancel", [], $this->authHeaderFor($superAdmin));
        $cancelResponse->assertOk();
        $original = JournalEntry::query()->where('source_type', 'sale')->where('source_id', $sale->id)->where('journal_type', 'sale')->where('status', 'posted')->firstOrFail();
        $this->assertDatabaseHas('journal_entries', ['original_journal_entry_id' => $original->id, 'journal_type' => 'reversal']);
        $this->assertSame('Canceled', $sale->fresh()->status);
    }

    public function test_cash_sale_is_excluded_from_ar_source_path(): void
    {
        $actor = User::factory()->create(['role' => 'super_admin']);
        $sale = $this->transactionPenjualan($actor, null, ['syarat_pembayaran' => 'Cash']);

        $this->assertNull(app(PiutangPostingService::class)->postSale($actor, $sale));
        $this->assertDatabaseCount('journal_entries', 0);
    }

    public function test_approved_ar_payment_posts_bank_debit_and_ar_credit(): void
    {
        $actor = User::factory()->create(['role' => 'super_admin']);
        $sale = $this->transactionPenjualan($actor, null, ['syarat_pembayaran' => 'Net 30', 'status' => 'Approved', 'grand_total' => 100000]);
        $payment = $this->transactionPembayaran($sale, $actor, ['status' => 'Approved', 'jumlah_bayar' => 40000, 'metode_pembayaran' => 'Transfer']);
        $this->map($actor, MappingKey::ArReceivable, AccountCategory::Aset);
        $this->map($actor, MappingKey::SalesRetailRevenue, AccountCategory::Pendapatan);
        $this->map($actor, MappingKey::BankDefault, AccountCategory::Aset);
        app(PiutangPostingService::class)->postSale($actor, $sale);

        $journal = app(PiutangPostingService::class)->postPayment($actor, $payment);

        $this->assertSame('ar_payment', $journal->journal_type->value);
        $this->assertSame($payment->id, $journal->source_id);
        $this->assertSame('40000.00', $journal->total_debit);
        $this->assertSame('40000.00', $journal->total_credit);
    }

    public function test_payment_settlement_approval_posts_and_cancellation_reverses(): void
    {
        $superAdmin = $this->transactionUser('super_admin');
        $gudang = $this->transactionGudang();
        $admin = $this->transactionUser('admin', $gudang);
        $sale = $this->transactionPenjualan(null, $gudang, ['syarat_pembayaran' => 'Net 30', 'status' => 'Approved', 'grand_total' => 100000]);
        $payment = $this->transactionPembayaran($sale, null, ['status' => 'Pending', 'jumlah_bayar' => 40000, 'metode_pembayaran' => 'Transfer']);
        $this->map($superAdmin, MappingKey::ArReceivable, AccountCategory::Aset);
        $this->map($superAdmin, MappingKey::SalesRetailRevenue, AccountCategory::Pendapatan);
        $this->map($superAdmin, MappingKey::BankDefault, AccountCategory::Aset);
        app(PiutangPostingService::class)->postSale($superAdmin, $sale);

        $approved = app(PaymentSettlementService::class)->approvePiutangPayment($payment, $admin->id);
        $paymentJournal = JournalEntry::query()
            ->where('source_type', 'payment')
            ->where('source_id', $payment->id)
            ->firstOrFail();
        $this->assertSame('Approved', $approved->status);
        $this->assertSame('40000.00', $paymentJournal->total_debit);

        app(PaymentSettlementService::class)->cancelPiutangPayment($approved);
        $this->assertNotNull($paymentJournal->fresh()->reversalJournal);
        $this->assertSame('Canceled', $payment->fresh()->status);
    }

    public function test_missing_cash_mapping_rejects_payment_without_partial_journal(): void
    {
        $actor = User::factory()->create(['role' => 'super_admin']);
        $sale = $this->transactionPenjualan($actor, null, ['syarat_pembayaran' => 'Net 30', 'status' => 'Approved']);
        $payment = $this->transactionPembayaran($sale, $actor, ['status' => 'Approved', 'metode_pembayaran' => 'Transfer']);
        $this->map($actor, MappingKey::ArReceivable, AccountCategory::Aset);
        $this->map($actor, MappingKey::SalesRetailRevenue, AccountCategory::Pendapatan);
        app(PiutangPostingService::class)->postSale($actor, $sale);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('No mapping exists for bank.default');
        app(PiutangPostingService::class)->postPayment($actor, $payment);
        $this->assertDatabaseCount('journal_entries', 1);
    }

    public function test_cancelling_credit_sale_reverses_sale_journal_and_reapproval_uses_version_two(): void
    {
        $actor = User::factory()->create(['role' => 'super_admin']);
        $sale = $this->transactionPenjualan($actor, null, ['syarat_pembayaran' => 'Net 30']);
        $this->map($actor, MappingKey::ArReceivable, AccountCategory::Aset);
        $this->map($actor, MappingKey::SalesRetailRevenue, AccountCategory::Pendapatan);
        $service = app(PiutangPostingService::class);
        $original = $service->postSale($actor, $sale);

        $reversal = $service->reverseSale($actor, $sale, 'Sale canceled');
        $reposted = $service->postSale($actor, $sale->fresh());

        $this->assertSame($original->id, $reversal->original_journal_entry_id);
        $this->assertSame(2, $reposted->source_version);
        $this->assertCount(3, JournalEntry::query()->get());
    }

    private function map(User $actor, MappingKey $key, AccountCategory $category): Account
    {
        $account = Account::factory()->create(['category' => $category, 'is_active' => true, 'is_postable' => true]);
        app(AccountMappingService::class)->create($actor, $key, $account, '2026-01-01', isActive: true);

        return $account;
    }
}
