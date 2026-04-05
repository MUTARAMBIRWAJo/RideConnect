<?php

namespace App\Filament\Widgets;

use App\Services\Dashboard\SuperDashboardMetricsService;
use Filament\Widgets\ChartWidget;

class RideAnalyticsChart extends ChartWidget
{
    protected static ?string $heading = 'Ride Activity (Last 7 Days)';

    protected int | string | array $columnSpan = [
        'default' => 1,
        'md' => 1,
    ];

    protected function getPollingInterval(): ?string
    {
        return '60s';
    }

    protected function getData(): array
    {
        $analytics = app(SuperDashboardMetricsService::class)->getRideAnalytics(7);

        return [
            'datasets' => [[
                'label' => 'Rides',
                'data' => $analytics['totals'] ?? [],
                'borderColor' => '#0f766e',
                'backgroundColor' => 'rgba(15, 118, 110, 0.18)',
                'fill' => true,
                'tension' => 0.3,
            ]],
            'labels' => $analytics['labels'] ?? [],
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}