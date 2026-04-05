<?php

namespace App\Filament\Widgets\SuperAdmin;

use App\Services\Dashboard\OperationalDashboardService;
use Filament\Widgets\ChartWidget;

class BookingTripRatioChartWidget extends ChartWidget
{
    protected static ?string $heading = 'Booking vs Trip Ratio (Today)';

    protected int | string | array $columnSpan = 1;

    protected function getData(): array
    {
        $ratio = app(OperationalDashboardService::class)->bookingTripRatio();

        return [
            'datasets' => [[
                'label' => 'Count',
                'data' => [
                    (int) ($ratio['bookings'] ?? 0),
                    (int) ($ratio['trips'] ?? 0),
                ],
                'backgroundColor' => ['#2563eb', '#dc2626'],
            ]],
            'labels' => ['Bookings', 'Trips'],
        ];
    }

    protected function getType(): string
    {
        return 'doughnut';
    }

    protected function getPollingInterval(): ?string
    {
        return '120s';
    }
}
