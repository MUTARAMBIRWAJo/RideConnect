<?php

namespace App\Services\Location;

use App\Models\Driver;
use App\Models\DriverLocation;
use App\Services\Ml\MlAnomalyDetectionService;
use App\Services\Realtime\RealtimeGateway;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class DriverLocationService
{
    private const ONLINE_TIMEOUT_MINUTES = 5;
    private const LOCATION_CACHE_TTL_MINUTES = 10;

    public function __construct(
        private readonly RealtimeGateway $realtimeGateway,
        private readonly MlAnomalyDetectionService $anomalyDetectionService,
    ) {
    }

    /**
     * Update driver location with real-time data
     */
    public function updateLocation(
        int $driverId,
        float $latitude,
        float $longitude,
        ?float $speedKmh = null,
        ?float $heading = null,
        ?float $accuracy = null,
        bool $isOnline = true,
        ?float $routeDeviationMeters = null,
        ?int $tripId = null,
    ): DriverLocation {
        $previousLocation = DriverLocation::where('driver_id', $driverId)->first();

        $location = DriverLocation::updateOrCreate(
            ['driver_id' => $driverId],
            [
                'latitude' => $latitude,
                'longitude' => $longitude,
                'speed_kmh' => $speedKmh,
                'heading' => $heading,
                'accuracy' => $accuracy,
                'updated_at' => now(),
                'last_activity_at' => now(),
                'is_online' => $isOnline,
            ]
        );

        // Cache the location for faster access
        $this->cacheLocation($driverId, $location);

        // Broadcast location update to passengers tracking this driver
        $this->broadcastLocationUpdate($driverId, $location);

        $this->anomalyDetectionService->inspectLocationUpdate(
            driverId: $driverId,
            location: $location,
            previousLocation: $previousLocation,
            routeDeviationMeters: $routeDeviationMeters,
            tripId: $tripId,
        );

        return $location;
    }

    /**
     * Update driver online status
     */
    public function updateOnlineStatus(int $driverId, bool $isOnline): void
    {
        DriverLocation::updateOrCreate(
            ['driver_id' => $driverId],
            [
                'is_online' => $isOnline,
                'last_activity_at' => now(),
            ]
        );

        // Broadcast online status change
        $this->broadcastOnlineStatus($driverId, $isOnline);
    }

    /**
     * Get current driver location
     */
    public function getCurrentLocation(int $driverId): ?DriverLocation
    {
        // Try cache first for performance
        $cached = Cache::get($this->getCacheKey($driverId));
        if ($cached) {
            return $cached;
        }

        // Fallback to database
        return DriverLocation::where('driver_id', $driverId)->first();
    }

    /**
     * Get nearby drivers within radius (in kilometers)
     */
    public function getNearbyDrivers(float $latitude, float $longitude, float $radiusKm = 5.0): array
    {
        $drivers = DriverLocation::selectRaw("
                driver_locations.*,
                (6371 * acos(cos(radians(?)) * cos(radians(latitude)) * cos(radians(longitude) - radians(?)) + sin(radians(?)) * sin(radians(latitude)))) AS distance_km
            ", [$latitude, $longitude, $latitude])
            ->where('is_online', true)
            ->having('distance_km', '<=', $radiusKm)
            ->orderBy('distance_km')
            ->get();

        return $drivers->toArray();
    }

    /**
     * Mark drivers as offline if they haven't updated location recently
     */
    public function markStaleDriversOffline(): int
    {
        $staleThreshold = now()->subMinutes(self::ONLINE_TIMEOUT_MINUTES);

        $updated = DriverLocation::where('is_online', true)
            ->where('last_activity_at', '<', $staleThreshold)
            ->update([
                'is_online' => false,
                'updated_at' => now(),
            ]);

        if ($updated > 0) {
            Log::info("Marked {$updated} drivers as offline due to inactivity");
        }

        return $updated;
    }

    /**
     * Get online drivers count
     */
    public function getOnlineDriversCount(): int
    {
        return DriverLocation::where('is_online', true)->count();
    }

    /**
     * Cache location for faster access
     */
    private function cacheLocation(int $driverId, DriverLocation $location): void
    {
        Cache::put(
            $this->getCacheKey($driverId),
            $location,
            now()->addMinutes(self::LOCATION_CACHE_TTL_MINUTES)
        );
    }

    /**
     * Broadcast location update to passengers
     */
    private function broadcastLocationUpdate(int $driverId, DriverLocation $location): void
    {
        try {
            $this->realtimeGateway->broadcast(
                "driver:{$driverId}",
                'driver.location.updated',
                [
                    'driver_id' => $driverId,
                    'latitude' => (float) $location->latitude,
                    'longitude' => (float) $location->longitude,
                    'speed_kmh' => (float) $location->speed_kmh,
                    'heading' => (float) $location->heading,
                    'accuracy' => (float) $location->accuracy,
                    'is_online' => (bool) $location->is_online,
                    'updated_at' => $location->updated_at?->toIso8601String(),
                ]
            );
        } catch (\Throwable $e) {
            Log::error('Failed to broadcast driver location update', [
                'driver_id' => $driverId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Broadcast online status change
     */
    private function broadcastOnlineStatus(int $driverId, bool $isOnline): void
    {
        try {
            $this->realtimeGateway->broadcast(
                "driver:{$driverId}",
                'driver.status.changed',
                [
                    'driver_id' => $driverId,
                    'is_online' => $isOnline,
                    'changed_at' => now()->toIso8601String(),
                ]
            );
        } catch (\Throwable $e) {
            Log::error('Failed to broadcast driver online status', [
                'driver_id' => $driverId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function getCacheKey(int $driverId): string
    {
        return "driver_location:{$driverId}";
    }
}
