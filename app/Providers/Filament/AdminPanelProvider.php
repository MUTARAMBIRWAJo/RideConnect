<?php

namespace App\Providers\Filament;

use App\Filament\Pages\Auth\RedirectToUnifiedLogin;
use App\Filament\Pages\Dashboard;
use App\Filament\Resources\BookingResource;
use App\Filament\Resources\DriverResource;
use App\Filament\Resources\FinanceResource;
use App\Filament\Resources\RideResource;
use App\Filament\Resources\TicketResource;
use App\Filament\Resources\TripResource;
use App\Filament\Resources\UserResource;
use App\Filament\Resources\VehicleResource;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\View\PanelsRenderHook;
use Filament\Navigation\NavigationGroup;
use Filament\Widgets;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Session\Middleware\AuthenticateSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
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
                'success' => [
                    50 => '240, 253, 244',
                    100 => '220, 252, 231',
                    200 => '187, 247, 208',
                    300 => '134, 239, 172',
                    400 => '74, 222, 128',
                    500 => '21, 128, 61',
                    600 => '22, 101, 52',
                    700 => '20, 83, 45',
                    800 => '15, 64, 35',
                    900 => '11, 45, 25',
                    950 => '5, 46, 22',
                ],
                'warning' => Color::Amber,
                'danger' => [
                    50 => '254, 242, 242',
                    100 => '254, 226, 226',
                    200 => '254, 202, 202',
                    300 => '252, 165, 165',
                    400 => '248, 113, 113',
                    500 => '239, 68, 68',
                    600 => '220, 38, 38',
                    700 => '185, 28, 28',
                    800 => '153, 27, 27',
                    900 => '127, 29, 29',
                    950 => '69, 10, 10',
                ],
                'info' => [
                    50 => '248, 250, 252',
                    100 => '241, 245, 249',
                    200 => '226, 232, 240',
                    300 => '203, 213, 225',
                    400 => '148, 163, 184',
                    500 => '100, 116, 139',
                    600 => '71, 85, 105',
                    700 => '51, 65, 85',
                    800 => '30, 41, 59',
                    900 => '15, 23, 42',
                    950 => '2, 6, 23',
                ],
                'gray' => Color::Slate,
            ])
            ->viteTheme('resources/css/filament/admin/theme.css')
            ->brandName('RideConnect')
            ->brandLogo(asset('images/logo.svg'))
            ->brandLogoHeight('2.5rem')
            ->favicon(asset('images/favicon.png'))
            ->renderHook(
                PanelsRenderHook::USER_MENU_BEFORE,
                fn (): string => view('components.filament.role-badge')->render(),
            )
            ->navigationGroups([
                NavigationGroup::make('Live Operations')->collapsible(false),
                NavigationGroup::make('Fleet & Drivers')->collapsible(true),
                NavigationGroup::make('Passengers')->collapsible(true),
                NavigationGroup::make('AI & Analytics')->collapsible(true),
                NavigationGroup::make('System')->collapsible(true),
            ])
            ->widgets([
                \App\Filament\Widgets\RideStatsOverview::class,
                \App\Filament\Widgets\LatestRidesTable::class,
                \App\Filament\Widgets\DemandHeatmapWidget::class,
                \App\Filament\Widgets\DriverAvailabilityChart::class,
            ])
            ->discoverResources(app_path('Filament/Resources'), 'App\\Filament\\Resources')
            ->discoverPages(app_path('Filament/Pages'), 'App\\Filament\\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(app_path('Filament/Widgets'), 'App\\Filament\\Widgets')
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
            ->authMiddleware([Authenticate::class])
            ->databaseNotifications();
    }
}

