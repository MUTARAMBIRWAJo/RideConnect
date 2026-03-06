<?php

namespace App\Filament\Widgets\Dashboard;

use App\Models\Ride;
use App\Models\User;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SuperAdminOverviewStats extends StatsOverviewWidget
{
    protected int | string | array $columnSpan = 'full';

    protected function getStats(): array
    {
        $totalUsers = User::count();
        $totalRevenue = $this->resolveTotalRevenue();
        $activeRides = Ride::whereIn('status', ['in_progress', 'accepted', 'IN_PROGRESS', 'ACCEPTED'])->count();
        $pendingApprovals = User::where('role', 'DRIVER')->where('is_approved', false)->count();

        return [
            Stat::make('Total Users', number_format($totalUsers))
                ->description('All registered accounts')
                ->color('primary'),
            Stat::make('Total Revenue', 'RWF ' . number_format($totalRevenue, 2))
                ->description('Across all recorded payments')
                ->color('success'),
            Stat::make('Active Rides', number_format($activeRides))
                ->description('In progress right now')
                ->color('warning'),
            Stat::make('Driver Approvals Pending', number_format($pendingApprovals))
                ->description('Waiting for verification')
                ->color('info'),
        ];
    }

    private function resolveTotalRevenue(): float
    {
        foreach (['payments_v2', 'payments'] as $table) {
            if (!Schema::hasTable($table)) {
                continue;
            }

            foreach (['amount', 'total_amount', 'fare_amount'] as $column) {
                if (Schema::hasColumn($table, $column)) {
                    return (float) DB::table($table)->sum($column);
                }
            }
        }

        return 0;
    }
}
