<?php

namespace App\Filament\Widgets;

use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\Cache;

class RideAnalytics extends ChartWidget
{
    protected static ?string $heading = 'Ride Analytics';

    protected function getData(): array
    {
        $data = Cache::remember('ride_analytics_chart', 600, function () {
            // Get last 7 days data
            $dates = collect();
            for ($i = 6; $i >= 0; $i--) {
                $date = now()->subDays($i)->format('Y-m-d');
                $count = \DB::table('rides')
                    ->whereDate('created_at', $date)
                    ->count();
                $dates->push($count);
            }
            return $dates->toArray();
        });

        return [
            'datasets' => [
                [
                    'label' => 'Rides per Day',
                    'data' => $data,
                    'borderColor' => 'rgb(59, 130, 246)',
                    'backgroundColor' => 'rgba(59, 130, 246, 0.1)',
                ],
            ],
            'labels' => ['6 days ago', '5 days ago', '4 days ago', '3 days ago', '2 days ago', 'Yesterday', 'Today'],
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}