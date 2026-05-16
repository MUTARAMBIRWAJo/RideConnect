<?php

namespace App\Filament\Widgets;

use Filament\Widgets\Widget;

class RideMapWidget extends Widget
{
    protected static string $view = 'filament.widgets.ride-map-widget';

    protected int|string|array $columnSpan = 'full';

    protected static ?int $sort = 1;

    protected static bool $isLazy = false;
}
