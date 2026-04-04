<?php

namespace App\Filament\Pages;

use App\Enums\UserRole;
use App\Filament\Pages\Concerns\HandlesRoleDashboards;
use App\Filament\Support\RoleDashboardConfig;
use Filament\Pages\Dashboard\Concerns\HasFiltersForm;
use Illuminate\Contracts\Support\Htmlable;

abstract class BaseDashboard extends \Filament\Pages\Dashboard
{
    use HandlesRoleDashboards, HasFiltersForm;

    protected static ?string $navigationGroup = 'Dashboards';

    abstract protected static function dashboardRole(): UserRole;

    protected static function dashboardRoleValue(): string
    {
        return static::dashboardRole()->value;
    }

    protected static function spatieRoleName(): string
    {
        return str_replace(' ', '_', ucwords(strtolower(static::dashboardRoleValue()), '_'));
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::userHasRole(auth()->user(), static::spatieRoleName(), static::dashboardRole());
    }

    public static function canAccess(): bool
    {
        return static::userHasRole(auth()->user(), static::spatieRoleName(), static::dashboardRole());
    }

    public static function canView(): bool
    {
        return static::canAccess();
    }

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);
    }

    public function getWidgets(): array
    {
        return RoleDashboardConfig::visibleWidgetsForRole(static::dashboardRoleValue());
    }

    public function getColumns(): int | string | array
    {
        return RoleDashboardConfig::columnsForRole(static::dashboardRoleValue());
    }
}