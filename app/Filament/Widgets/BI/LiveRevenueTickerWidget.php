<?php

namespace App\Filament\Widgets\BI;

use App\Modules\Reporting\Services\ReportingService;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class LiveRevenueTickerWidget extends BaseWidget
{
    protected static ?string $pollingInterval = '60s';
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        /** @var ReportingService $reporting */
        $reporting = app(ReportingService::class);
        $today     = $reporting->getRevenueSummaryToday();

        $grossFmt  = 'RWF ' . number_format($today['gross_revenue'] ?? 0);
        $netFmt    = 'RWF ' . number_format($today['net_revenue'] ?? 0);
        $rideCount = (int) ($today['ride_count'] ?? 0);

        return [
            Stat::make('Gross Revenue Today', $grossFmt)
                ->description('All completed rides')
                ->color('success')
                ->icon('heroicon-o-banknotes'),

            Stat::make('Net Revenue Today', $netFmt)
                ->description('After driver payouts')
                ->color('primary')
                ->icon('heroicon-o-arrow-trending-up'),

            Stat::make('Rides Today', number_format($rideCount))
                ->description('Completed trips')
                ->color('info')
                ->icon('heroicon-o-map-pin'),
        ];
    }
}
