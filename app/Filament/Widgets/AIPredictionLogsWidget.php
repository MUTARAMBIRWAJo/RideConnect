<?php

namespace App\Filament\Widgets;

use Filament\Widgets\Widget;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AIPredictionLogsWidget extends Widget
{
    protected static string $view = 'filament.widgets.ai-prediction-logs-widget';

    protected int | string | array $columnSpan = 'full';

    protected function getViewData(): array
    {
        if (!Schema::hasTable('ai_prediction_logs')) {
            return ['logs' => collect()];
        }

        $logs = DB::table('ai_prediction_logs')
            ->select('prediction_type', 'response_time_ms', 'success', 'requested_at')
            ->orderByDesc('requested_at')
            ->limit(20)
            ->get();

        return ['logs' => $logs];
    }
}
