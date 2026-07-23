<?php

declare(strict_types=1);

namespace Tests\Feature\Filament;

use App\Accounting\AccountCategory;
use App\Accounting\MappingKey;
use App\Filament\Pages\Accounting\PemetaanAkunPage;
use App\Models\Account;
use App\Models\AccountMapping;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

final class AccountMappingPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_replaces_the_placeholder_at_the_single_pemetaan_akun_navigation_target(): void
    {
        $registeredPages = collect(Filament::getPanel('app')->getPages())
            ->filter(fn (string $page): bool => $page::getSlug() === 'pemetaan-akun');

        $this->assertCount(1, $registeredPages);
        $this->assertSame(PemetaanAkunPage::class, $registeredPages->first());
        $this->assertNotSame('filament.pages.reports.placeholder', $this->viewProperty());
    }

    public function test_super_admin_sees_the_seven_grouped_mapping_sections_and_protected_lock_indicator(): void
    {
        $superAdmin = User::factory()->superAdmin()->create();
        AccountMapping::query()->create([
            'mapping_key' => MappingKey::SalesRetailRevenue,
            'section' => 'Penjualan',
            'account_id' => $this->account(AccountCategory::Pendapatan)->id,
            'effective_from' => '2026-07-01',
            'is_active' => true,
            'is_protected' => true,
            'changed_by' => $superAdmin->id,
        ]);

        Livewire::actingAs($superAdmin)
            ->test(PemetaanAkunPage::class)
            ->assertSee('Penjualan')
            ->assertSee('Pembelian')
            ->assertSee('AR / AP')
            ->assertSee('Kas & Bank')
            ->assertSee('Persediaan')
            ->assertSee('Biaya')
            ->assertSee('Ekuitas & Lainnya')
            ->assertSee('Pendapatan Penjualan Retail')
            ->assertSee('Terkunci');
    }

    public function test_sales_is_denied_while_admin_and_spectator_can_view_read_only_mappings(): void
    {
        $sales = User::factory()->sales()->create();
        $admin = User::factory()->admin()->create();
        $spectator = User::factory()->spectator()->create();

        Livewire::actingAs($sales)->test(PemetaanAkunPage::class)->assertForbidden();

        foreach ([$admin, $spectator] as $user) {
            Livewire::actingAs($user)->test(PemetaanAkunPage::class)
                ->assertOk()
                ->assertActionHidden('saveMappings')
                ->assertActionHidden('createAccount');
        }
    }

    public function test_creating_an_account_from_the_mapping_action_preserves_the_current_draft_and_selects_the_new_account(): void
    {
        $superAdmin = User::factory()->superAdmin()->create();
        $revenue = $this->account(AccountCategory::Pendapatan);
        $receivableHeading = $this->heading('1-1200', AccountCategory::Aset, 'Piutang', 'receivable');

        $component = Livewire::actingAs($superAdmin)
            ->test(PemetaanAkunPage::class)
            ->fillForm([
                'mappings' => [
                    MappingKey::SalesRetailRevenue->formStateKey() => [
                        'account_id' => $revenue->id,
                        'effective_from' => '2026-07-01',
                        'effective_to' => null,
                        'is_active' => true,
                    ],
                    MappingKey::ArReceivable->formStateKey() => [
                        'effective_from' => '2026-07-01',
                        'effective_to' => null,
                        'is_active' => true,
                    ],
                ],
            ])
            ->call('createAccountForMapping', MappingKey::ArReceivable->formStateKey(), [
                'category' => AccountCategory::Aset->value,
                'subcategory' => 'receivable',
                'parent_id' => $receivableHeading->id,
                'name' => 'Piutang Marketplace',
                'is_postable' => true,
                'is_control_account' => true,
                'is_active' => true,
            ])
            ->assertHasNoErrors();

        $created = Account::query()->where('name', 'Piutang Marketplace')->firstOrFail();

        $component
            ->assertFormSet([
                'mappings.'.MappingKey::SalesRetailRevenue->formStateKey().'.account_id' => $revenue->id,
                'mappings.'.MappingKey::ArReceivable->formStateKey().'.account_id' => $created->id,
            ]);

        $this->assertDatabaseHas('accounts', [
            'id' => $created->id,
            'parent_id' => $receivableHeading->id,
            'category' => AccountCategory::Aset->value,
            'subcategory' => 'receivable',
            'is_control_account' => true,
        ]);
    }

    private function viewProperty(): string
    {
        $reflection = new \ReflectionClass(PemetaanAkunPage::class);
        $property = $reflection->getProperty('view');
        $property->setAccessible(true);

        return $property->getValue(new PemetaanAkunPage);
    }

    private function account(AccountCategory $category): Account
    {
        return Account::factory()->create([
            'category' => $category,
            'normal_balance' => $category->normalBalance(),
            'statement_classification' => $category->statementClassification(),
            'is_active' => true,
            'is_postable' => true,
        ]);
    }

    private function heading(string $code, AccountCategory $category, string $name, string $subcategory): Account
    {
        return Account::factory()->heading()->create([
            'code' => $code,
            'category' => $category,
            'subcategory' => $subcategory,
            'name' => $name,
        ]);
    }
}
