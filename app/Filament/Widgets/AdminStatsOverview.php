<?php

namespace App\Filament\Widgets;

use App\Services\Dashboard\SuperDashboardMetricsService;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class AdminStatsOverview extends StatsOverviewWidget
{
    protected int | string | array $columnSpan = 'full';

    protected function getStats(): array
    {
        $metrics = app(SuperDashboardMetricsService::class)->getOverviewStats();

        return [
            Stat::make('Total Users', number_format((int) ($metrics['total_users'] ?? 0)))
                ->description('All registered accounts')
                ->color('primary'),
            Stat::make('Pending Approvals', number_format((int) ($metrics['pending_users'] ?? 0)))
                ->description('Users awaiting approval')
                ->color('warning'),
            Stat::make('Active Rides', number_format((int) ($metrics['active_rides'] ?? 0)))
                ->description('Rides currently in progress')
                ->color('success'),
            Stat::make('Revenue', 'RWF ' . number_format((float) ($metrics['total_revenue'] ?? 0), 2))
                ->description('Total recorded payments')
                ->color('info'),
        ];
    }
}