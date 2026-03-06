<?php

namespace App\Filament\Widgets;

use Filament\Widgets\Widget;

class DemandHeatmapWidget extends Widget
{
    protected static string $view = 'filament.widgets.demand-heatmap-widget';

    protected int | string | array $columnSpan = [
        'md' => 1,
        'xl' => 2,
    ];

    protected function getViewData(): array
    {
        // coordinates + labels are static placeholders — replace with real data if available
        $markers = [
            ['label' => 'Nyabugogo', 'lat' => -1.953, 'lng' => 30.060],
            ['label' => 'Remera', 'lat' => -1.944, 'lng' => 30.091],
            ['label' => 'Kacyiru', 'lat' => -1.948, 'lng' => 30.074],
            ['label' => 'Kimironko', 'lat' => -1.951, 'lng' => 30.102],
        ];

        return compact('markers');
    }
}
