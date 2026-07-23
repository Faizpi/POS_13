<?php

declare(strict_types=1);

namespace Tests\Feature\Filament;

use App\Accounting\AccountCategory;
use App\Accounting\MappingKey;
use App\Filament\Pages\HutangPage;
use App\Models\Account;
use App\Models\User;
use App\Services\Accounting\AccountMappingService;
use App\Services\Accounting\HutangPostingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Support\BuildsTransactionFixtures;
use Tests\TestCase;

final class HutangLedgerPageTest extends TestCase
{
    use BuildsTransactionFixtures;
    use RefreshDatabase;

    public function test_dashboard_exposes_posted_ap_credit_mutation_with_running_balance_and_linkage(): void
    {
        $actor = User::factory()->create(['role' => 'super_admin']);
        $purchase = $this->transactionPembelian($actor, null, [
            'syarat_pembayaran' => 'Net 30',
            'status' => 'Approved',
            'grand_total' => '1000000.00',
        ]);
        $this->map($actor, MappingKey::PurchaseInventory, AccountCategory::Aset);
        $this->map($actor, MappingKey::ApPayable, AccountCategory::Kewajiban);
        app(HutangPostingService::class)->postPurchase($actor, $purchase);

        $mutations = Livewire::actingAs($actor)
            ->test(HutangPage::class)
            ->instance()
            ->getLedgerMutationsForPurchase($purchase);

        $this->assertCount(1, $mutations);
        $this->assertSame('0.00', $mutations[0]['debit']);
        $this->assertSame('1000000.00', $mutations[0]['credit']);
        $this->assertSame('1000000.00', $mutations[0]['running_balance']);
        $this->assertNotNull($mutations[0]['journal_id']);
        $this->assertSame('purchase', $mutations[0]['source_type']);
        $this->assertSame($purchase->id, $mutations[0]['source_id']);
    }

    private function map(User $actor, MappingKey $key, AccountCategory $category): Account
    {
        $account = Account::factory()->create(['category' => $category, 'is_active' => true, 'is_postable' => true]);
        app(AccountMappingService::class)->create($actor, $key, $account, '2026-01-01', isActive: true);

        return $account;
    }
}
