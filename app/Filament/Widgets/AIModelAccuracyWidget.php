<?php

namespace App\Filament\Widgets;

use Filament\Widgets\Widget;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AIModelAccuracyWidget extends Widget
{
    protected static string $view = 'filament.widgets.ai-model-accuracy-widget';

    protected int|string|array $columnSpan = 'full';

    protected function getViewData(): array
    {
        if (! Schema::hasTable('ai_model_metrics')) {
            return ['metrics' => collect()];
        }

        $metrics = DB::table('ai_model_metrics')
            ->select('model_name', 'metric_name', 'metric_value', 'evaluated_at')
            ->orderByDesc('evaluated_at')
            ->limit(40)
            ->get()
            ->groupBy(fn ($row) => $row->model_name.':'.$row->metric_name)
            ->map(fn ($rows) => $rows->first())
            ->values();

        return ['metrics' => $metrics];
    }
}
