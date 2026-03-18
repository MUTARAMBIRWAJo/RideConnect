<?php

namespace App\Filament\Widgets\Dashboard;

use App\Filament\Support\RoleDashboardConfig;
use App\Services\FinancialMatrixDataService;
use Filament\Widgets\Widget;

class FinancialMatrixWidget extends Widget
{
    protected static string $view = 'filament.widgets.dashboard.financial-matrix-widget';

    protected int | string | array $columnSpan = [
        'default' => 1,
        'md' => 2,
        'xl' => 2,
    ];

    public static function isLazy(): bool
    {
        return RoleDashboardConfig::isWidgetLazy(static::class, false);
    }

    protected function getPollingInterval(): ?string
    {
        return RoleDashboardConfig::pollingIntervalForWidget(static::class, '300s');
    }

    protected function getViewData(): array
    {
        /** @var FinancialMatrixDataService $service */
        $service = app(FinancialMatrixDataService::class);
        $from = now()->subDays(29)->toDateString();
        $to = now()->toDateString();
        $snapshot = $service->snapshot($from, $to);

        return [
            'matrix' => $snapshot['matrix'],
            'dailyRows' => $snapshot['daily_rows'],
            'period' => $snapshot['period'],
        ];
    }
}
