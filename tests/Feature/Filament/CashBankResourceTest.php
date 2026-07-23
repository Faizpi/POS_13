<?php

declare(strict_types=1);

namespace Tests\Feature\Filament;

use App\Accounting\AccountCategory;
use App\Accounting\CashAccountType;
use App\Filament\Resources\CashBankAccounts\CashBankAccountResource;
use App\Filament\Resources\CashBankAccounts\Pages\ListCashBankAccounts;
use App\Models\Account;
use App\Models\CashBankAccount;
use App\Models\Gudang;
use App\Models\User;
use App\Services\Accounting\AccountingAuthorization;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use ReflectionClass;
use stdClass;
use Tests\TestCase;

class CashBankResourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_cash_bank_resource_replaces_the_placeholder_without_duplicate_navigation(): void
    {
        $resource = $this->resourceClass();

        $this->assertSame('Akuntansi', $this->staticProperty($resource, 'navigationGroup'));
        $this->assertSame('Kas & Bank', $this->staticProperty($resource, 'navigationLabel'));
        $this->assertSame(3, $this->staticProperty($resource, 'navigationSort'));
        $this->assertSame('kas-bank', $this->staticProperty($resource, 'slug'));
        $this->assertFalse(class_exists('App\\Filament\\Pages\\Accounting\\KasBankPage'), 'Todo-4 placeholder must be retired to prevent a duplicate route/navigation item.');

        $registeredResources = collect(Filament::getPanel('app')->getResources())
            ->filter(fn (string $resource): bool => $resource::getSlug() === 'kas-bank');

        $this->assertCount(1, $registeredResources);
        $this->assertSame(CashBankAccountResource::class, $registeredResources->first());
    }

    public function test_super_admin_can_create_edit_and_deactivate_a_cash_bank_master(): void
    {
        $createPage = $this->pageClass('CreateCashBankAccount');
        $editPage = $this->pageClass('EditCashBankAccount');
        $superAdmin = User::factory()->create(['role' => 'super_admin']);
        $gudang = Gudang::create(['nama_gudang' => 'Gudang Filament', 'alamat_gudang' => 'Jl. Filament']);
        $account = $this->compatibleAccount(CashAccountType::Bank);

        Livewire::actingAs($superAdmin)
            ->test($createPage)
            ->fillForm([
                'name' => 'Mandiri Operasional',
                'type' => CashAccountType::Bank->value,
                'account_id' => $account->id,
                'gudang_id' => $gudang->id,
                'bank_name' => 'Mandiri',
                'bank_account_number' => '9876543210',
                'bank_account_holder' => 'PT Hibiscus Efsya',
                'is_active' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $record = $this->cashBankModelClass()::query()->firstOrFail();
        $this->assertSame($gudang->id, $record->gudang_id);

        Livewire::actingAs($superAdmin)
            ->test($editPage, ['record' => $record->getRouteKey()])
            ->fillForm(['name' => 'Mandiri Nonaktif', 'is_active' => false])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('cash_bank_accounts', [
            'id' => $record->id,
            'name' => 'Mandiri Nonaktif',
            'is_active' => false,
        ]);
    }

    public function test_resource_rejects_incompatible_or_inactive_account_and_inactive_warehouse(): void
    {
        $createPage = $this->pageClass('CreateCashBankAccount');
        $superAdmin = User::factory()->create(['role' => 'super_admin']);
        $inactiveAccount = $this->compatibleAccount(CashAccountType::Kas, ['is_active' => false]);
        $inactiveGudang = Gudang::create([
            'nama_gudang' => 'Gudang Tutup',
            'alamat_gudang' => 'Jl. Tutup',
            'is_active' => false,
        ]);

        Livewire::actingAs($superAdmin)
            ->test($createPage)
            ->fillForm([
                'name' => 'Kas Tidak Valid',
                'type' => CashAccountType::Kas->value,
                'account_id' => $inactiveAccount->id,
                'gudang_id' => $inactiveGudang->id,
                'is_active' => true,
            ])
            ->call('create')
            ->assertHasFormErrors(['account_id', 'gudang_id']);
    }

    public function test_only_super_admin_can_mutate_and_admin_scope_cannot_leak_into_configuration(): void
    {
        $resource = $this->resourceClass();
        $createPage = $this->pageClass('CreateCashBankAccount');
        $gudangA = Gudang::create(['nama_gudang' => 'Gudang A', 'alamat_gudang' => 'Jl. A']);
        $gudangB = Gudang::create(['nama_gudang' => 'Gudang B', 'alamat_gudang' => 'Jl. B']);
        $admin = User::factory()->create(['role' => 'admin', 'gudang_id' => $gudangA->id]);
        $spectator = User::factory()->create(['role' => 'spectator', 'gudang_id' => $gudangA->id]);
        $sales = User::factory()->create(['role' => 'user', 'gudang_id' => $gudangA->id]);

        $this->actingAs($admin);
        $this->assertTrue($resource::canViewAny());
        $this->assertFalse($resource::canCreate());
        $this->assertFalse($resource::canEdit(new stdClass));

        Livewire::actingAs($admin)
            ->test($createPage)
            ->assertForbidden();

        $this->actingAs($spectator);
        $this->assertTrue($resource::canViewAny());
        $this->assertFalse($resource::canCreate());

        $this->actingAs($sales);
        $this->assertFalse($resource::canViewAny());
        $this->assertFalse($resource::canCreate());
        $this->assertFalse(app(AccountingAuthorization::class)->canInitiateCashOperation($admin, $gudangB->id));
    }

    public function test_admin_and_spectator_table_queries_only_show_their_current_warehouse_and_global_masters(): void
    {
        $gudangA = Gudang::create(['nama_gudang' => 'Gudang A', 'alamat_gudang' => 'Jl. A']);
        $gudangB = Gudang::create(['nama_gudang' => 'Gudang B', 'alamat_gudang' => 'Jl. B']);
        $warehouseARecord = $this->cashBankAccountFor('Kas Gudang A', $gudangA);
        $warehouseBRecord = $this->cashBankAccountFor('Kas Gudang B', $gudangB);
        $globalRecord = $this->cashBankAccountFor('Bank Global', null, CashAccountType::Bank);
        $admin = User::factory()->create([
            'role' => 'admin',
            'gudang_id' => $gudangA->id,
            'current_gudang_id' => $gudangA->id,
        ]);
        $admin->gudangs()->attach($gudangA);
        $spectator = User::factory()->create([
            'role' => 'spectator',
            'gudang_id' => $gudangA->id,
            'current_gudang_id' => $gudangA->id,
        ]);
        $spectator->spectatorGudangs()->attach($gudangA);

        foreach ([$admin, $spectator] as $user) {
            Livewire::actingAs($user)
                ->test(ListCashBankAccounts::class)
                ->assertCanSeeTableRecords([$warehouseARecord, $globalRecord])
                ->assertCanNotSeeTableRecords([$warehouseBRecord]);
        }
    }

    public function test_super_admin_table_query_sees_all_warehouses_and_global_masters_while_user_cannot_view(): void
    {
        $gudangA = Gudang::create(['nama_gudang' => 'Gudang A', 'alamat_gudang' => 'Jl. A']);
        $gudangB = Gudang::create(['nama_gudang' => 'Gudang B', 'alamat_gudang' => 'Jl. B']);
        $warehouseARecord = $this->cashBankAccountFor('Kas Gudang A', $gudangA);
        $warehouseBRecord = $this->cashBankAccountFor('Kas Gudang B', $gudangB);
        $globalRecord = $this->cashBankAccountFor('Bank Global', null, CashAccountType::Bank);
        $superAdmin = User::factory()->create(['role' => 'super_admin']);
        $user = User::factory()->create(['role' => 'user', 'gudang_id' => $gudangA->id]);

        Livewire::actingAs($superAdmin)
            ->test(ListCashBankAccounts::class)
            ->assertCanSeeTableRecords([$warehouseARecord, $warehouseBRecord, $globalRecord]);

        $this->actingAs($user);
        $this->assertFalse(CashBankAccountResource::canViewAny());
    }

    /** @return class-string */
    private function resourceClass(): string
    {
        $class = 'App\\Filament\\Resources\\CashBankAccounts\\CashBankAccountResource';
        $this->assertTrue(class_exists($class), 'Cash/bank Filament resource is missing.');

        return $class;
    }

    /** @return class-string */
    private function pageClass(string $page): string
    {
        $this->resourceClass();
        $class = 'App\\Filament\\Resources\\CashBankAccounts\\Pages\\'.$page;
        $this->assertTrue(class_exists($class), "Cash/bank {$page} page is missing.");

        return $class;
    }

    /** @return class-string<Model> */
    private function cashBankModelClass(): string
    {
        $class = CashBankAccount::class;
        $this->assertTrue(class_exists($class), 'Cash/bank master model is missing.');

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

    private function compatibleAccount(CashAccountType $type, array $overrides = []): Account
    {
        return Account::factory()->create([
            'category' => AccountCategory::Aset,
            'subcategory' => $type->value,
            'is_active' => true,
            'is_postable' => true,
            ...$overrides,
        ]);
    }

    private function cashBankAccountFor(string $name, ?Gudang $gudang, CashAccountType $type = CashAccountType::Kas): CashBankAccount
    {
        return CashBankAccount::query()->create([
            'name' => $name,
            'type' => $type,
            'account_id' => $this->compatibleAccount($type)->id,
            'gudang_id' => $gudang?->id,
            'is_active' => true,
        ]);
    }
}
