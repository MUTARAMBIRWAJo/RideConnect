<?php

namespace App\Filament\Pages;

use Filament\Pages\Dashboard\Concerns\HasFiltersForm;
use Illuminate\Contracts\Support\Htmlable;

class AIMonitoringDashboard extends \Filament\Pages\Dashboard
{
    use HasFiltersForm;
    protected static ?string $navigationGroup = 'Dashboards';

    protected static ?int $navigationSort = 2;

    protected static string $routePath = '/ai-monitoring';

    public static function getNavigationLabel(): string
    {
        return 'AI Monitoring Dashboard';
    }

    public static function getNavigationIcon(): string | Htmlable | null
    {
        return 'heroicon-o-cpu-chip';
    }

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->check() && auth()->user()->hasRole('ai-admin');
    }

    public static function canAccess(): bool
    {
        return auth()->check() && auth()->user()->hasRole('ai-admin');
    }

    public static function canView(): bool
    {
        return auth()->check() && auth()->user()->hasRole('ai-admin');
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

    public function getColumns(): int | array
    {
        return [
            'md' => 2,
            'xl' => 2,
        ];
    }
}
