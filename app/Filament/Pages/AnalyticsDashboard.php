<?php

namespace App\Filament\Pages;

use App\Enums\UserRole;
use App\Filament\Widgets\SuperAdmin\AiInsightsWidget;
use App\Filament\Widgets\SuperAdmin\BookingTripRatioChartWidget;
use App\Filament\Widgets\SuperAdmin\DriverActivityChartWidget;
use App\Filament\Widgets\SuperAdmin\RevenueTrendChartWidget;
use App\Filament\Widgets\SuperAdmin\RidesPerHourChartWidget;
use Illuminate\Contracts\Support\Htmlable;

class AnalyticsDashboard extends BaseDashboard
{
    protected static string $routePath = '/analytics-dashboard';

    protected static string $view = 'filament.pages.analytics-dashboard';

    protected static function dashboardRole(): UserRole
    {
        return UserRole::SUPER_ADMIN;
    }

    public static function getNavigationLabel(): string
    {
        return 'Analytics Dashboard';
    }

    public static function getNavigationIcon(): string | Htmlable | null
    {
        return 'heroicon-o-chart-bar-square';
    }

    public function getWidgets(): array
    {
        return [];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            RidesPerHourChartWidget::class,
            RevenueTrendChartWidget::class,
            DriverActivityChartWidget::class,
            BookingTripRatioChartWidget::class,
            AiInsightsWidget::class,
        ];
    }

    public function getColumns(): int | string | array
    {
        return [
            'default' => 1,
            'md' => 2,
            'xl' => 2,
        ];
    }
}
