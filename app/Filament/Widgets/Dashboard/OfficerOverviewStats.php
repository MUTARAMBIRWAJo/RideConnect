<?php

namespace App\Filament\Widgets\Dashboard;

use App\Models\Driver;
use App\Models\Ride;
use App\Models\Ticket;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Schema;

class OfficerOverviewStats extends StatsOverviewWidget
{
    protected int | string | array $columnSpan = 'full';

    protected function getStats(): array
    {
        $ridesToday = Ride::whereDate('created_at', now()->toDateString())->count();
        $openTickets = Ticket::whereIn('status', ['OPEN', 'open'])->count();
        $driversOnline = Schema::hasColumn('drivers', 'is_online')
            ? Driver::where('is_online', true)->count()
            : Driver::whereIn('status', ['approved', 'APPROVED', 'active', 'ACTIVE'])->count();
        $demandForecast = (int) round(Ride::whereDate('created_at', '>=', now()->subDays(7)->toDateString())->count() / 7);

        return [
            Stat::make('Rides Today', number_format($ridesToday))
                ->description('Trips created today')
                ->color('primary'),
            Stat::make('Open Tickets', number_format($openTickets))
                ->description('Items needing follow-up')
                ->color('warning'),
            Stat::make('Drivers Online', number_format($driversOnline))
                ->description('Available for assignments')
                ->color('success'),
            Stat::make('Demand Forecast', number_format($demandForecast) . ' avg/day')
                ->description('7-day rolling estimate')
                ->color('info'),
        ];
    }
}
