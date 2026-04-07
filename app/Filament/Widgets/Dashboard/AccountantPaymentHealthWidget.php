<?php

namespace App\Filament\Widgets\Dashboard;

use App\Filament\Support\RoleDashboardConfig;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AccountantPaymentHealthWidget extends StatsOverviewWidget
{
    protected int | string | array $columnSpan = 'full';

    public static function isLazy(): bool
    {
        return RoleDashboardConfig::isWidgetLazy(static::class, true);
    }

    protected function getPollingInterval(): ?string
    {
        return RoleDashboardConfig::pollingIntervalForWidget(static::class, '120s');
    }

    protected function getStats(): array
    {
        [$table, $amountColumn] = $this->resolvePaymentSource();

        if (! $table || ! $amountColumn) {
            return [
                Stat::make('Successful Payments (24h)', '0')->description('Last 24 hours')->color('success'),
                Stat::make('Failed Payments (24h)', '0')->description('Last 24 hours')->color('danger'),
                Stat::make('Success Rate', '0%')->description('Payment reliability')->color('info'),
                Stat::make('Volume (24h)', 'RWF 0')->description('Successful amount')->color('primary'),
            ];
        }

        $base = DB::table($table)->where('created_at', '>=', now()->subDay());

        $successCount = (int) (clone $base)
            ->whereIn('status', ['successful', 'SUCCESSFUL', 'paid', 'PAID', 'completed', 'COMPLETED'])
            ->count();

        $failedCount = (int) (clone $base)
            ->whereIn('status', ['failed', 'FAILED', 'declined', 'DECLINED'])
            ->count();

        $successVolume = (float) (clone $base)
            ->whereIn('status', ['successful', 'SUCCESSFUL', 'paid', 'PAID', 'completed', 'COMPLETED'])
            ->sum($amountColumn);

        $total = $successCount + $failedCount;
        $successRate = $total > 0 ? round(($successCount / $total) * 100, 2) : 0;

        return [
            Stat::make('Successful Payments (24h)', number_format($successCount))
                ->description('Last 24 hours')
                ->color('success'),
            Stat::make('Failed Payments (24h)', number_format($failedCount))
                ->description('Last 24 hours')
                ->color('danger'),
            Stat::make('Success Rate', number_format($successRate, 2) . '%')
                ->description('Payment reliability')
                ->color('info'),
            Stat::make('Volume (24h)', 'RWF ' . number_format($successVolume, 0))
                ->description('Successful amount')
                ->color('primary'),
        ];
    }

    private function resolvePaymentSource(): array
    {
        foreach (['payments_v2', 'payments'] as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            if (! Schema::hasColumn($table, 'status') || ! Schema::hasColumn($table, 'created_at')) {
                continue;
            }

            foreach (['amount', 'total_amount', 'fare_amount'] as $column) {
                if (Schema::hasColumn($table, $column)) {
                    return [$table, $column];
                }
            }
        }

        return [null, null];
    }
}
