<?php

namespace App\Filament\Pages;

use App\Enums\UserRole;
use App\Filament\Pages\Concerns\HandlesRoleDashboards;
use Filament\Pages\Dashboard\Concerns\HasFiltersForm;
use Illuminate\Contracts\Support\Htmlable;

class AIMonitoringDashboard extends \Filament\Pages\Dashboard
{
    use HandlesRoleDashboards, HasFiltersForm;

    protected static ?string $navigationGroup = 'Dashboards';

    protected static ?int $navigationSort = 2;

    protected static string $routePath = '/ai-monitoring';

    public static function getNavigationLabel(): string
    {
        return 'AI Monitoring Dashboard';
    }

    public static function getNavigationIcon(): string|Htmlable|null
    {
        return 'heroicon-o-cpu-chip';
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::userHasAnyRole(
            auth()->user(),
            ['Super_admin', 'Admin'],
            [UserRole::SUPER_ADMIN, UserRole::ADMIN],
        );
    }

    public static function canAccess(): bool
    {
        return static::userHasAnyRole(
            auth()->user(),
            ['Super_admin', 'Admin'],
            [UserRole::SUPER_ADMIN, UserRole::ADMIN],
        );
    }

    public static function canView(): bool
    {
        return static::userHasAnyRole(
            auth()->user(),
            ['Super_admin', 'Admin'],
            [UserRole::SUPER_ADMIN, UserRole::ADMIN],
        );
    }

    public function getWidgets(): array
    {
        return [
            \App\Filament\Widgets\AIModelAccuracyWidget::class,
            \App\Filament\Widgets\AIDemandHeatmapWidget::class,
            \App\Filament\Widgets\AIDriverDistributionWidget::class,
            \App\Filament\Widgets\AIPredictionLogsWidget::class,
        ];
    }

    public function getColumns(): int|array
    {
        return [
            'md' => 2,
            'xl' => 2,
        ];
    }
}
