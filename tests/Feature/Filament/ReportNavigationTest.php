<?php

namespace Tests\Feature\Filament;

use App\Filament\Pages\Reports\ArusKasPage;
use App\Filament\Pages\Reports\BukuBesarPage;
use App\Filament\Pages\Reports\JurnalPage;
use App\Filament\Pages\Reports\LabaRugiPage;
use App\Filament\Pages\Reports\NeracaPage;
use App\Filament\Pages\Reports\NeracaSaldoPage;
use App\Filament\Pages\Reports\PerubahanModalPage;
use App\Filament\Pages\Reports\RingkasanBisnisPage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use ReflectionClass;
use Tests\TestCase;

class ReportNavigationTest extends TestCase
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

    public function test_ringkasan_bisnis_route_exists(): void
    {
        $user = User::where('email', 'superadmin@hibiscusefsya.com')->first();
        $response = $this->actingAs($user)->get('/app/ringkasan-bisnis');
        $response->assertStatus(200);
    }

    public function test_neraca_route_exists(): void
    {
        $user = User::where('email', 'superadmin@hibiscusefsya.com')->first();
        $response = $this->actingAs($user)->get('/app/neraca');
        $response->assertStatus(200);
    }

    public function test_laba_rugi_route_exists(): void
    {
        $user = User::where('email', 'superadmin@hibiscusefsya.com')->first();
        $response = $this->actingAs($user)->get('/app/laba-rugi');
        $response->assertStatus(200);
    }

    public function test_arus_kas_route_exists(): void
    {
        $user = User::where('email', 'superadmin@hibiscusefsya.com')->first();
        $response = $this->actingAs($user)->get('/app/arus-kas');
        $response->assertStatus(200);
    }

    public function test_perubahan_modal_route_exists(): void
    {
        $user = User::where('email', 'superadmin@hibiscusefsya.com')->first();
        $response = $this->actingAs($user)->get('/app/perubahan-modal');
        $response->assertStatus(200);
    }

    public function test_buku_besar_route_exists(): void
    {
        $user = User::where('email', 'superadmin@hibiscusefsya.com')->first();
        $response = $this->actingAs($user)->get('/app/buku-besar');
        $response->assertStatus(200);
    }

    public function test_jurnal_route_exists(): void
    {
        $user = User::where('email', 'superadmin@hibiscusefsya.com')->first();
        $response = $this->actingAs($user)->get('/app/jurnal');
        $response->assertStatus(200);
    }

    public function test_neraca_saldo_route_exists(): void
    {
        $user = User::where('email', 'superadmin@hibiscusefsya.com')->first();
        $response = $this->actingAs($user)->get('/app/neraca-saldo');
        $response->assertStatus(200);
    }

    public function test_old_neraca_page_route_removed(): void
    {
        $user = User::where('email', 'superadmin@hibiscusefsya.com')->first();
        $response = $this->actingAs($user)->get('/app/neraca-page');
        $response->assertStatus(404);
    }

    public function test_ringkasan_bisnis_has_correct_label(): void
    {
        $this->assertEquals('Ringkasan Bisnis', $this->getStaticProperty(RingkasanBisnisPage::class, 'navigationLabel'));
    }

    public function test_ringkasan_bisnis_has_correct_title(): void
    {
        $this->assertEquals('Ringkasan Bisnis', $this->getStaticProperty(RingkasanBisnisPage::class, 'title'));
    }

    public function test_ringkasan_bisnis_has_correct_slug(): void
    {
        $this->assertEquals('ringkasan-bisnis', $this->getStaticProperty(RingkasanBisnisPage::class, 'slug'));
    }

    public function test_ringkasan_bisnis_has_sort_1(): void
    {
        $this->assertEquals(1, $this->getStaticProperty(RingkasanBisnisPage::class, 'navigationSort'));
    }

    public function test_neraca_has_correct_label(): void
    {
        $this->assertEquals('Neraca', $this->getStaticProperty(NeracaPage::class, 'navigationLabel'));
    }

    public function test_neraca_has_correct_slug(): void
    {
        $this->assertEquals('neraca', $this->getStaticProperty(NeracaPage::class, 'slug'));
    }

    public function test_neraca_has_sort_2(): void
    {
        $this->assertEquals(2, $this->getStaticProperty(NeracaPage::class, 'navigationSort'));
    }

    public function test_laba_rugi_has_correct_label(): void
    {
        $this->assertEquals('Laba Rugi', $this->getStaticProperty(LabaRugiPage::class, 'navigationLabel'));
    }

    public function test_laba_rugi_has_correct_slug(): void
    {
        $this->assertEquals('laba-rugi', $this->getStaticProperty(LabaRugiPage::class, 'slug'));
    }

    public function test_laba_rugi_has_sort_3(): void
    {
        $this->assertEquals(3, $this->getStaticProperty(LabaRugiPage::class, 'navigationSort'));
    }

    public function test_arus_kas_has_correct_label(): void
    {
        $this->assertEquals('Arus Kas', $this->getStaticProperty(ArusKasPage::class, 'navigationLabel'));
    }

    public function test_arus_kas_has_correct_slug(): void
    {
        $this->assertEquals('arus-kas', $this->getStaticProperty(ArusKasPage::class, 'slug'));
    }

    public function test_arus_kas_has_sort_4(): void
    {
        $this->assertEquals(4, $this->getStaticProperty(ArusKasPage::class, 'navigationSort'));
    }

    public function test_perubahan_modal_has_correct_label(): void
    {
        $this->assertEquals('Perubahan Modal', $this->getStaticProperty(PerubahanModalPage::class, 'navigationLabel'));
    }

    public function test_perubahan_modal_has_correct_slug(): void
    {
        $this->assertEquals('perubahan-modal', $this->getStaticProperty(PerubahanModalPage::class, 'slug'));
    }

    public function test_perubahan_modal_has_sort_5(): void
    {
        $this->assertEquals(5, $this->getStaticProperty(PerubahanModalPage::class, 'navigationSort'));
    }

    public function test_buku_besar_has_correct_label(): void
    {
        $this->assertEquals('Buku Besar', $this->getStaticProperty(BukuBesarPage::class, 'navigationLabel'));
    }

    public function test_buku_besar_has_correct_slug(): void
    {
        $this->assertEquals('buku-besar', $this->getStaticProperty(BukuBesarPage::class, 'slug'));
    }

    public function test_buku_besar_has_sort_6(): void
    {
        $this->assertEquals(6, $this->getStaticProperty(BukuBesarPage::class, 'navigationSort'));
    }

    public function test_jurnal_has_correct_label(): void
    {
        $this->assertEquals('Laporan Jurnal', $this->getStaticProperty(JurnalPage::class, 'navigationLabel'));
    }

    public function test_jurnal_has_correct_slug(): void
    {
        $this->assertEquals('jurnal', $this->getStaticProperty(JurnalPage::class, 'slug'));
    }

    public function test_jurnal_has_sort_7(): void
    {
        $this->assertEquals(7, $this->getStaticProperty(JurnalPage::class, 'navigationSort'));
    }

    public function test_neraca_saldo_has_correct_label(): void
    {
        $this->assertEquals('Neraca Saldo', $this->getStaticProperty(NeracaSaldoPage::class, 'navigationLabel'));
    }

    public function test_neraca_saldo_has_correct_slug(): void
    {
        $this->assertEquals('neraca-saldo', $this->getStaticProperty(NeracaSaldoPage::class, 'slug'));
    }

    public function test_neraca_saldo_has_sort_8(): void
    {
        $this->assertEquals(8, $this->getStaticProperty(NeracaSaldoPage::class, 'navigationSort'));
    }

    public function test_all_report_pages_use_laporan_navigation_group(): void
    {
        $this->assertEquals('Laporan', $this->getStaticProperty(RingkasanBisnisPage::class, 'navigationGroup'));
        $this->assertEquals('Laporan', $this->getStaticProperty(NeracaPage::class, 'navigationGroup'));
        $this->assertEquals('Laporan', $this->getStaticProperty(LabaRugiPage::class, 'navigationGroup'));
        $this->assertEquals('Laporan', $this->getStaticProperty(ArusKasPage::class, 'navigationGroup'));
        $this->assertEquals('Laporan', $this->getStaticProperty(PerubahanModalPage::class, 'navigationGroup'));
        $this->assertEquals('Laporan', $this->getStaticProperty(BukuBesarPage::class, 'navigationGroup'));
        $this->assertEquals('Laporan', $this->getStaticProperty(JurnalPage::class, 'navigationGroup'));
        $this->assertEquals('Laporan', $this->getStaticProperty(NeracaSaldoPage::class, 'navigationGroup'));
    }

    public function test_super_admin_can_access_all_report_pages(): void
    {
        $user = User::where('email', 'superadmin@hibiscusefsya.com')->first();
        $this->actingAs($user);

        $this->assertTrue(RingkasanBisnisPage::canAccess());
        $this->assertTrue(NeracaPage::canAccess());
        $this->assertTrue(LabaRugiPage::canAccess());
        $this->assertTrue(ArusKasPage::canAccess());
        $this->assertTrue(PerubahanModalPage::canAccess());
        $this->assertTrue(BukuBesarPage::canAccess());
        $this->assertTrue(JurnalPage::canAccess());
        $this->assertTrue(NeracaSaldoPage::canAccess());
    }

    public function test_spectator_can_access_all_report_pages(): void
    {
        $user = User::where('email', 'spectator@hibiscusefsya.com')->first();
        $this->actingAs($user);

        $this->assertTrue(RingkasanBisnisPage::canAccess());
        $this->assertTrue(NeracaPage::canAccess());
        $this->assertTrue(LabaRugiPage::canAccess());
        $this->assertTrue(ArusKasPage::canAccess());
        $this->assertTrue(PerubahanModalPage::canAccess());
        $this->assertTrue(BukuBesarPage::canAccess());
        $this->assertTrue(JurnalPage::canAccess());
        $this->assertTrue(NeracaSaldoPage::canAccess());
    }

    public function test_admin_cannot_access_report_pages(): void
    {
        $user = User::where('email', 'admin@hibiscusefsya.com')->first();
        $this->actingAs($user);

        $this->assertFalse(RingkasanBisnisPage::canAccess());
        $this->assertFalse(NeracaPage::canAccess());
        $this->assertFalse(LabaRugiPage::canAccess());
        $this->assertFalse(ArusKasPage::canAccess());
        $this->assertFalse(PerubahanModalPage::canAccess());
        $this->assertFalse(BukuBesarPage::canAccess());
        $this->assertFalse(JurnalPage::canAccess());
        $this->assertFalse(NeracaSaldoPage::canAccess());
    }

    public function test_user_role_cannot_access_report_pages(): void
    {
        $user = User::where('email', 'salesa@hibiscusefsya.com')->first();
        $this->actingAs($user);

        $this->assertFalse(RingkasanBisnisPage::canAccess());
        $this->assertFalse(NeracaPage::canAccess());
        $this->assertFalse(LabaRugiPage::canAccess());
        $this->assertFalse(ArusKasPage::canAccess());
        $this->assertFalse(PerubahanModalPage::canAccess());
        $this->assertFalse(BukuBesarPage::canAccess());
        $this->assertFalse(JurnalPage::canAccess());
        $this->assertFalse(NeracaSaldoPage::canAccess());
    }

    public function test_placeholder_pages_show_placeholder_content(): void
    {
        $user = User::where('email', 'superadmin@hibiscusefsya.com')->first();

        $response = $this->actingAs($user)->get('/app/neraca');
        $response->assertSee('Halaman ini masih dalam tahap pengembangan');
        $response->assertSee('Belum ada data atau perhitungan akuntansi yang ditampilkan');
        $response->assertSee('Implementasi akan dilanjutkan pada tahap berikutnya');

        $response = $this->actingAs($user)->get('/app/laba-rugi');
        $response->assertSee('Halaman ini masih dalam tahap pengembangan');

        $response = $this->actingAs($user)->get('/app/arus-kas');
        $response->assertSee('Halaman ini masih dalam tahap pengembangan');

        $response = $this->actingAs($user)->get('/app/perubahan-modal');
        $response->assertSee('Halaman ini masih dalam tahap pengembangan');

        $response = $this->actingAs($user)->get('/app/buku-besar');
        $response->assertDontSee('Halaman ini masih dalam tahap pengembangan');
        $response->assertSee('Saldo awal');

        $response = $this->actingAs($user)->get('/app/jurnal');
        $response->assertDontSee('Halaman ini masih dalam tahap pengembangan');
        $response->assertSee('Tidak ada jurnal posted untuk filter ini.');

        $response = $this->actingAs($user)->get('/app/neraca-saldo');
        $response->assertDontSee('Halaman ini masih dalam tahap pengembangan');
        $response->assertSee('Consolidated view only.');
    }

    public function test_ringkasan_bisnis_is_not_placeholder(): void
    {
        $user = User::where('email', 'superadmin@hibiscusefsya.com')->first();
        $response = $this->actingAs($user)->get('/app/ringkasan-bisnis');
        $response->assertDontSee('Halaman ini masih dalam tahap pengembangan');
        $response->assertSee('OMSET PERGUDANG');
    }

    public function test_ringkasan_bisnis_livewire_component_loads(): void
    {
        $user = User::where('email', 'superadmin@hibiscusefsya.com')->first();

        Livewire::actingAs($user)
            ->test(RingkasanBisnisPage::class)
            ->assertOk();
    }

    public function test_placeholder_pages_livewire_components_load(): void
    {
        $user = User::where('email', 'superadmin@hibiscusefsya.com')->first();

        Livewire::actingAs($user)->test(NeracaPage::class)->assertOk();
        Livewire::actingAs($user)->test(LabaRugiPage::class)->assertOk();
        Livewire::actingAs($user)->test(ArusKasPage::class)->assertOk();
        Livewire::actingAs($user)->test(PerubahanModalPage::class)->assertOk();
        Livewire::actingAs($user)->test(BukuBesarPage::class)->assertOk();
        Livewire::actingAs($user)->test(JurnalPage::class)->assertOk();
        Livewire::actingAs($user)->test(NeracaSaldoPage::class)->assertOk();
    }
}
