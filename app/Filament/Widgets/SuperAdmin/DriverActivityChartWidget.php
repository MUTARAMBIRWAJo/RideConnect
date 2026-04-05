<?php

namespace App\Filament\Widgets\SuperAdmin;

use App\Services\Dashboard\OperationalDashboardService;
use Filament\Widgets\ChartWidget;

class DriverActivityChartWidget extends ChartWidget
{
    protected static ?string $heading = 'Driver Activity (12h)';

    protected int | string | array $columnSpan = 1;

    protected function getData(): array
    {
        $chart = app(OperationalDashboardService::class)->driverActivity(12);

        return [
            'datasets' => [[
                'label' => 'Active Drivers',
                'data' => $chart['data'] ?? [],
                'borderColor' => '#16a34a',
                'backgroundColor' => 'rgba(22, 163, 74, 0.20)',
                'fill' => true,
                'tension' => 0.3,
            ]],
            'labels' => $chart['labels'] ?? [],
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }

    protected function getPollingInterval(): ?string
    {
        return '90s';
    }
}
