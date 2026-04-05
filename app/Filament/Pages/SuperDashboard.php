<?php

namespace App\Filament\Pages;

use App\Enums\UserRole;
use App\Filament\Widgets\AdminStatsOverview;
use App\Filament\Widgets\RevenueWidget;
use App\Filament\Widgets\RideAnalyticsChart;
use Illuminate\Contracts\Support\Htmlable;

class SuperDashboard extends BaseDashboard
{
    protected static string $routePath = '/super-dashboard';

    protected static string $view = 'filament.pages.super-dashboard';

    protected static function dashboardRole(): UserRole
    {
        return UserRole::SUPER_ADMIN;
    }

    public static function getNavigationLabel(): string
    {
        return 'Super Dashboard';
    }

    public static function getNavigationIcon(): string | Htmlable | null
    {
        return 'heroicon-o-shield-check';
    }

    public function getWidgets(): array
    {
        // Keep this dashboard deterministic and avoid role-configured widget side effects.
        return [];
    }

    protected function getHeaderWidgets(): array
    {
        if ((bool) config('dashboard.super_dashboard_static_mode', false)) {
            return [];
        }

        return [
            AdminStatsOverview::class,
            RideAnalyticsChart::class,
            RevenueWidget::class,
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
