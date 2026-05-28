<?php

namespace App\Filament\Widgets;

use App\Filament\Support\RoleDashboardConfig;
use Filament\Widgets\Widget;

class DemandHeatmapWidget extends Widget
{
    protected static string $view = 'filament.widgets.demand-heatmap-widget';

    protected int|string|array $columnSpan = [
        'md' => 1,
        'xl' => 2,
    ];

    public static function isLazy(): bool
    {
        return RoleDashboardConfig::isWidgetLazy(static::class, true);
    }

    protected function getPollingInterval(): ?string
    {
        return RoleDashboardConfig::pollingIntervalForWidget(static::class, '180s');
    }
}
