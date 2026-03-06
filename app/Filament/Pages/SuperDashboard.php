<?php

namespace App\Filament\Pages;

use App\Enums\UserRole;
use App\Filament\Pages\Concerns\HandlesRoleDashboards;
use App\Filament\Widgets\Dashboard\SuperAdminOverviewStats;
use App\Filament\Widgets\Dashboard\SystemLogsWidget;
use App\Filament\Widgets\DemandHeatmapWidget;
use App\Filament\Widgets\DriverAvailabilityChart;
use App\Filament\Widgets\RideStatsOverview;
use Filament\Widgets\Widget;
use Illuminate\Contracts\Support\Htmlable;

class SuperDashboard extends \Filament\Pages\Dashboard
{
    use HandlesRoleDashboards;

    protected static string $routePath = '/super-dashboard';

    public static function getNavigationLabel(): string
    {
        return 'Super Dashboard';
    }

    public static function getNavigationIcon(): string | Htmlable | null
    {
        return 'heroicon-o-shield-check';
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return static::userHasRole($user, 'Super_admin', UserRole::SUPER_ADMIN);
    }

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);
    }

    public function getWidgets(): array
    {
        return [
            SuperAdminOverviewStats::class,
            RideStatsOverview::class,
            DriverAvailabilityChart::class,
            DemandHeatmapWidget::class,
            SystemLogsWidget::class,
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
