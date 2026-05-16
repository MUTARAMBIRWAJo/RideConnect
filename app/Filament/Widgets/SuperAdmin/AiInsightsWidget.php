<?php

namespace App\Filament\Widgets\SuperAdmin;

use App\Services\AiDashboardService;
use Filament\Widgets\Widget;

class AiInsightsWidget extends Widget
{
    protected static string $view = 'filament.widgets.super-admin.ai-insights-widget';

    protected int|string|array $columnSpan = 1;

    protected function getViewData(): array
    {
        $ai = app(AiDashboardService::class);
        $demandZones = $ai->getDemandZones();
        $surgeZones = $ai->getSurgePredictions();
        $eta = $ai->getEtaPredictions();

        $busyHours = collect($demandZones)
            ->sortByDesc('score')
            ->take(3)
            ->map(fn (array $zone): string => (string) $zone['zone'])
            ->values()
            ->all();

        return compact('demandZones', 'surgeZones', 'eta', 'busyHours');
    }
}
