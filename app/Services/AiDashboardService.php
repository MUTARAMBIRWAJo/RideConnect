<?php

namespace App\Services;

use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\Facades\Cache;

class AiDashboardService
{
    public function __construct(private readonly HttpFactory $http) {}

    /**
     * @return array<int, array{zone: string, level: string, score: float}>
     */
    public function getDemandZones(): array
    {
        return $this->remember('dashboard.ai.demand_zones', 90, function (): array {
            $predictions = \App\Models\DemandPrediction::where('predicted_at', '>=', now()->subHours(2))
                ->orderByDesc('predicted_at')
                ->orderByDesc('intensity')
                ->take(8)
                ->get();

            if ($predictions->isEmpty()) {
                return $this->fallbackDemandZones();
            }

            return $predictions->map(function ($prediction): array {
                $score = $prediction->intensity;
                return [
                    'zone' => $prediction->zone_id,
                    'level' => $this->demandLevelLabel($score),
                    'score' => $score,
                ];
            })->all();
        }, $this->fallbackDemandZones());
    }

    /**
     * @return array<int, array{zone: string, multiplier: float}>
     */
    public function getSurgePredictions(): array
    {
        return $this->remember('dashboard.ai.surge_predictions', 90, function (): array {
            $demand = $this->getDemandZones();
            if (empty($demand)) {
                return [];
            }
            
            return collect($demand)->map(function ($zone) {
                // Heuristic: scale score dynamically to surge
                $multiplier = max(1.0, 1.0 + ($zone['score'] * 3));
                return [
                    'zone' => $zone['zone'],
                    'multiplier' => round($multiplier, 2),
                ];
            })->sortByDesc('multiplier')->take(5)->values()->all();
        }, []);
    }

    /**
     * @return array{minutes: int, confidence: float}
     */
    public function getEtaPredictions(): array
    {
        return $this->remember('dashboard.ai.eta_prediction', 90, function (): array {
            $metric = \Illuminate\Support\Facades\DB::table('ai_model_metrics')
                ->where('metric_name', 'accuracy')
                ->orderByDesc('evaluated_at')
                ->first();

            return [
                'minutes' => 60,
                'confidence' => $metric ? (float) $metric->metric_value : 0.95,
            ];
        }, ['minutes' => 60, 'confidence' => 0.95]);
    }

    private function client()
    {
        return $this->http
            ->baseUrl(rtrim((string) config('services.ai_service.url', 'https://rideconnect-ai.onrender.com'), '/'))
            ->acceptJson()
            ->asJson()
            ->timeout((int) config('services.ai_service.timeout', 8))
            ->retry(1, 250)
            ->when(
                filled(config('services.ai_service.key')),
                fn ($client) => $client->withHeader('X-API-Key', (string) config('services.ai_service.key'))
            );
    }

    private function mlClient()
    {
        return $this->http
            ->baseUrl(rtrim((string) config('services.ml_service.url', 'https://ml-service-j72g.onrender.com'), '/'))
            ->acceptJson()
            ->asJson()
            ->timeout((int) config('services.ml_service.timeout', 8))
            ->when(
                filled(config('services.ml_service.api_key')),
                fn ($client) => $client->withHeader('X-API-Key', (string) config('services.ml_service.api_key'))
            );
    }

    /**
     * @template T
     *
     * @param  T  $fallback
     * @return T
     */
    private function remember(string $key, int $seconds, callable $callback, mixed $fallback): mixed
    {
        try {
            return Cache::remember($key, $seconds, $callback);
        } catch (\Throwable $e) {
            report($e);

            return $fallback;
        }
    }

    /**
     * @return array<int, array{zone: string, level: string, score: float}>
     */
    private function fallbackDemandZones(): array
    {
        return [
            ['zone' => 'Kigali CBD', 'level' => 'HIGH', 'score' => 0.87],
            ['zone' => 'Remera', 'level' => 'MEDIUM', 'score' => 0.58],
            ['zone' => 'Kimironko', 'level' => 'MEDIUM', 'score' => 0.54],
        ];
    }

    /**
     * @return array<int, array{name: string, latitude: float, longitude: float}>
     */
    private function majorDemandZones(): array
    {
        return [
            ['name' => 'Kigali CBD', 'latitude' => -1.9441, 'longitude' => 30.0619],
            ['name' => 'Remera', 'latitude' => -1.9579, 'longitude' => 30.1127],
            ['name' => 'Kimironko', 'latitude' => -1.9367, 'longitude' => 30.1304],
        ];
    }

    private function demandLevelLabel(float $score): string
    {
        return match (true) {
            $score >= 0.15 => 'HIGH',
            $score >= 0.08 => 'MEDIUM',
            default => 'LOW',
        };
    }

    /**
     * @return array<int, array{zone: string, multiplier: float}>
     */
    private function fallbackSurgePredictions(): array
    {
        return [
            ['zone' => 'Kigali CBD', 'multiplier' => 1.4],
            ['zone' => 'Nyabugogo', 'multiplier' => 1.2],
            ['zone' => 'Kimironko', 'multiplier' => 1.15],
        ];
    }
}
