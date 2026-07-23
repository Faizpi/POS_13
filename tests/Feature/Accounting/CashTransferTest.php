<?php

declare(strict_types=1);

namespace Tests\Feature\Accounting;

use App\Accounting\AccountCategory;
use App\Accounting\CashAccountType;
use App\Accounting\DomainException;
use App\Accounting\JournalStatus;
use App\Accounting\JournalType;
use App\Accounting\LineOrder;
use App\Accounting\MappingKey;
use App\Accounting\Money;
use App\Accounting\SourceIdentity;
use App\Filament\Pages\Accounting\TransferKasPage;
use App\Models\Account;
use App\Models\CashBankAccount;
use App\Models\Gudang;
use App\Models\User;
use App\Services\Accounting\AccountMappingService;
use App\Services\Accounting\CashTransferService;
use App\Services\Accounting\LedgerPersistenceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

final class CashTransferTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_posts_direct_transfer_with_opposite_cash_lines_and_traceable_reference(): void
    {
        $actor = User::factory()->create(['role' => 'super_admin']);
        $source = $this->cashAccount('Kas Jakarta');
        $destination = $this->cashAccount('Kas Palembang');
        $this->fund($source, '3000000.00');

        $transfer = app(CashTransferService::class)->transfer($actor, $source, $destination, '2000000.00', 'direct', 'Operational transfer');

        $this->assertSame('posted', $transfer->status);
        $this->assertStringStartsWith('TRF-', $transfer->transfer_number);
        $this->assertSame($source->id, $transfer->source_cash_bank_account_id);
        $this->assertSame($destination->id, $transfer->destination_cash_bank_account_id);
        $this->assertSame('2000000.00', $transfer->amount);
        $this->assertSame(1, $transfer->journals()->count());
        $journal = $transfer->journals()->firstOrFail()->load('lines');
        $this->assertSame('cash_transfer', $journal->journal_type->value);
        $this->assertSame('2000000.00', $journal->total_debit);
        $this->assertSame('2000000.00', $journal->total_credit);
        $this->assertSame($destination->account_id, $journal->lines->firstWhere('debit', '2000000.00')->account_id);
        $this->assertSame($source->account_id, $journal->lines->firstWhere('credit', '2000000.00')->account_id);
    }

    public function test_admin_initiates_pending_transfer_from_active_warehouse_and_super_admin_posts_it(): void
    {
        $source = $this->cashAccount('Kas Jakarta');
        $destination = $this->cashAccount('Kas Palembang');
        $admin = User::factory()->create([
            'role' => 'admin',
            'gudang_id' => $source->gudang_id,
            'current_gudang_id' => $source->gudang_id,
        ]);
        $admin->gudangs()->attach($source->gudang_id);
        $superAdmin = User::factory()->create(['role' => 'super_admin']);
        $this->fund($source, '500.00');

        $pending = app(CashTransferService::class)->initiate($admin, $source, $destination, '250.00', 'direct', 'Needs approval');

        $this->assertSame('pending', $pending->status);
        $this->assertSame(0, $pending->journals()->count());
        $posted = app(CashTransferService::class)->approve($superAdmin, $pending);
        $this->assertSame('posted', $posted->status);
        $this->assertSame(1, $posted->journals()->count());
    }

    public function test_admin_can_initiate_pending_transfer_from_transfer_kas_page(): void
    {
        $source = $this->cashAccount('Kas Jakarta');
        $destination = $this->cashAccount('Kas Palembang');
        $admin = User::factory()->create([
            'role' => 'admin',
            'gudang_id' => $source->gudang_id,
            'current_gudang_id' => $source->gudang_id,
        ]);
        $admin->gudangs()->attach($source->gudang_id);

        Livewire::actingAs($admin)
            ->test(TransferKasPage::class)
            ->set('sourceCashBankAccountId', $source->id)
            ->set('destinationCashBankAccountId', $destination->id)
            ->set('amount', '25.00')
            ->set('mode', 'direct')
            ->set('memo', 'UI initiated')
            ->call('initiateTransfer')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('cash_transfers', [
            'source_cash_bank_account_id' => $source->id,
            'destination_cash_bank_account_id' => $destination->id,
            'status' => 'pending',
            'amount' => '25.00',
        ]);
        $this->assertDatabaseCount('journal_entries', 0);
    }

    public function test_transfer_numbers_allocate_from_locked_sequence_not_count_plus_one(): void
    {
        $actor = User::factory()->create(['role' => 'super_admin']);
        $source = $this->cashAccount('Kas Jakarta');
        $destination = $this->cashAccount('Kas Palembang');
        $this->fund($source, '100.00');

        $first = app(CashTransferService::class)->transfer($actor, $source, $destination, '25.00', 'direct', null);
        $second = app(CashTransferService::class)->transfer($actor, $source, $destination, '25.00', 'direct', null);

        $datePrefix = now()->format('Ymd');
        $this->assertSame("TRF-{$datePrefix}-000001", $first->transfer_number);
        $this->assertSame("TRF-{$datePrefix}-000002", $second->transfer_number);
        $this->assertSame(2, (int) DB::table('cash_transfer_sequences')->where('sequence_key', 'cash_transfer')->value('last_value'));
    }

    public function test_in_transit_send_then_receive_posts_transit_then_destination(): void
    {
        $actor = User::factory()->create(['role' => 'super_admin']);
        $source = $this->cashAccount('Kas Jakarta');
        $destination = $this->cashAccount('Kas Palembang');
        $this->fund($source, '300.00');
        $transit = Account::factory()->create([
            'category' => AccountCategory::Aset,
            'is_active' => true,
            'is_postable' => true,
        ]);
        app(AccountMappingService::class)->create($actor, MappingKey::CashInTransit, $transit, '2026-01-01', isActive: true);

        $transfer = app(CashTransferService::class)->transfer($actor, $source, $destination, '250.00', 'in_transit', 'Two-stage transfer');

        $this->assertSame('in_transit', $transfer->status);
        $this->assertSame(1, $transfer->journals()->count());
        $this->assertStringContainsString('Menunggu diterima dari Kas Jakarta', app(CashTransferService::class)->destinationStatusDescription($transfer));

        $received = app(CashTransferService::class)->receive($actor, $transfer);
        $this->assertSame('posted', $received->status);
        $this->assertSame(2, $received->journals()->count());
        $this->assertSame('Diterima dari Kas Jakarta', app(CashTransferService::class)->destinationStatusDescription($received));

        $this->expectException(DomainException::class);
        app(CashTransferService::class)->receive($actor, $received);
    }

    public function test_cancel_direct_transfer_creates_linked_reversal_without_mutating_original(): void
    {
        $actor = User::factory()->create(['role' => 'super_admin']);
        $source = $this->cashAccount('Kas Jakarta');
        $destination = $this->cashAccount('Kas Palembang');
        $this->fund($source, '500.00');
        $transfer = app(CashTransferService::class)->transfer($actor, $source, $destination, '250.00', 'direct', 'Cancelable');
        $original = $transfer->journals()->firstOrFail();

        $canceled = app(CashTransferService::class)->cancel($actor, $transfer, 'Transfer canceled');

        $this->assertSame('canceled', $canceled->status);
        $this->assertSame('posted', $original->fresh()->status->value);
        $this->assertDatabaseHas('journal_entries', [
            'original_journal_entry_id' => $original->id,
            'journal_type' => 'reversal',
        ]);
    }

    public function test_insufficient_source_balance_leaves_no_transfer_or_journal(): void
    {
        $actor = User::factory()->create(['role' => 'super_admin']);
        $source = $this->cashAccount('Kas Jakarta');
        $destination = $this->cashAccount('Kas Palembang');
        $this->fund($source, '1.00');

        try {
            app(CashTransferService::class)->transfer($actor, $source, $destination, '1.01', 'direct', null);
            $this->fail('Expected insufficient balance rejection.');
        } catch (DomainException $exception) {
            $this->assertSame('Source cash account has insufficient posted balance.', $exception->getMessage());
        }

        $this->assertDatabaseCount('cash_transfers', 0);
        $this->assertDatabaseCount('journal_entries', 1);
    }

    public function test_same_account_and_unauthorized_actor_are_rejected_without_partial_transfer(): void
    {
        $source = $this->cashAccount('Kas Jakarta');
        $unauthorized = User::factory()->create(['role' => 'spectator']);

        $this->expectException(DomainException::class);
        app(CashTransferService::class)->transfer($unauthorized, $source, $source, '1.00', 'direct', null);
    }

    private function fund(CashBankAccount $cashAccount, string $amount): void
    {
        $equity = Account::factory()->create([
            'category' => AccountCategory::Ekuitas,
            'is_active' => true,
            'is_postable' => true,
        ]);
        $money = Money::fromDecimalString($amount);
        $journal = app(LedgerPersistenceService::class)->persist(
            new SourceIdentity('cash_funding', $cashAccount->id, JournalType::CashTransfer, 1),
            '2026-07-22',
            'Cash funding fixture',
            $cashAccount->gudang_id,
            null,
            null,
            [
                ['account_id' => $cashAccount->account_id, 'line_order' => new LineOrder(10), 'debit' => $money, 'credit' => null],
                ['account_id' => $equity->id, 'line_order' => new LineOrder(20), 'debit' => null, 'credit' => $money],
            ],
        );
        $journal->update(['status' => JournalStatus::Approved]);
        $journal->update(['status' => JournalStatus::Posted]);
    }

    private function cashAccount(string $name): CashBankAccount
    {
        $gudang = Gudang::create(['nama_gudang' => $name, 'alamat_gudang' => 'Jl. Test']);
        $account = Account::factory()->create([
            'category' => AccountCategory::Aset,
            'subcategory' => CashAccountType::Kas->value,
            'is_active' => true,
            'is_postable' => true,
        ]);

        return CashBankAccount::create([
            'name' => $name,
            'type' => CashAccountType::Kas,
            'account_id' => $account->id,
            'gudang_id' => $gudang->id,
            'is_active' => true,
        ]);
    }
}
