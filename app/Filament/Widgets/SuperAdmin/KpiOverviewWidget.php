<?php

namespace App\Filament\Widgets\SuperAdmin;

use App\Services\AiDashboardService;
use App\Services\Dashboard\OperationalDashboardService;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class KpiOverviewWidget extends StatsOverviewWidget
{
    protected int|string|array $columnSpan = 'full';

    protected function getStats(): array
    {
        $metrics = app(OperationalDashboardService::class)->getMainKpis();
        $surgeZones = collect(app(AiDashboardService::class)->getSurgePredictions())
            ->filter(fn (array $zone): bool => (float) ($zone['multiplier'] ?? 1.0) > 1.1)
            ->count();

        return [
            Stat::make('Total Rides Today', number_format((int) ($metrics['total_rides_today'] ?? 0)))
                ->description('Trips and bookings created today')
                ->color('primary'),

            Stat::make('Active Drivers', number_format((int) ($metrics['active_drivers'] ?? 0)))
                ->description('Currently active or online')
                ->color('success'),

            Stat::make('Active Trips', number_format((int) ($metrics['active_trips'] ?? 0)))
                ->description('Pending, accepted, or started')
                ->color('warning'),

            Stat::make('Revenue Today', 'RWF '.number_format((float) ($metrics['revenue_today'] ?? 0), 2))
                ->description('Payments recorded today')
                ->color('success'),

            Stat::make('Cancellation Rate', number_format((float) ($metrics['cancellation_rate'] ?? 0), 2).'%')
                ->description('Cancelled trips vs total trips today')
                ->color('danger'),

            Stat::make('AI Surge Zones', number_format($surgeZones))
                ->description('Predicted high-pressure zones')
                ->color('info'),
        ];
    }
}
