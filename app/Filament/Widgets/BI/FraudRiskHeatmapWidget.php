<?php

namespace App\Filament\Widgets\BI;

use App\Filament\Support\RoleDashboardConfig;
use App\Modules\Reporting\Services\ReportingService;
use Filament\Widgets\ChartWidget;

class FraudRiskHeatmapWidget extends ChartWidget
{
    protected static ?string $heading = 'Fraud Risk by Severity';
    protected static ?int $sort = 5;

    public static function isLazy(): bool
    {
        return RoleDashboardConfig::isWidgetLazy(static::class, true);
    }

    protected function getPollingInterval(): ?string
    {
        return RoleDashboardConfig::pollingIntervalForWidget(static::class, '300s');
    }

    protected function getData(): array
    {
        /** @var ReportingService $reporting */
        $reporting = app(ReportingService::class);
        $risk      = $reporting->getFraudRisk();

        $bySeverity = $risk['by_severity'] ?? [];

        $labels = [];
        $counts = [];
        $colors = [];

        $palette = [
            'low'      => '#22c55e',
            'medium'   => '#f59e0b',
            'high'     => '#ef4444',
            'critical' => '#7c3aed',
        ];

        foreach (['low', 'medium', 'high', 'critical'] as $sev) {
            $labels[] = ucfirst($sev);
            $counts[] = $bySeverity[$sev] ?? 0;
            $colors[] = $palette[$sev];
        }

        return [
            'datasets' => [
                [
                    'label'           => 'Fraud Flags',
                    'data'            => $counts,
                    'backgroundColor' => $colors,
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'doughnut';
    }
}
