<?php

namespace App\Filament\Widgets;

use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Cache;

class AdminStats extends BaseWidget
{
    protected function getStats(): array
    {
        $stats = Cache::remember('admin_dashboard_stats', 300, function () {
            return \DB::selectOne("
                SELECT
                    COUNT(*) FILTER (WHERE status IN ('in_progress', 'accepted')) as active_rides,
                    COUNT(*) FILTER (WHERE created_at::date = CURRENT_DATE) as rides_today,
                    COUNT(*) FILTER (WHERE status IN ('completed', 'COMPLETED')) as completed_rides
                FROM rides
            ");
        });

        return [
            Stat::make('Active Rides', number_format($stats->active_rides ?? 0))
                ->description('Currently in progress')
                ->color('primary'),
            Stat::make('Today\'s Rides', number_format($stats->rides_today ?? 0))
                ->description('Rides started today')
                ->color('success'),
            Stat::make('Completed Rides', number_format($stats->completed_rides ?? 0))
                ->description('Total completed rides')
                ->color('info'),
        ];
    }
}
