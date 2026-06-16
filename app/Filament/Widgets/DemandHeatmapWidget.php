<?php

namespace App\Filament\Widgets;

use Filament\Widgets\Widget;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Carbon;

class DemandHeatmapWidget extends Widget
{
    protected static string $view = 'filament.widgets.demand-heatmap';

    // Refresh every 5 minutes (changed from 14 minutes)
    // This means the heatmap polls for fresh predictions every 5 min
    protected static ?string $pollingInterval = '5m';

    /**
     * Build real feature vector from current system time and return
     * predictions for ALL zones from the ML microservice.
     * ALWAYS passes Carbon::now() as the timestamp so the model
     * receives the actual current hour — never a stale or hardcoded value.
     */
    public function getDemandData(): array
    {
        $now = Carbon::now();

        $features = [
            'hour_of_day' => $now->hour,
            'day_of_week' => $now->dayOfWeek,   // 0=Sunday, 6=Saturday
            'is_weekend'  => $now->isWeekend() ? 1 : 0,
            'is_holiday'  => 0,   // TODO: wire up Rwandan public holiday calendar
            'is_peak'     => (($now->hour >= 7  && $now->hour <= 9) ||
                              ($now->hour >= 17 && $now->hour <= 19)) ? 1 : 0,
            'temperature' => 21.0,   // TODO: wire up weather API
            'is_raining'  => 0,
        ];

        try {
            $response = Http::timeout(10)
                ->post(config('services.ml.url') . '/predict-demand/all', [
                    'timestamp' => $now->toISOString(),   // ALWAYS current time
                    'features'  => $features,
                ]);

            if ($response->successful()) {
                return $response->json('data.zones', []);
            }

            Log::warning('DemandHeatmap: ML service returned error', [
                'status' => $response->status(),
                'body'   => $response->body(),
            ]);
        } catch (\Exception $e) {
            Log::warning('DemandHeatmap: ML service unreachable', [
                'error' => $e->getMessage(),
            ]);
        }

        // Return empty array on failure — show no data rather than stale data
        return [];
    }
}
