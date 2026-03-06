<?php

namespace App\Filament\Widgets\Dashboard;

use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AccountantRevenueSummary extends StatsOverviewWidget
{
    protected int | string | array $columnSpan = 'full';

    protected function getStats(): array
    {
        [$table, $column] = $this->resolveFinanceSource();

        if (!$table || !$column) {
            return [
                Stat::make('Revenue Summary', 'RWF 0.00'),
                Stat::make('This Month', 'RWF 0.00'),
                Stat::make('Transactions', '0'),
                Stat::make('Avg Transaction', 'RWF 0.00'),
            ];
        }

        $totalRevenue = (float) DB::table($table)->sum($column);
        $monthlyRevenue = (float) DB::table($table)
            ->whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->sum($column);
        $transactionCount = (int) DB::table($table)->count();
        $avgTransaction = $transactionCount > 0 ? ($totalRevenue / $transactionCount) : 0;

        return [
            Stat::make('Revenue Summary', 'RWF ' . number_format($totalRevenue, 2))
                ->description('All-time processed revenue')
                ->color('success'),
            Stat::make('This Month', 'RWF ' . number_format($monthlyRevenue, 2))
                ->description('Current month total')
                ->color('primary'),
            Stat::make('Transactions', number_format($transactionCount))
                ->description('Recorded payment count')
                ->color('info'),
            Stat::make('Avg Transaction', 'RWF ' . number_format($avgTransaction, 2))
                ->description('Mean amount per payment')
                ->color('gray'),
        ];
    }

    private function resolveFinanceSource(): array
    {
        foreach (['payments_v2', 'payments'] as $table) {
            if (!Schema::hasTable($table)) {
                continue;
            }

            foreach (['amount', 'total_amount', 'fare_amount'] as $column) {
                if (Schema::hasColumn($table, $column) && Schema::hasColumn($table, 'created_at')) {
                    return [$table, $column];
                }
            }
        }

        return [null, null];
    }
}
