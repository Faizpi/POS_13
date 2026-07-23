<?php

declare(strict_types=1);

namespace Tests\Feature\Filament;

use App\Accounting\AccountCategory;
use App\Accounting\DomainException;
use App\Accounting\NormalBalance;
use App\Accounting\StatementClassification;
use App\Filament\Resources\Accounts\Schemas\AccountForm;
use App\Models\Account;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use ReflectionClass;
use Tests\TestCase;

final class AccountResourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_resource_replaces_placeholder_at_the_single_daftar_akun_navigation_target(): void
    {
        $resource = $this->resourceClass();

        $this->assertSame('Akuntansi', $this->staticProperty($resource, 'navigationGroup'));
        $this->assertSame('Daftar Akun', $this->staticProperty($resource, 'navigationLabel'));
        $this->assertSame(1, $this->staticProperty($resource, 'navigationSort'));
        $this->assertSame('daftar-akun', $this->staticProperty($resource, 'slug'));
        $this->assertFalse(class_exists('App\\Filament\\Pages\\Accounting\\DaftarAkunPage'));

        $registeredResources = collect(Filament::getPanel('app')->getResources())
            ->filter(fn (string $resource): bool => $resource::getSlug() === 'daftar-akun');

        $this->assertCount(1, $registeredResources);
        $this->assertSame($resource, $registeredResources->first());
    }

    public function test_list_renders_hierarchy_and_filters_category_subcategory_and_status(): void
    {
        $listPage = $this->pageClass('ListAccounts');
        $superAdmin = User::factory()->create(['role' => 'super_admin']);
        $assetParent = $this->heading('1-1100', AccountCategory::Aset, 'Kas & Bank', 'kas');
        $assetChild = Account::factory()->withParent($assetParent)->create([
            'code' => '1-1101',
            'name' => 'Kas Operasional',
            'subcategory' => 'kas',
            'is_active' => true,
        ]);
        $liability = Account::factory()->create([
            'code' => '2-1100',
            'name' => 'Utang Usaha',
            'category' => AccountCategory::Kewajiban,
            'subcategory' => 'payable',
        ]);

        Livewire::actingAs($superAdmin)
            ->test($listPage)
            ->assertCanSeeTableRecords([$assetParent, $assetChild, $liability])
            ->filterTable('category', AccountCategory::Aset->value)
            ->assertCanSeeTableRecords([$assetParent, $assetChild])
            ->assertCanNotSeeTableRecords([$liability])
            ->filterTable('subcategory', 'kas')
            ->assertCanSeeTableRecords([$assetParent, $assetChild])
            ->filterTable('is_active', '1')
            ->assertCanSeeTableRecords([$assetChild]);
    }

    public function test_super_admin_create_uses_category_defaults_and_guarded_suggested_code(): void
    {
        $createPage = $this->pageClass('CreateAccount');
        $superAdmin = User::factory()->create(['role' => 'super_admin']);
        $parent = $this->heading('1-1100', AccountCategory::Aset, 'Kas & Bank', 'kas');

        Livewire::actingAs($superAdmin)
            ->test($createPage)
            ->fillForm([
                'category' => AccountCategory::Aset->value,
                'subcategory' => 'kas',
                'parent_id' => $parent->id,
            ])
            ->assertFormSet([
                'normal_balance' => NormalBalance::Debit->value,
                'statement_classification' => StatementClassification::Neraca->value,
                'code' => '1-1101',
            ])
            ->fillForm([
                'name' => 'Kas Operasional',
                'is_postable' => true,
                'is_control_account' => false,
                'is_active' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('accounts', [
            'code' => '1-1101',
            'name' => 'Kas Operasional',
            'parent_id' => $parent->id,
            'category' => AccountCategory::Aset->value,
            'normal_balance' => NormalBalance::Debit->value,
            'statement_classification' => StatementClassification::Neraca->value,
            'is_postable' => true,
            'is_control_account' => false,
            'is_active' => true,
        ]);
    }

    public function test_super_admin_can_use_a_valid_manual_code_override(): void
    {
        $createPage = $this->pageClass('CreateAccount');
        $superAdmin = User::factory()->create(['role' => 'super_admin']);
        $parent = $this->heading('1-1100', AccountCategory::Aset, 'Kas & Bank', 'kas');

        Livewire::actingAs($superAdmin)
            ->test($createPage)
            ->fillForm([
                'category' => AccountCategory::Aset->value,
                'subcategory' => 'kas',
                'parent_id' => $parent->id,
                'code' => '1-1117',
                'name' => 'Kas Cadangan',
                'is_postable' => true,
                'is_control_account' => false,
                'is_active' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('accounts', ['code' => '1-1117', 'name' => 'Kas Cadangan']);
    }

    public function test_super_admin_can_edit_and_deactivate_an_unused_non_system_account(): void
    {
        $editPage = $this->pageClass('EditAccount');
        $superAdmin = User::factory()->create(['role' => 'super_admin']);
        $account = Account::factory()->create(['name' => 'Beban Promosi', 'is_active' => true]);

        Livewire::actingAs($superAdmin)
            ->test($editPage, ['record' => $account->getRouteKey()])
            ->fillForm(['name' => 'Beban Promosi Nonaktif', 'is_active' => false])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('accounts', [
            'id' => $account->id,
            'name' => 'Beban Promosi Nonaktif',
            'is_active' => false,
        ]);
    }

    public function test_sales_is_denied_and_admin_and_spectator_are_read_only(): void
    {
        $resource = $this->resourceClass();
        $createPage = $this->pageClass('CreateAccount');
        $account = Account::factory()->create();
        $sales = User::factory()->create(['role' => 'user']);
        $admin = User::factory()->create(['role' => 'admin']);
        $spectator = User::factory()->create(['role' => 'spectator']);

        $this->actingAs($sales);
        $this->assertFalse($resource::canViewAny());
        Livewire::actingAs($sales)->test($createPage)->assertForbidden();

        foreach ([$admin, $spectator] as $user) {
            $this->actingAs($user);
            $this->assertTrue($resource::canViewAny());
            $this->assertFalse($resource::canCreate());
            $this->assertFalse($resource::canEdit($account));
            Livewire::actingAs($user)->test($createPage)->assertForbidden();
        }
    }

    public function test_form_rejects_cyclic_or_incompatible_parent_and_incompatible_control_account_type(): void
    {
        $createPage = $this->pageClass('CreateAccount');
        $editPage = $this->pageClass('EditAccount');
        $superAdmin = User::factory()->create(['role' => 'super_admin']);
        $assetParent = $this->heading('1-1100', AccountCategory::Aset, 'Kas & Bank', 'kas');
        $assetChild = Account::factory()->withParent($assetParent)->create([
            'code' => '1-1101',
            'subcategory' => 'kas',
        ]);
        $liabilityParent = $this->heading('2-1100', AccountCategory::Kewajiban, 'Utang', 'payable');

        Livewire::actingAs($superAdmin)
            ->test($editPage, ['record' => $assetParent->getRouteKey()])
            ->fillForm(['parent_id' => $assetChild->id])
            ->call('save')
            ->assertHasFormErrors(['parent_id']);

        Livewire::actingAs($superAdmin)
            ->test($createPage)
            ->fillForm([
                'category' => AccountCategory::Aset->value,
                'subcategory' => 'kas',
                'parent_id' => $liabilityParent->id,
                'name' => 'Parent Salah',
                'is_postable' => true,
                'is_control_account' => false,
                'is_active' => true,
            ])
            ->call('create')
            ->assertHasFormErrors(['parent_id']);

        Livewire::actingAs($superAdmin)
            ->test($createPage)
            ->fillForm([
                'category' => AccountCategory::Aset->value,
                'subcategory' => 'kas',
                'parent_id' => $assetParent->id,
                'name' => 'Kas Control Tidak Valid',
                'is_postable' => true,
                'is_control_account' => true,
                'is_active' => true,
            ])
            ->call('create')
            ->assertHasFormErrors(['is_control_account']);
    }

    public function test_used_or_system_accounts_cannot_be_deleted_and_resource_has_no_destructive_delete_action(): void
    {
        $resource = $this->resourceClass();
        $used = Account::factory()->create(['is_used' => true]);
        $system = Account::factory()->system()->create();

        $this->assertFalse($resource::canDelete($used));
        $this->assertFalse($resource::canDelete($system));

        foreach ([$used, $system] as $account) {
            try {
                $account->delete();
                $this->fail('Protected account deletion must remain blocked by the model guard.');
            } catch (DomainException) {
                $this->assertDatabaseHas('accounts', ['id' => $account->id]);
            }
        }
    }

    public function test_aset_subcategories_do_not_offer_prohibited_fixed_asset_or_depreciation_options(): void
    {
        $options = AccountForm::subcategoryOptions(AccountCategory::Aset->value);

        $this->assertArrayNotHasKey('fixed_asset', $options);
        $this->assertArrayNotHasKey('accumulated_depreciation', $options);
    }

    public function test_suggestion_clears_expected_domain_errors_but_surfaces_unexpected_failures(): void
    {
        $invalidParent = Account::factory()->heading()->create([
            'code' => '9-9999',
            'category' => AccountCategory::Aset,
        ]);

        $this->assertNull(AccountForm::suggestedCode(AccountCategory::Aset, $invalidParent));

        $invalidParent->setRawAttributes(['code' => ['unexpected'], 'category' => AccountCategory::Aset->value]);

        $this->expectException(\TypeError::class);

        AccountForm::suggestedCode(AccountCategory::Aset, $invalidParent);
    }

    /** @return class-string */
    private function resourceClass(): string
    {
        $class = 'App\\Filament\\Resources\\Accounts\\AccountResource';
        $this->assertTrue(class_exists($class), 'Daftar Akun Filament resource is missing.');

        return $class;
    }

    /** @return class-string */
    private function pageClass(string $page): string
    {
        $this->resourceClass();
        $class = 'App\\Filament\\Resources\\Accounts\\Pages\\'.$page;
        $this->assertTrue(class_exists($class), "Daftar Akun {$page} page is missing.");

        return $class;
    }

    /** @param class-string $class */
    private function staticProperty(string $class, string $property): mixed
    {
        $reflection = new ReflectionClass($class);
        $propertyReflection = $reflection->getProperty($property);
        $propertyReflection->setAccessible(true);

        return $propertyReflection->getValue();
    }

    private function heading(string $code, AccountCategory $category, string $name, ?string $subcategory = null): Account
    {
        return Account::factory()->heading()->create([
            'code' => $code,
            'category' => $category,
            'name' => $name,
            'subcategory' => $subcategory,
        ]);
    }
}
