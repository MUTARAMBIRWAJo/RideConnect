<?php

namespace App\Filament\Widgets\Dashboard;

use App\Models\PlatformCommission;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\Schema;

class DailyCommissionChartWidget extends ChartWidget
{
    protected static ?string $heading = 'Daily Commission (Last 30 Days)';

    protected int | string | array $columnSpan = 'full';

    protected function getData(): array
    {
        if (! Schema::hasTable('platform_commissions')) {
            return $this->emptyData();
        }

        $rows = PlatformCommission::query()
            ->selectRaw('date::text as day, SUM(commission_amount) as total')
            ->whereBetween('date', [now()->subDays(29)->toDateString(), now()->toDateString()])
            ->groupBy('date')
            ->orderBy('date')
            ->pluck('total', 'day');

        // Fill in every day in the range (zero if no data)
        $labels = [];
        $values = [];
        for ($i = 29; $i >= 0; $i--) {
            $day      = now()->subDays($i)->toDateString();
            $labels[] = now()->subDays($i)->format('M d');
            $values[] = round((float) ($rows[$day] ?? 0), 2);
        }

        return [
            'datasets' => [
                [
                    'label'           => 'Commission (RWF)',
                    'data'            => $values,
                    'borderColor'     => '#f59e0b',
                    'backgroundColor' => 'rgba(245, 158, 11, 0.15)',
                    'fill'            => true,
                    'tension'         => 0.3,
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }

    private function emptyData(): array
    {
        return [
            'datasets' => [['label' => 'Commission (RWF)', 'data' => array_fill(0, 30, 0)]],
            'labels'   => array_map(fn ($i) => now()->subDays(29 - $i)->format('M d'), range(0, 29)),
        ];
    }
}
