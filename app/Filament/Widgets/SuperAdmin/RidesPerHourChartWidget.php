<?php

namespace App\Filament\Widgets\SuperAdmin;

use App\Services\Dashboard\OperationalDashboardService;
use Filament\Widgets\ChartWidget;

class RidesPerHourChartWidget extends ChartWidget
{
    protected static ?string $heading = 'Rides Per Hour';

    protected int | string | array $columnSpan = 1;

    protected function getData(): array
    {
        $chart = app(OperationalDashboardService::class)->ridesPerHour();

        return [
            'datasets' => [[
                'label' => 'Rides',
                'data' => $chart['data'] ?? [],
                'borderColor' => '#0f766e',
                'backgroundColor' => 'rgba(15, 118, 110, 0.22)',
                'fill' => true,
                'tension' => 0.35,
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
        return '60s';
    }
}
