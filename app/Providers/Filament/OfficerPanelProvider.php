<?php

namespace App\Providers\Filament;

use App\Filament\Pages\Auth\RedirectToUnifiedLogin;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Navigation\NavigationGroup;
use Filament\View\PanelsRenderHook;
use Illuminate\Support\Facades\Blade;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Session\Middleware\AuthenticateSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class OfficerPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('officer')
            ->path('officer')
            ->authGuard('web')
            ->login(RedirectToUnifiedLogin::class)
            ->font('Inter')
            ->colors([
                'primary' => [
                    50 => '240, 253, 244',
                    100 => '220, 252, 231',
                    200 => '187, 247, 208',
                    300 => '134, 239, 172',
                    400 => '74, 222, 128',
                    500 => '22, 101, 52',
                    600 => '20, 83, 45',
                    700 => '15, 64, 35',
                    800 => '11, 45, 25',
                    900 => '7, 29, 16',
                    950 => '4, 17, 9',
                ],
                'success' => Color::Green,
                'warning' => Color::Amber,
                'danger' => Color::Red,
                'info' => Color::Blue,
                'gray' => Color::Slate,
            ])
            ->brandName('RideConnect Ops')
            ->brandLogo(asset('images/logo.svg'))
            ->brandLogoHeight('2.5rem')
            ->favicon(asset('images/favicon.png'))
            ->renderHook(
                PanelsRenderHook::HEAD_END,
                fn (): string => Blade::render("@vite(['resources/js/app.js'])"),
            )
            ->navigationGroups([
                NavigationGroup::make('Dashboard')->collapsible(false),
                NavigationGroup::make('Live Operations')->collapsible(false),
                NavigationGroup::make('Fleet Management')->collapsible(true),
                NavigationGroup::make('Support & Complaints')->collapsible(true),
            ])
            ->discoverPages(app_path('Filament/Pages/Officer'), 'App\\Filament\\Pages\\Officer')
            ->pages([
                \App\Filament\Pages\Officer\OfficerDashboard::class,
                \App\Filament\Pages\Officer\LiveRidesPage::class,
                \App\Filament\Pages\Officer\DriverManagementPage::class,
                \App\Filament\Pages\Officer\ComplaintsPage::class,
                \App\Filament\Pages\Officer\AIInsightsPage::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
                \App\Http\Middleware\EnsureOfficerRole::class,
            ])
            ->databaseNotifications();
    }
}
