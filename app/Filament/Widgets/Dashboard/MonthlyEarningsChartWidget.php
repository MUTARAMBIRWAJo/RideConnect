<?php

namespace App\Filament\Widgets\Dashboard;

use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class MonthlyEarningsChartWidget extends ChartWidget
{
    protected static ?string $heading = 'Monthly Earnings Chart';

    protected int | string | array $columnSpan = 'full';

    protected function getData(): array
    {
        [$table, $amountColumn] = $this->resolveFinanceSource();

        $labels = collect(range(5, 0))->map(fn (int $offset) => now()->subMonths($offset)->format('M'))->values();
        $labels->push(now()->format('M'));

        if (!$table || !$amountColumn || !Schema::hasColumn($table, 'created_at')) {
            return [
                'datasets' => [[
                    'label' => 'Earnings',
                    'data' => array_fill(0, $labels->count(), 0),
                ]],
                'labels' => $labels->all(),
            ];
        }

        $data = $labels->map(function (string $label, int $index) use ($table, $amountColumn, $labels) {
            $target = now()->subMonths(($labels->count() - 1) - $index);

            return (float) DB::table($table)
                ->whereYear('created_at', $target->year)
                ->whereMonth('created_at', $target->month)
                ->sum($amountColumn);
        })->all();

        return [
            'datasets' => [[
                'label' => 'Earnings',
                'data' => $data,
                'borderColor' => '#15803d',
                'backgroundColor' => 'rgba(21, 128, 61, 0.15)',
            ]],
            'labels' => $labels->all(),
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }

    private function resolveFinanceSource(): array
    {
        foreach (['payments_v2', 'payments'] as $table) {
            if (!Schema::hasTable($table)) {
                continue;
            }

            foreach (['amount', 'total_amount', 'fare_amount'] as $column) {
                if (Schema::hasColumn($table, $column)) {
                    return [$table, $column];
                }
            }
        }

        return [null, null];
    }
}
