<?php

namespace App\Filament\Pages;

use App\Enums\UserRole;
use App\Filament\Pages\Concerns\HandlesRoleDashboards;
use App\Filament\Support\RoleDashboardConfig;
use Filament\Pages\Dashboard\Concerns\HasFiltersForm;
use Illuminate\Contracts\Support\Htmlable;

class OfficerDashboardV2 extends \Filament\Pages\Dashboard
{
    use HandlesRoleDashboards, HasFiltersForm;

    protected static ?string $navigationGroup = 'Dashboards';

    protected static string $routePath = '/officer-dashboard-v2';

    public static function getNavigationLabel(): string
    {
        return 'Officer Dashboard V2';
    }

    public static function getNavigationIcon(): string | Htmlable | null
    {
        return 'heroicon-o-clipboard-document-check';
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::userHasRole(auth()->user(), 'Officer', UserRole::OFFICER);
    }

    public static function canAccess(): bool
    {
        return static::userHasRole(auth()->user(), 'Officer', UserRole::OFFICER);
    }

    public static function canView(): bool
    {
        return static::userHasRole(auth()->user(), 'Officer', UserRole::OFFICER);
    }

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);
    }

    public function getWidgets(): array
    {
        return RoleDashboardConfig::visibleWidgetsForRole(UserRole::OFFICER->value);
    }

    public function getColumns(): int | string | array
    {
        return RoleDashboardConfig::columnsForRole(UserRole::OFFICER->value);
    }
}
