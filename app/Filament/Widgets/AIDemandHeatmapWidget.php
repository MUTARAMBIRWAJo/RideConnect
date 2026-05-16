<?php

namespace App\Filament\Widgets;

use Filament\Widgets\Widget;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AIDemandHeatmapWidget extends Widget
{
    protected static string $view = 'filament.widgets.ai-demand-heatmap-widget';

    protected int|string|array $columnSpan = 1;

    protected function getViewData(): array
    {
        if (! Schema::hasTable('demand_logs')) {
            return ['zones' => collect()];
        }

        $zones = DB::table('demand_logs')
            ->selectRaw('COALESCE(zone_key, CONCAT(ROUND(pickup_lat::numeric, 2),\':\', ROUND(pickup_lng::numeric, 2))) as zone_bucket')
            ->selectRaw('COUNT(*) as requests')
            ->where('request_time', '>=', now()->subHours(6))
            ->groupByRaw('COALESCE(zone_key, CONCAT(ROUND(pickup_lat::numeric, 2),\':\', ROUND(pickup_lng::numeric, 2)))')
            ->orderByDesc('requests')
            ->limit(8)
            ->get();

        $zones = $zones->map(function ($row) {
            $row->zone_key = $row->zone_bucket;
            unset($row->zone_bucket);

            return $row;
        });

        return ['zones' => $zones];
    }
}
