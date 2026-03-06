<?php

namespace App\Filament\Widgets\BI;

use App\Modules\Reporting\Services\ReportingService;
use Filament\Widgets\ChartWidget;

class RevenueOverTimeChartWidget extends ChartWidget
{
    protected static ?string $heading = 'Revenue Over Time (Last 30 Days)';
    protected static ?string $pollingInterval = '300s';
    protected static ?int $sort = 3;
    protected int | string | array $columnSpan = 'full';

    protected function getData(): array
    {
        /** @var ReportingService $reporting */
        $reporting = app(ReportingService::class);
        $monthly   = $reporting->getMonthlyGrowth();

        $labels  = array_column($monthly, 'month');
        $revenue = array_column($monthly, 'gross_revenue');
        $commission = array_column($monthly, 'total_commission');

        return [
            'datasets' => [
                [
                    'label'           => 'Gross Revenue (RWF)',
                    'data'            => $revenue,
                    'borderColor'     => '#0ea5e9',
                    'backgroundColor' => 'rgba(14, 165, 233, 0.1)',
                    'fill'            => true,
                    'tension'         => 0.4,
                ],
                [
                    'label'           => 'Commission (RWF)',
                    'data'            => $commission,
                    'borderColor'     => '#22c55e',
                    'backgroundColor' => 'rgba(34, 197, 94, 0.1)',
                    'fill'            => false,
                    'tension'         => 0.4,
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}
