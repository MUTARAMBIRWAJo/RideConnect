<?php

namespace App\Filament\Pages;

use App\Enums\UserRole;
use App\Filament\Pages\Concerns\HandlesRoleDashboards;
use App\Filament\Widgets\Dashboard\AdminOverviewStats;
use App\Filament\Widgets\DemandHeatmapWidget;
use App\Filament\Widgets\DriverAvailabilityChart;
use App\Filament\Widgets\LatestRidesTable;
use App\Filament\Widgets\RideStatsOverview;
use Illuminate\Contracts\Support\Htmlable;

class AdminDashboard extends \Filament\Pages\Dashboard
{
    use HandlesRoleDashboards;

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
        return static::canAccess();
    }

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return static::userHasRole($user, 'Admin', UserRole::ADMIN);
    }

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);
    }

    public function getWidgets(): array
    {
        return [
            AdminOverviewStats::class,
            RideStatsOverview::class,
            DriverAvailabilityChart::class,
            DemandHeatmapWidget::class,
            LatestRidesTable::class,
        ];
    }

    public function getColumns(): int | string | array
    {
        return [
            'md' => 2,
            'xl' => 3,
        ];
    }
}
