<?php

namespace App\Services;

use App\Models\Trip;
use Illuminate\Support\Collection;

class DemandPredictionService
{
    /**
     * Fetch demand predictions from the ML service and save them to the DB.
     */
    public function predict(): Collection
    {
        $now = now();
        $features = [
            'hour_of_day' => $now->hour,
            'day_of_week' => $now->dayOfWeek,
            'is_weekend'  => $now->isWeekend() ? 1 : 0,
            'is_holiday'  => 0,
            'is_peak'     => (($now->hour >= 7 && $now->hour <= 9) || ($now->hour >= 17 && $now->hour <= 19)) ? 1 : 0,
            'temperature' => 21.0,
            'is_raining'  => 0,
        ];

        try {
            $response = \Illuminate\Support\Facades\Http::timeout(30)
                ->post(config('services.ml.url', 'http://ml-service:8001') . '/predict-demand/all', [
                    'timestamp' => $now->toISOString(),
                    'features'  => $features,
                ]);

            if ($response->successful()) {
                $zones = $response->json('data.zones', []);
                $points = collect();

                foreach ($zones as $zone) {
                    // Save to database
                    $prediction = \App\Models\DemandPrediction::updateOrCreate(
                        [
                            'zone_id' => $zone['zone_id'],
                            'predicted_at' => $now->startOfMinute()->toDateTimeString(),
                        ],
                        [
                            'zone_name' => $zone['zone_name'] ?? null,
                            'lat' => $zone['lat'],
                            'lng' => $zone['lng'],
                            'intensity' => min(1.0, max(0.0, ($zone['demand_score'] ?? 0) / 100)),
                        ]
                    );

                    $points->push([
                        'lat' => $prediction->lat,
                        'lng' => $prediction->lng,
                        'intensity' => $prediction->intensity,
                    ]);
                }

                return $points;
            }
            
            \Illuminate\Support\Facades\Log::warning('DemandPredictionService: ML service returned error', [
                'status' => $response->status(),
                'body'   => $response->body(),
            ]);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::warning('DemandPredictionService: ML service unreachable', [
                'error' => $e->getMessage(),
            ]);
        }

        // Fallback: Return the latest predictions from DB if available
        $latestPredictionTime = \App\Models\DemandPrediction::max('predicted_at');
        if ($latestPredictionTime) {
            $latest = \App\Models\DemandPrediction::where('predicted_at', $latestPredictionTime)->get();
            return $latest->map(function ($prediction) {
                return [
                    'lat' => $prediction->lat,
                    'lng' => $prediction->lng,
                    'intensity' => $prediction->intensity,
                ];
            });
        }

        return collect([]);
    }
}
