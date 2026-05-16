<?php

namespace App\Services\Ml;

use App\Models\Driver;
use App\Models\Ride;
use App\Models\Trip;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Throwable;

class DriverRankerService
{
    public function __construct(private readonly HttpFactory $http)
    {
    }

    /**
     * @param Collection<int, Driver> $drivers
     * @return array{driver: Driver|null, score: float|null, version: string|null, source: string}
     */
    public function chooseBestDriver(Trip $trip, Ride $ride, Collection $drivers): array
    {
        if ($drivers->isEmpty()) {
            return [
                'driver' => null,
                'score' => null,
                'version' => null,
                'source' => 'none',
            ];
        }

        if (! (bool) config('services.ml_service.ranking_enabled', true)) {
            return $this->fallbackRank($drivers, 'feature_flag_disabled');
        }

        try {
            $payload = $this->buildPayload($trip, $drivers);
            $response = $this->client()->post('/ml/rank-drivers', $payload);

            if (! $response->successful()) {
                Log::warning('ML driver ranking unavailable; using fallback ranking', [
                    'trip_id' => $trip->id,
                    'status' => $response->status(),
                    'response' => $response->json(),
                ]);

                return $this->fallbackRank($drivers, 'ml_unavailable');
            }

            $data = $response->json('data', []);
            $bestDriverId = $data['best_driver']['driver_id'] ?? null;
            $score = $data['best_driver']['score'] ?? null;
            $version = $data['model_version'] ?? null;

            $driver = $drivers->first(fn (Driver $driver): bool => (string) $driver->id === (string) $bestDriverId);
            if (! $driver) {
                Log::warning('ML driver ranking returned unknown driver; using fallback ranking', [
                    'trip_id' => $trip->id,
                    'best_driver_id' => $bestDriverId,
                ]);

                return $this->fallbackRank($drivers, 'ml_unknown_driver');
            }

            Log::info('ML driver ranking selected driver', [
                'trip_id' => $trip->id,
                'ride_id' => $ride->id,
                'driver_id' => $driver->id,
                'ranker_score' => $score,
                'ranker_version' => $version,
            ]);

            return [
                'driver' => $driver,
                'score' => is_numeric($score) ? (float) $score : null,
                'version' => is_string($version) ? $version : null,
                'source' => 'ml',
            ];
        } catch (Throwable $exception) {
            Log::warning('ML driver ranking failed; using fallback ranking', [
                'trip_id' => $trip->id,
                'error' => $exception->getMessage(),
            ]);

            return $this->fallbackRank($drivers, 'ml_exception');
        }
    }

    /**
     * @param Collection<int, Driver> $drivers
     * @return array{driver: Driver|null, score: float|null, version: string|null, source: string}
     */
    public function fallbackRank(Collection $drivers, string $source = 'fallback'): array
    {
        $driver = $drivers
            ->sortBy([
                fn (Driver $driver): float => (float) ($driver->distance_to_pickup_km ?? PHP_FLOAT_MAX),
                fn (Driver $driver): float => -1 * (float) ($driver->rating ?? 0),
            ])
            ->first();

        return [
            'driver' => $driver,
            'score' => null,
            'version' => 'fallback:v1',
            'source' => $source,
        ];
    }

    /**
     * @param Collection<int, Driver> $drivers
     */
    private function buildPayload(Trip $trip, Collection $drivers): array
    {
        $requestedAt = $trip->requested_at ?: $trip->created_at ?: now();

        return [
            'booking_context' => [
                'pickup_lat' => (float) $trip->pickup_lat,
                'pickup_lng' => (float) $trip->pickup_lng,
                'hour_of_day' => (int) $requestedAt->format('G'),
                'day_of_week' => (int) $requestedAt->dayOfWeekIso - 1,
            ],
            'candidates' => $drivers->take(20)->map(function (Driver $driver): array {
                return [
                    'driver_id' => (string) $driver->id,
                    'distance_to_pickup_km' => round((float) ($driver->distance_to_pickup_km ?? 999.0), 4),
                    'driver_rating' => (float) ($driver->rating ?? 0),
                    'acceptance_rate' => $this->acceptanceRate($driver),
                    'vehicle_type' => $this->rankerVehicleType((string) ($driver->ranker_vehicle_type ?? 'sedan')),
                ];
            })->values()->all(),
        ];
    }

    private function rankerVehicleType(string $vehicleType): string
    {
        return match (strtolower(trim($vehicleType))) {
            'suv' => 'suv',
            'bus', 'coach', 'minibus', 'minivan', 'van' => 'minibus',
            'boda', 'motorbike', 'motorcycle', 'moto', 'tricycle', 'tuk-tuk' => 'moto',
            default => 'sedan',
        };
    }

    private function acceptanceRate(Driver $driver): float
    {
        $total = (int) $driver->trips_total_count;
        if ($total <= 0) {
            return 0.75;
        }

        $accepted = (int) $driver->trips_accepted_count;

        return round(max(0.0, min(1.0, $accepted / $total)), 4);
    }

    private function client()
    {
        $baseUrl = rtrim((string) config('services.ml_service.url'), '/');
        $apiKey = (string) config('services.ml_service.api_key', '');

        $client = $this->http
            ->baseUrl($baseUrl)
            ->acceptJson()
            ->asJson()
            ->timeout((float) config('services.ml_service.timeout', 10))
            ->retry(2, 200, throw: false);

        if ($apiKey !== '') {
            $client = $client->withHeader('X-API-Key', $apiKey);
        }

        return $client;
    }
}
