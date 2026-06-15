<?php

namespace App\Providers\Filament;

use App\Filament\Pages\Dashboard;
use App\Filament\Pages\Auth\Login;
use App\Filament\Pages\ProfilePage;
use Filament\Navigation\NavigationGroup;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Enums\ThemeMode;
use Filament\Support\Colors\Color;
use Filament\Support\Enums\Width;
use Filament\View\PanelsRenderHook;
use Filament\Widgets\AccountWidget;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AppPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->defaultThemeMode(ThemeMode::Light)
            ->id('app')
            ->path('app')
            ->login(Login::class)
            ->userMenuItems([
                'profile' => fn (Action $action): Action => $action
                    ->label('Profil Saya')
                    ->url(fn (): string => ProfilePage::getUrl()),
            ])
            ->brandName('Hibiscus Efsya POS')
            ->favicon(asset('assets/img/logoHE1.png'))
            ->font('Instrument Sans')
            ->colors([
                'primary' => Color::Blue,
            ])
            ->databaseNotifications()
            ->sidebarCollapsibleOnDesktop()
            ->maxContentWidth(Width::Full)
            ->navigationGroups([
                NavigationGroup::make('Neraca')->icon('heroicon-o-scale'),
                NavigationGroup::make('Kunjungan')->icon('heroicon-o-map-pin'),
                NavigationGroup::make('Biaya')->icon('heroicon-o-wallet'),
                NavigationGroup::make('Piutang')->icon('heroicon-o-inbox'),
                NavigationGroup::make('Hutang')->icon('heroicon-o-inbox-stack'),
                NavigationGroup::make('Gudang')->icon('heroicon-o-archive-box'),
                NavigationGroup::make('Kontak')->icon('heroicon-o-identification'),
                NavigationGroup::make('Master Data')->icon('heroicon-o-database'),
                NavigationGroup::make('Pengaturan')->icon('heroicon-o-cog-6-tooth'),
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\\Filament\\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\\Filament\\Widgets')
            ->widgets([
                AccountWidget::class,
            ])
            ->renderHook(
                PanelsRenderHook::GLOBAL_SEARCH_BEFORE,
                fn (): string => \Illuminate\Support\Facades\Blade::render('@livewire("gudang-switcher")')
            )
            ->renderHook(
                PanelsRenderHook::HEAD_END,
                fn (): string => view('filament.components.ui-polish')->render()
                    . '<script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.6/dist/JsBarcode.all.min.js"></script>'
                    . '<script src="https://cdn.jsdelivr.net/npm/qrcodejs@1.0.0/qrcode.min.js"></script>'
            )
            ->renderHook(
                PanelsRenderHook::BODY_END,
                fn (): string => '<script src="'.asset('js/bluetooth-print.js').'?v='.filemtime(public_path('js/bluetooth-print.js')).'"></script>'
                    .view('filament.components.barcode-scanner')->render()
                    .view('filament.components.pos-scripts')->render()
            )

            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
