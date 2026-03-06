<?php

namespace App\Filament\Pages;

use App\Enums\UserRole;
use App\Filament\Pages\Concerns\HandlesRoleDashboards;
use App\Filament\Widgets\Dashboard\OfficerOverviewStats;
use App\Filament\Widgets\DemandHeatmapWidget;
use App\Filament\Widgets\DriverAvailabilityChart;
use App\Filament\Widgets\LatestRidesTable;
use Illuminate\Contracts\Support\Htmlable;

class OfficerDashboard extends \Filament\Pages\Dashboard
{
    use HandlesRoleDashboards;

    protected static string $routePath = '/officer-dashboard';

    public static function getNavigationLabel(): string
    {
        return 'Officer Dashboard';
    }

    public static function getNavigationIcon(): string | Htmlable | null
    {
        return 'heroicon-o-clipboard-document-check';
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return static::userHasRole($user, 'Officer', UserRole::OFFICER);
    }

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);
    }

    public function getWidgets(): array
    {
        return [
            OfficerOverviewStats::class,
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
