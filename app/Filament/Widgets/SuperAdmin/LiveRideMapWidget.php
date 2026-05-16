<?php

namespace App\Filament\Widgets\SuperAdmin;

use Filament\Widgets\Widget;

class LiveRideMapWidget extends Widget
{
    protected static string $view = 'filament.widgets.super-admin.live-ride-map-widget';

    protected int|string|array $columnSpan = 'full';

    protected static bool $isLazy = false;

    protected static ?int $sort = 1;

    protected function getViewData(): array
    {
        return [
            'endpoint' => route('api.map.live-data'),
            'defaultLat' => -1.9441,
            'defaultLng' => 30.0619,
        ];
    }
}
