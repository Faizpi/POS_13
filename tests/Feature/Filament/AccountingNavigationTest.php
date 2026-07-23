<?php

namespace Tests\Feature\Filament;

use App\Filament\Pages\Accounting\PemetaanAkunPage;
use App\Filament\Pages\Accounting\TransferKasPage;
use App\Filament\Pages\HutangPage;
use App\Filament\Pages\PiutangPage;
use App\Filament\Pages\Reports\JurnalPage;
use App\Filament\Resources\Accounts\AccountResource;
use App\Filament\Resources\Accounts\Pages\ListAccounts;
use App\Filament\Resources\CashBankAccounts\CashBankAccountResource;
use App\Filament\Resources\CashBankAccounts\Pages\ListCashBankAccounts;
use App\Models\Gudang;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use ReflectionClass;
use Tests\TestCase;

class AccountingNavigationTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    private function getStaticProperty(string $class, string $property): mixed
    {
        $reflection = new ReflectionClass($class);
        $prop = $reflection->getProperty($property);
        $prop->setAccessible(true);

        return $prop->getValue();
    }

    // === Akuntansi group pages exist with correct navigation properties ===

    public function test_daftar_akun_resource_has_correct_navigation_group(): void
    {
        $this->assertEquals('Akuntansi', $this->getStaticProperty(AccountResource::class, 'navigationGroup'));
    }

    public function test_daftar_akun_resource_has_correct_label(): void
    {
        $this->assertEquals('Daftar Akun', $this->getStaticProperty(AccountResource::class, 'navigationLabel'));
    }

    public function test_daftar_akun_resource_has_correct_slug(): void
    {
        $this->assertEquals('daftar-akun', $this->getStaticProperty(AccountResource::class, 'slug'));
    }

    public function test_daftar_akun_resource_has_sort_1(): void
    {
        $this->assertEquals(1, $this->getStaticProperty(AccountResource::class, 'navigationSort'));
    }

    public function test_pemetaan_akun_has_correct_navigation_group(): void
    {
        $this->assertEquals('Akuntansi', $this->getStaticProperty(PemetaanAkunPage::class, 'navigationGroup'));
    }

    public function test_pemetaan_akun_has_correct_label(): void
    {
        $this->assertEquals('Pemetaan Akun', $this->getStaticProperty(PemetaanAkunPage::class, 'navigationLabel'));
    }

    public function test_pemetaan_akun_has_correct_slug(): void
    {
        $this->assertEquals('pemetaan-akun', $this->getStaticProperty(PemetaanAkunPage::class, 'slug'));
    }

    public function test_pemetaan_akun_has_sort_2(): void
    {
        $this->assertEquals(2, $this->getStaticProperty(PemetaanAkunPage::class, 'navigationSort'));
    }

    public function test_kas_bank_has_correct_navigation_group(): void
    {
        $this->assertEquals('Akuntansi', $this->getStaticProperty(CashBankAccountResource::class, 'navigationGroup'));
    }

    public function test_kas_bank_has_correct_label(): void
    {
        $this->assertEquals('Kas & Bank', $this->getStaticProperty(CashBankAccountResource::class, 'navigationLabel'));
    }

    public function test_kas_bank_has_correct_slug(): void
    {
        $this->assertEquals('kas-bank', $this->getStaticProperty(CashBankAccountResource::class, 'slug'));
    }

    public function test_kas_bank_has_sort_3(): void
    {
        $this->assertEquals(3, $this->getStaticProperty(CashBankAccountResource::class, 'navigationSort'));
    }

    public function test_transfer_kas_has_correct_navigation_group(): void
    {
        $this->assertEquals('Akuntansi', $this->getStaticProperty(TransferKasPage::class, 'navigationGroup'));
    }

    public function test_transfer_kas_has_correct_label(): void
    {
        $this->assertEquals('Transfer Kas', $this->getStaticProperty(TransferKasPage::class, 'navigationLabel'));
    }

    public function test_transfer_kas_has_correct_slug(): void
    {
        $this->assertEquals('transfer-kas', $this->getStaticProperty(TransferKasPage::class, 'slug'));
    }

    public function test_transfer_kas_has_sort_4(): void
    {
        $this->assertEquals(4, $this->getStaticProperty(TransferKasPage::class, 'navigationSort'));
    }

    // === JurnalPage label changed to 'Laporan Jurnal' ===

    public function test_jurnal_has_correct_label(): void
    {
        $this->assertEquals('Laporan Jurnal', $this->getStaticProperty(JurnalPage::class, 'navigationLabel'));
    }

    // === Access control: sales cannot access Akuntansi pages ===

    public function test_sales_cannot_access_daftar_akun(): void
    {
        $user = $this->accountingUser('salesa@hibiscusefsya.com', 'user');
        $this->actingAs($user);

        $this->assertFalse(AccountResource::canViewAny());
    }

    public function test_sales_cannot_access_pemetaan_akun(): void
    {
        $user = $this->accountingUser('salesa@hibiscusefsya.com', 'user');
        $this->actingAs($user);

        $this->assertFalse(PemetaanAkunPage::canAccess());
    }

    public function test_sales_cannot_access_kas_bank(): void
    {
        $user = $this->accountingUser('salesa@hibiscusefsya.com', 'user');
        $this->actingAs($user);

        $this->assertFalse(CashBankAccountResource::canViewAny());
    }

    public function test_sales_cannot_access_transfer_kas(): void
    {
        $user = $this->accountingUser('salesa@hibiscusefsya.com', 'user');
        $this->actingAs($user);

        $this->assertFalse(TransferKasPage::canAccess());
    }

    // === Access control: super_admin can access Akuntansi pages ===

    public function test_super_admin_can_access_daftar_akun(): void
    {
        $user = $this->accountingUser('superadmin@hibiscusefsya.com', 'super_admin');
        $this->actingAs($user);

        $this->assertTrue(AccountResource::canViewAny());
    }

    public function test_super_admin_can_access_pemetaan_akun(): void
    {
        $user = $this->accountingUser('superadmin@hibiscusefsya.com', 'super_admin');
        $this->actingAs($user);

        $this->assertTrue(PemetaanAkunPage::canAccess());
    }

    public function test_super_admin_can_access_kas_bank(): void
    {
        $user = $this->accountingUser('superadmin@hibiscusefsya.com', 'super_admin');
        $this->actingAs($user);

        $this->assertTrue(CashBankAccountResource::canViewAny());
    }

    public function test_super_admin_can_access_transfer_kas(): void
    {
        $user = $this->accountingUser('superadmin@hibiscusefsya.com', 'super_admin');
        $this->actingAs($user);

        $this->assertTrue(TransferKasPage::canAccess());
    }

    // === Access control: spectator can access Akuntansi pages ===

    public function test_spectator_can_access_daftar_akun(): void
    {
        $user = $this->accountingUser('spectator@hibiscusefsya.com', 'spectator');
        $this->actingAs($user);

        $this->assertTrue(AccountResource::canViewAny());
    }

    public function test_spectator_can_access_pemetaan_akun(): void
    {
        $user = $this->accountingUser('spectator@hibiscusefsya.com', 'spectator');
        $this->actingAs($user);

        $this->assertTrue(PemetaanAkunPage::canAccess());
    }

    public function test_spectator_can_access_kas_bank(): void
    {
        $user = $this->accountingUser('spectator@hibiscusefsya.com', 'spectator');
        $this->actingAs($user);

        $this->assertTrue(CashBankAccountResource::canViewAny());
    }

    public function test_spectator_cannot_access_transfer_kas_mutation_page(): void
    {
        $user = $this->accountingUser('spectator@hibiscusefsya.com', 'spectator');
        $this->actingAs($user);

        $this->assertFalse(TransferKasPage::canAccess());
    }

    // === Access control: admin has read-only access to cash/bank configuration ===

    public function test_admin_cannot_access_daftar_akun(): void
    {
        $user = $this->accountingUser('admin@hibiscusefsya.com', 'admin');
        $this->actingAs($user);

        $this->assertTrue(AccountResource::canViewAny());
        $this->assertFalse(AccountResource::canCreate());
    }

    public function test_admin_can_access_pemetaan_akun_read_only(): void
    {
        $user = $this->accountingUser('admin@hibiscusefsya.com', 'admin');
        $this->actingAs($user);

        $this->assertTrue(PemetaanAkunPage::canAccess());
    }

    public function test_admin_cannot_access_kas_bank(): void
    {
        $user = $this->accountingUser('admin@hibiscusefsya.com', 'admin');
        $this->actingAs($user);

        $this->assertTrue(CashBankAccountResource::canViewAny());
    }

    public function test_admin_with_active_warehouse_can_access_transfer_kas_to_initiate(): void
    {
        $gudang = Gudang::create([
            'nama_gudang' => 'Gudang Transfer',
            'alamat_gudang' => 'Jl. Transfer',
        ]);
        $user = $this->accountingUser('admin@hibiscusefsya.com', 'admin');
        $user->update(['gudang_id' => $gudang->id, 'current_gudang_id' => $gudang->id]);
        $user->gudangs()->syncWithoutDetaching([$gudang->id]);
        $this->actingAs($user);

        $this->assertTrue(TransferKasPage::canAccess());
    }

    // === Routes exist for Akuntansi pages ===

    public function test_daftar_akun_route_exists(): void
    {
        $user = $this->accountingUser('superadmin@hibiscusefsya.com', 'super_admin');
        $response = $this->actingAs($user)->get('/app/daftar-akun');
        $response->assertStatus(200);
    }

    public function test_pemetaan_akun_route_exists(): void
    {
        $user = $this->accountingUser('superadmin@hibiscusefsya.com', 'super_admin');
        $response = $this->actingAs($user)->get('/app/pemetaan-akun');
        $response->assertStatus(200);
    }

    public function test_kas_bank_route_exists(): void
    {
        $user = $this->accountingUser('superadmin@hibiscusefsya.com', 'super_admin');
        $response = $this->actingAs($user)->get('/app/kas-bank');
        $response->assertStatus(200);
    }

    public function test_transfer_kas_route_exists(): void
    {
        $user = $this->accountingUser('superadmin@hibiscusefsya.com', 'super_admin');
        $response = $this->actingAs($user)->get('/app/transfer-kas');
        $response->assertStatus(200);
    }

    // === Livewire components load ===

    public function test_accounting_pages_livewire_components_load(): void
    {
        $user = $this->accountingUser('superadmin@hibiscusefsya.com', 'super_admin');

        Livewire::actingAs($user)->test(ListAccounts::class)->assertOk();
        Livewire::actingAs($user)->test(PemetaanAkunPage::class)->assertOk();
        Livewire::actingAs($user)->test(ListCashBankAccounts::class)->assertOk();
        Livewire::actingAs($user)->test(TransferKasPage::class)->assertOk();
    }

    // === Piutang/Hutang groups preserved (not in Akuntansi) ===

    public function test_piutang_not_in_akuntansi_group(): void
    {
        $this->assertNotEquals('Akuntansi', $this->getStaticProperty(PiutangPage::class, 'navigationGroup'));
    }

    public function test_hutang_not_in_akuntansi_group(): void
    {
        $this->assertNotEquals('Akuntansi', $this->getStaticProperty(HutangPage::class, 'navigationGroup'));
    }

    private function accountingUser(string $email, string $role): User
    {
        return User::query()->firstOrCreate(
            ['email' => $email],
            [
                'name' => 'Accounting Navigation '.$role,
                'password' => 'password',
                'role' => $role,
            ],
        );
    }
}
