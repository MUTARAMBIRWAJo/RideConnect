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
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Session\Middleware\AuthenticateSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AccountantPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('accountant')
            ->path('accountant')
            ->authGuard('web')
            ->login(RedirectToUnifiedLogin::class)
            ->font('Inter')
            ->colors([
                'primary' => Color::Amber,
                'success' => Color::Green,
                'warning' => Color::Orange,
                'danger' => Color::Red,
                'info' => Color::Blue,
                'gray' => Color::Slate,
            ])
            ->brandName('RideConnect Finance')
            ->brandLogo(asset('images/logo.svg'))
            ->brandLogoHeight('2.5rem')
            ->favicon(asset('images/favicon.png'))
            ->navigationGroups([
                NavigationGroup::make('Dashboard')->collapsible(false),
                NavigationGroup::make('Financial Operations')->collapsible(false),
                NavigationGroup::make('Reporting & Analytics')->collapsible(true),
                NavigationGroup::make('Compliance')->collapsible(true),
            ])
            ->discoverPages(app_path('Filament/Pages/Accountant'), 'App\\Filament\\Pages\\Accountant')
            ->pages([
                \App\Filament\Pages\Accountant\FinancialDashboard::class,
                \App\Filament\Pages\Accountant\TransactionsPage::class,
                \App\Filament\Pages\Accountant\DriverEarningsPage::class,
                \App\Filament\Pages\Accountant\ReportsPage::class,
                \App\Filament\Pages\Accountant\AuditLogsPage::class,
                \App\Filament\Pages\Accountant\RefundManagementPage::class,
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
                \App\Http\Middleware\EnsureAccountantRole::class,
            ])
            ->databaseNotifications();
    }
}
