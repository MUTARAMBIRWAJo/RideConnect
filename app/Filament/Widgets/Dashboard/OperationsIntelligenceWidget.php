<?php

namespace App\Filament\Widgets\Dashboard;

use App\Filament\Support\RoleDashboardConfig;
use App\Services\OperationsIntelligenceDataService;
use Filament\Widgets\Widget;

class OperationsIntelligenceWidget extends Widget
{
    protected static string $view = 'filament.widgets.dashboard.operations-intelligence-widget';

    protected int|string|array $columnSpan = [
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
        return RoleDashboardConfig::pollingIntervalForWidget(static::class, '120s');
    }

    protected function getViewData(): array
    {
        /** @var OperationsIntelligenceDataService $service */
        $service = app(OperationsIntelligenceDataService::class);

        return [
            'kpis' => $service->kpis(),
            'dailyTrend' => $service->dailyTrend(),
            'statusMix' => $service->statusMix(),
            'topRoutes' => array_slice($service->topRoutes(), 0, 5),
            'exportFrom' => now()->subDays(29)->toDateString(),
            'exportTo' => now()->toDateString(),
        ];
    }
}
