<?php

namespace App\Filament\Pages;

use App\Enums\UserRole;
use App\Filament\Pages\Concerns\HandlesRoleDashboards;
use App\Filament\Widgets\AdminStats;
use App\Filament\Widgets\RideAnalytics;
use Filament\Pages\Dashboard\Concerns\HasFiltersForm;
use Illuminate\Contracts\Support\Htmlable;

class AdminDashboard extends \Filament\Pages\Dashboard
{
    use HandlesRoleDashboards, HasFiltersForm;

    protected static string $view = 'filament.pages.admin-dashboard';

    protected static ?string $navigationGroup = 'Dashboards';

    protected static string $routePath = '/admin-dashboard';

    public static function getNavigationLabel(): string
    {
        return 'Admin Dashboard';
    }

    public static function getNavigationIcon(): string | Htmlable | null
    {
        return 'heroicon-o-cog-8-tooth';
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::userHasRole(auth()->user(), 'Admin', UserRole::ADMIN);
    }

    public static function canAccess(): bool
    {
        return static::userHasRole(auth()->user(), 'Admin', UserRole::ADMIN);
    }

    public static function canView(): bool
    {
        return static::userHasRole(auth()->user(), 'Admin', UserRole::ADMIN);
    }

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);
    }

    protected function getHeaderWidgets(): array
    {
        return [
            AdminStats::class,
        ];
    }

    protected function getFooterWidgets(): array
    {
        return [
            RideAnalytics::class,
        ];
    }
}
