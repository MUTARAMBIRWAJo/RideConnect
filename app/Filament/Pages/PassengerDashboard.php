<?php

namespace App\Filament\Pages;

use App\Enums\UserRole;
use App\Filament\Pages\Concerns\HandlesRoleDashboards;
use App\Filament\Support\RoleDashboardConfig;
use Filament\Pages\Dashboard\Concerns\HasFiltersForm;
use Illuminate\Contracts\Support\Htmlable;

class PassengerDashboard extends \Filament\Pages\Dashboard
{
    use HandlesRoleDashboards, HasFiltersForm;

    protected static string $routePath = '/passenger-dashboard';

    public static function getNavigationLabel(): string
    {
        return 'Passenger Dashboard';
    }

    public static function getNavigationIcon(): string | Htmlable | null
    {
        return 'heroicon-o-user-circle';
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::userHasRole(auth()->user(), 'Passenger', UserRole::PASSENGER);
    }

    public static function canAccess(): bool
    {
        return static::userHasRole(auth()->user(), 'Passenger', UserRole::PASSENGER);
    }

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);
    }

    public function getWidgets(): array
    {
        return RoleDashboardConfig::visibleWidgetsForRole(UserRole::PASSENGER->value);
    }

    public function getColumns(): int | string | array
    {
        return RoleDashboardConfig::columnsForRole(UserRole::PASSENGER->value);
    }
}
