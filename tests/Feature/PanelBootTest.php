<?php

namespace Tests\Feature;

use App\Filament\Resources\Biayas\Pages\ListBiayas;
use App\Filament\Resources\Kunjungans\Pages\ListKunjungans;
use App\Filament\Resources\Pembayarans\Pages\ListPembayarans;
use App\Filament\Resources\Pembelians\Pages\ListPembelians;
use App\Filament\Resources\PenerimaanBarangs\Pages\ListPenerimaanBarangs;
use App\Filament\Widgets\ChartPenjualanSales;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PanelBootTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    public function test_app_root_redirects_to_login_when_unauthenticated(): void
    {
        $response = $this->get('/app');
        $response->assertRedirect('/app/login');
    }

    public function test_login_page_loads(): void
    {
        $response = $this->get('/app/login');
        $response->assertStatus(200);
        $response->assertSee('Hibiscus Efsya POS');
        $response->assertSee('Kelola Keuangan Bisnis');
        $response->assertSee('Real-time sales control');
        $response->assertSee('Masuk ke Hibiscus Efsya POS');
    }

    public function test_login_form_authenticates_super_admin(): void
    {
        Livewire::test(\App\Filament\Pages\Auth\Login::class)
            ->fillForm([
                'email' => 'superadmin@hibiscusefsya.com',
                'password' => 'password123',
            ])
            ->call('authenticate')
            ->assertHasNoFormErrors();

        $this->assertAuthenticated();
    }

    public function test_super_admin_can_access_dashboard(): void
    {
        $user = User::where('email', 'superadmin@hibiscusefsya.com')->first();
        $response = $this->actingAs($user)->get('/app');
        $response->assertStatus(200);
    }

    public function test_super_admin_can_access_profile_page(): void
    {
        $user = User::where('email', 'superadmin@hibiscusefsya.com')->first();
        $response = $this->actingAs($user)->get('/app/profile-page');
        $response->assertStatus(200);
        $response->assertSee('Informasi Akun');
    }

    public function test_super_admin_can_render_sales_chart_widget(): void
    {
        $user = User::where('email', 'superadmin@hibiscusefsya.com')->first();

        Livewire::actingAs($user)
            ->test(ChartPenjualanSales::class)
            ->assertOk();
    }

    public function test_admin_can_access_dashboard(): void
    {
        $user = User::where('email', 'admin@hibiscusefsya.com')->first();
        $response = $this->actingAs($user)->get('/app');
        $response->assertStatus(200);
    }

    public function test_user_can_access_dashboard(): void
    {
        $user = User::where('email', 'salesa@hibiscusefsya.com')->first();
        $response = $this->actingAs($user)->get('/app');
        $response->assertStatus(200);
    }

    public function test_spectator_can_access_dashboard(): void
    {
        $user = User::where('email', 'spectator@hibiscusefsya.com')->first();
        $response = $this->actingAs($user)->get('/app');
        $response->assertStatus(200);
    }

    // === Resource access ===

    public function test_super_admin_can_access_users_page(): void
    {
        $user = User::where('email', 'superadmin@hibiscusefsya.com')->first();
        $response = $this->actingAs($user)->get('/app/users');
        $response->assertStatus(200);
    }

    public function test_admin_cannot_access_users_page(): void
    {
        $user = User::where('email', 'admin@hibiscusefsya.com')->first();
        $response = $this->actingAs($user)->get('/app/users');
        $response->assertStatus(403);
    }

    public function test_user_cannot_access_gudangs_page(): void
    {
        $user = User::where('email', 'salesa@hibiscusefsya.com')->first();
        $response = $this->actingAs($user)->get('/app/gudangs');
        $response->assertStatus(403);
    }

    public function test_super_admin_can_access_produks_page(): void
    {
        $user = User::where('email', 'superadmin@hibiscusefsya.com')->first();
        $response = $this->actingAs($user)->get('/app/produks');
        $response->assertStatus(200);
    }

    public function test_super_admin_can_access_penjualans_page(): void
    {
        $user = User::where('email', 'superadmin@hibiscusefsya.com')->first();
        $response = $this->actingAs($user)->get('/app/penjualans');
        $response->assertStatus(200);
    }

    public function test_super_admin_can_render_transaction_create_pages(): void
    {
        $user = User::where('email', 'superadmin@hibiscusefsya.com')->first();

        foreach ([
            '/app/penjualans/create',
            '/app/pembelians/create',
            '/app/biayas/create',
            '/app/kunjungans/create',
        ] as $path) {
            $this->actingAs($user)->get($path)->assertStatus(200);
        }
    }

    public function test_super_admin_can_render_pending_tabs_after_livewire_update(): void
    {
        $user = User::where('email', 'superadmin@hibiscusefsya.com')->first();

        foreach ([
            ListKunjungans::class,
            ListPembayarans::class,
            ListBiayas::class,
            ListPembelians::class,
            ListPenerimaanBarangs::class,
        ] as $component) {
            Livewire::actingAs($user)
                ->test($component, ['activeTab' => 'pending'])
                ->assertOk();
        }
    }

    public function test_super_admin_can_access_pending_tab_urls(): void
    {
        $user = User::where('email', 'superadmin@hibiscusefsya.com')->first();

        foreach ([
            '/app/kunjungans?tab=pending',
            '/app/pembayarans?tab=pending',
            '/app/biayas?tab=pending',
            '/app/pembelians?tab=pending',
            '/app/penerimaan-barangs?tab=pending',
        ] as $path) {
            $this->actingAs($user)->get($path)->assertStatus(200);
        }
    }

    public function test_super_admin_can_access_kontaks_page(): void
    {
        $user = User::where('email', 'superadmin@hibiscusefsya.com')->first();
        $response = $this->actingAs($user)->get('/app/kontaks');
        $response->assertStatus(200);
    }

    public function test_super_admin_can_access_pembelians_page(): void
    {
        $user = User::where('email', 'superadmin@hibiscusefsya.com')->first();
        $response = $this->actingAs($user)->get('/app/pembelians');
        $response->assertStatus(200);
    }

    public function test_super_admin_can_access_biayas_page(): void
    {
        $user = User::where('email', 'superadmin@hibiscusefsya.com')->first();
        $response = $this->actingAs($user)->get('/app/biayas');
        $response->assertStatus(200);
    }

    public function test_super_admin_can_access_kunjungans_page(): void
    {
        $user = User::where('email', 'superadmin@hibiscusefsya.com')->first();
        $response = $this->actingAs($user)->get('/app/kunjungans');
        $response->assertStatus(200);
    }

    public function test_super_admin_can_access_pembayarans_page(): void
    {
        $user = User::where('email', 'superadmin@hibiscusefsya.com')->first();
        $response = $this->actingAs($user)->get('/app/pembayarans');
        $response->assertStatus(200);
    }

    public function test_super_admin_can_access_penerimaan_barangs_page(): void
    {
        $user = User::where('email', 'superadmin@hibiscusefsya.com')->first();
        $response = $this->actingAs($user)->get('/app/penerimaan-barangs');
        $response->assertStatus(200);
    }

    public function test_super_admin_can_access_stok_log_page(): void
    {
        $user = User::where('email', 'superadmin@hibiscusefsya.com')->first();

        $this->actingAs($user)->get('/app/stok-log-page')->assertStatus(200);
    }

    public function test_spectator_cannot_create_penjualan(): void
    {
        $user = User::where('email', 'spectator@hibiscusefsya.com')->first();
        $response = $this->actingAs($user)->get('/app/penjualans/create');
        $response->assertStatus(403);
    }
}
