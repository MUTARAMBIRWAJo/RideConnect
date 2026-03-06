<?php

namespace App\Filament\Widgets\BI;

use App\Modules\Reporting\Services\ReportingService;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class CommissionTodayWidget extends BaseWidget
{
    protected static ?string $pollingInterval = '60s';
    protected static ?int $sort = 2;

    protected function getStats(): array
    {
        /** @var ReportingService $reporting */
        $reporting = app(ReportingService::class);
        $today     = $reporting->getRevenueSummaryToday();
        $trend     = $reporting->getCommissionTrend(7);

        $commission   = $today['total_commission'] ?? 0;
        $avgDaily     = count($trend) > 0
            ? array_sum(array_column($trend, 'commission')) / count($trend)
            : 0;
        $trend7       = $avgDaily > 0
            ? (($commission - $avgDaily) / $avgDaily * 100)
            : 0;
        $trendLabel   = ($trend7 >= 0 ? '+' : '') . number_format($trend7, 1) . '% vs 7d avg';

        return [
            Stat::make('Commission Today', 'RWF ' . number_format($commission))
                ->description($trendLabel)
                ->color($trend7 >= 0 ? 'success' : 'warning')
                ->icon('heroicon-o-currency-dollar'),

            Stat::make('Avg Daily Commission (7d)', 'RWF ' . number_format($avgDaily))
                ->description('7-day rolling average')
                ->color('primary')
                ->icon('heroicon-o-chart-bar'),
        ];
    }
}
