<?php

namespace App\Filament\Pages;

use App\Enums\UserRole;
use App\Filament\Pages\Concerns\HandlesRoleDashboards;
use App\Filament\Support\RoleDashboardConfig;
use Filament\Pages\Dashboard\Concerns\HasFiltersForm;
use Illuminate\Contracts\Support\Htmlable;

class DriverDashboard extends \Filament\Pages\Dashboard
{
    use HandlesRoleDashboards, HasFiltersForm;

    protected static string $routePath = '/driver-dashboard';

    public static function getNavigationLabel(): string
    {
        return 'Driver Dashboard';
    }

    public static function getNavigationIcon(): string | Htmlable | null
    {
        return 'heroicon-o-truck';
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::userHasRole(auth()->user(), 'Driver', UserRole::DRIVER);
    }

    public static function canAccess(): bool
    {
        return static::userHasRole(auth()->user(), 'Driver', UserRole::DRIVER);
    }

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);
    }

    public function getWidgets(): array
    {
        return RoleDashboardConfig::visibleWidgetsForRole(UserRole::DRIVER->value);
    }

    public function getColumns(): int | string | array
    {
        return RoleDashboardConfig::columnsForRole(UserRole::DRIVER->value);
    }
}
