<?php

namespace App\Services\Location;

use App\Models\Trip;
use App\Services\Realtime\RealtimeGateway;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

class TripLocationService
{
    public function __construct(private readonly RealtimeGateway $realtimeGateway) {}

    private function cacheKey(int $tripId): string
    {
        return 'trip_location:'.$tripId;
    }

    /**
     * Update trip live location snapshot without schema changes.
     */
    public function updateLocation(Trip $trip, float $lat, float $lng, ?array $routeSnapshot = null): array
    {
        $payload = [
            'current_lat' => $lat,
            'current_lng' => $lng,
            'last_updated_at' => now()->toIso8601String(),
            'route_snapshot' => $routeSnapshot,
        ];

        Cache::put($this->cacheKey((int) $trip->id), $payload, now()->addHours(12));

        try {
            Storage::disk('local')->makeDirectory('trip-location-stream');
            Storage::disk('local')->append(
                'trip-location-stream/'.$trip->id.'.jsonl',
                json_encode([
                    'trip_id' => $trip->id,
                    'current_lat' => $lat,
                    'current_lng' => $lng,
                    'last_updated_at' => $payload['last_updated_at'],
                    'route_snapshot' => $routeSnapshot,
                ], JSON_UNESCAPED_SLASHES)
            );
        } catch (\Throwable $e) {
            // If storage is unavailable, continue using cache only.
        }

        return $payload;
    }

    public function updateAndBroadcast(int $tripId, float $lat, float $lng, ?array $routeSnapshot = null): array
    {
        $trip = Trip::findOrFail($tripId);

        $payload = $this->updateLocation($trip, $lat, $lng, $routeSnapshot);

        $this->realtimeGateway->broadcast(
            "trip:{$tripId}",
            'driver.location.updated',
            [
                'lat' => $lat,
                'lng' => $lng,
                'timestamp' => now()->toIso8601String(),
            ]
        );

        return $payload;
    }

    public function getCurrentLocation(Trip $trip): array
    {
        return Cache::get($this->cacheKey((int) $trip->id), [
            'current_lat' => null,
            'current_lng' => null,
            'last_updated_at' => null,
            'route_snapshot' => null,
        ]);
    }
}
