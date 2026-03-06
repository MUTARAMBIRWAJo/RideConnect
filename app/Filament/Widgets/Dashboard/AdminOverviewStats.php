<?php

namespace App\Filament\Widgets\Dashboard;

use App\Models\Driver;
use App\Models\Ride;
use App\Models\Ticket;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Schema;

class AdminOverviewStats extends StatsOverviewWidget
{
    protected int | string | array $columnSpan = 'full';

    protected function getStats(): array
    {
        $activeRides = Ride::whereIn('status', ['in_progress', 'accepted', 'IN_PROGRESS', 'ACCEPTED'])->count();
        $driversOnline = Schema::hasColumn('drivers', 'is_online')
            ? Driver::where('is_online', true)->count()
            : Driver::whereIn('status', ['approved', 'APPROVED', 'active', 'ACTIVE'])->count();
        $rideApprovals = Ride::whereIn('status', ['pending', 'PENDING', 'requested', 'REQUESTED'])->count();
        $ticketOverview = Ticket::whereIn('status', ['OPEN', 'open'])->count();

        $totalRides = Ride::count();
        $completedRides = Ride::whereIn('status', ['completed', 'COMPLETED'])->count();
        $performance = $totalRides > 0 ? round(($completedRides / $totalRides) * 100, 1) : 0;

        return [
            Stat::make('Active Rides', number_format($activeRides))
                ->description('Trips currently in progress')
                ->color('primary'),
            Stat::make('Drivers Online', number_format($driversOnline))
                ->description('Currently available drivers')
                ->color('success'),
            Stat::make('Ride Approvals', number_format($rideApprovals))
                ->description('Rides awaiting action')
                ->color('warning'),
            Stat::make('Open Tickets', number_format($ticketOverview))
                ->description('Pending support workload')
                ->color('info'),
            Stat::make('Completion Rate', $performance . '%')
                ->description('Completed rides ratio')
                ->color('gray'),
        ];
    }
}
