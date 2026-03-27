<?php

namespace App\Filament\Pages;

use App\Enums\UserRole;
use App\Filament\Pages\Concerns\HandlesRoleDashboards;
use Filament\Pages\Dashboard\Concerns\HasFiltersForm;
use Illuminate\Contracts\Support\Htmlable;

class OfficerDashboardV2 extends \Filament\Pages\Dashboard
{
    use HandlesRoleDashboards, HasFiltersForm;

    protected static ?string $navigationGroup = 'Dashboards';

    protected static string $routePath = '/officer-dashboard-v2';

    protected static string $view = 'filament.pages.officer-dashboard-v2';

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

    protected function getHeaderWidgets(): array
    {
        return [
            \App\Filament\Widgets\Dashboard\OfficerOverviewStats::class,
        ];
    }

    protected function getFooterWidgets(): array
    {
        return [];
    }

    public function getWidgets(): array
    {
        return [
            \App\Filament\Widgets\Dashboard\OfficerOverviewStats::class,
            \App\Filament\Widgets\Dashboard\ActivityFeedWidget::class,
            \App\Filament\Widgets\Dashboard\NotificationsWidget::class,
            \App\Filament\Widgets\Dashboard\TransactionsTableWidget::class,
            \App\Filament\Widgets\LatestRidesTable::class,
            \App\Filament\Widgets\RideMapWidget::class,
            \App\Filament\Widgets\DemandHeatmapWidget::class,
        ];
    }
}
