<?php

namespace App\Filament\Widgets;

use Filament\Widgets\Widget;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AIDriverDistributionWidget extends Widget
{
    protected static string $view = 'filament.widgets.ai-driver-distribution-widget';

    protected int|string|array $columnSpan = 1;

    protected function getViewData(): array
    {
        if (! Schema::hasTable('driver_locations')) {
            return ['distribution' => collect()];
        }

        $distribution = DB::table('driver_locations')
            ->selectRaw('CONCAT(ROUND(latitude::numeric, 2),\':\', ROUND(longitude::numeric, 2)) as zone_key')
            ->selectRaw('COUNT(*) as drivers')
            ->groupByRaw('CONCAT(ROUND(latitude::numeric, 2),\':\', ROUND(longitude::numeric, 2))')
            ->orderByDesc('drivers')
            ->limit(8)
            ->get();

        return ['distribution' => $distribution];
    }
}
