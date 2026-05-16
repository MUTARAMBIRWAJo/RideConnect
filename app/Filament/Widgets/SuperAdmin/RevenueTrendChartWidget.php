<?php

namespace App\Filament\Widgets\SuperAdmin;

use App\Services\Dashboard\OperationalDashboardService;
use Filament\Widgets\ChartWidget;

class RevenueTrendChartWidget extends ChartWidget
{
    protected static ?string $heading = 'Revenue Trend (7 Days)';

    protected int|string|array $columnSpan = 1;

    protected function getData(): array
    {
        $chart = app(OperationalDashboardService::class)->revenueTrend(7);

        return [
            'datasets' => [[
                'label' => 'Revenue (RWF)',
                'data' => $chart['data'] ?? [],
                'backgroundColor' => 'rgba(2, 132, 199, 0.45)',
                'borderColor' => '#0284c7',
                'borderWidth' => 1,
            ]],
            'labels' => $chart['labels'] ?? [],
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getPollingInterval(): ?string
    {
        return '90s';
    }
}
