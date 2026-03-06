<?php

namespace App\Filament\Widgets\Dashboard;

use App\Models\PlatformCommission;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Schema;

class CommissionOverviewWidget extends StatsOverviewWidget
{
    protected int | string | array $columnSpan = 'full';

    protected function getStats(): array
    {
        if (! Schema::hasTable('platform_commissions')
            || ! Schema::hasColumn('platform_commissions', 'commission_amount')
            || ! Schema::hasColumn('platform_commissions', 'date')) {
            return $this->emptyStats();
        }

        $today = (float) PlatformCommission::query()
            ->whereDate('date', now()->toDateString())
            ->sum('commission_amount');

        $week = (float) PlatformCommission::query()
            ->whereBetween('date', [now()->startOfWeek()->toDateString(), now()->endOfWeek()->toDateString()])
            ->sum('commission_amount');

        $month = (float) PlatformCommission::query()
            ->whereBetween('date', [now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString()])
            ->sum('commission_amount');

        $all = (float) PlatformCommission::query()->sum('commission_amount');

        return [
            Stat::make('Total Commission Today', 'RWF ' . number_format($today, 2))
                ->description('Current day retained commission')
                ->color('success'),
            Stat::make('Total Commission This Week', 'RWF ' . number_format($week, 2))
                ->description('Week-to-date commission')
                ->color('info'),
            Stat::make('Total Commission This Month', 'RWF ' . number_format($month, 2))
                ->description('Month-to-date commission')
                ->color('warning'),
            Stat::make('Total Commission All Time', 'RWF ' . number_format($all, 2))
                ->description('Lifetime retained commission')
                ->color('primary'),
        ];
    }

    private function emptyStats(): array
    {
        return [
            Stat::make('Total Commission Today', 'RWF 0.00')
                ->description('Current day retained commission')
                ->color('success'),
            Stat::make('Total Commission This Week', 'RWF 0.00')
                ->description('Week-to-date commission')
                ->color('info'),
            Stat::make('Total Commission This Month', 'RWF 0.00')
                ->description('Month-to-date commission')
                ->color('warning'),
            Stat::make('Total Commission All Time', 'RWF 0.00')
                ->description('Lifetime retained commission')
                ->color('primary'),
        ];
    }
}
