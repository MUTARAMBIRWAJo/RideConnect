<?php

namespace App\Services;

use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\Facades\Cache;

class AiDashboardService
{
    public function __construct(private readonly HttpFactory $http)
    {
    }

    /**
     * @return array<int, array{zone: string, level: string, score: float}>
     */
    public function getDemandZones(): array
    {
        return $this->remember('dashboard.ai.demand_zones', 90, function (): array {
            $payload = [
                'city' => 'Kigali',
                'hour' => now()->hour,
                'day_of_week' => now()->dayOfWeek,
            ];

            $response = $this->client()->post('/predict/demand', $payload);

            if (! $response->successful()) {
                return $this->fallbackDemandZones();
            }

            $zones = $response->json('zones');

            if (! is_array($zones) || $zones === []) {
                return $this->fallbackDemandZones();
            }

            return collect($zones)
                ->filter(fn (mixed $zone): bool => is_array($zone))
                ->map(function (array $zone): array {
                    return [
                        'zone' => (string) ($zone['zone'] ?? $zone['name'] ?? 'Unknown'),
                        'level' => strtoupper((string) ($zone['level'] ?? 'MEDIUM')),
                        'score' => (float) ($zone['score'] ?? 0),
                    ];
                })
                ->take(8)
                ->values()
                ->all();
        }, $this->fallbackDemandZones());
    }

    /**
     * @return array<int, array{zone: string, multiplier: float}>
     */
    public function getSurgePredictions(): array
    {
        return $this->remember('dashboard.ai.surge_predictions', 90, function (): array {
            $payload = [
                'city' => 'Kigali',
                'hour' => now()->hour,
                'day_of_week' => now()->dayOfWeek,
            ];

            $response = $this->client()->post('/predict/surge', $payload);

            if (! $response->successful()) {
                return $this->fallbackSurgePredictions();
            }

            $zones = $response->json('zones');

            if (! is_array($zones) || $zones === []) {
                return $this->fallbackSurgePredictions();
            }

            return collect($zones)
                ->filter(fn (mixed $zone): bool => is_array($zone))
                ->map(function (array $zone): array {
                    return [
                        'zone' => (string) ($zone['zone'] ?? $zone['name'] ?? 'Unknown'),
                        'multiplier' => (float) ($zone['multiplier'] ?? $zone['surge_multiplier'] ?? 1.0),
                    ];
                })
                ->take(8)
                ->values()
                ->all();
        }, $this->fallbackSurgePredictions());
    }

    /**
     * @return array{minutes: int, confidence: float}
     */
    public function getEtaPredictions(): array
    {
        return $this->remember('dashboard.ai.eta_prediction', 90, function (): array {
            $payload = [
                'origin_lat' => -1.9441,
                'origin_lng' => 30.0619,
                'destination_lat' => -1.9706,
                'destination_lng' => 30.1044,
                'hour' => now()->hour,
                'day_of_week' => now()->dayOfWeek,
            ];

            $response = $this->client()->post('/predict/eta', $payload);

            if (! $response->successful()) {
                return ['minutes' => 14, 'confidence' => 0.72];
            }

            return [
                'minutes' => (int) ($response->json('eta_minutes') ?? $response->json('minutes') ?? 14),
                'confidence' => (float) ($response->json('confidence') ?? 0.72),
            ];
        }, ['minutes' => 14, 'confidence' => 0.72]);
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

    /**
     * @template T
     * @param T $fallback
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