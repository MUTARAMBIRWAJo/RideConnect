<?php

namespace App\Services\Location;

use App\Jobs\DriverLocationSyncJob;
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

    private const STALE_LOCATION_THRESHOLD_MINUTES = 15;

    public function __construct(
        private readonly RealtimeGateway $realtimeGateway,
        private readonly MlAnomalyDetectionService $anomalyDetectionService,
        private readonly DriverLocationValidator $validator,
    ) {}

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
        // Validate coordinates
        $validation = $this->validator->validate($latitude, $longitude, $accuracy);
        if (!$validation['valid']) {
            Log::warning('Invalid driver location coordinates', [
                'driver_id' => $driverId,
                'errors' => $validation['errors'],
            ]);
            throw new \InvalidArgumentException('Invalid location coordinates: ' . implode(', ', $validation['errors']));
        }

        if (!empty($validation['warnings'])) {
            Log::info('Driver location validation warnings', [
                'driver_id' => $driverId,
                'warnings' => $validation['warnings'],
            ]);
        }

        $previousLocation = DriverLocation::where('driver_id', $driverId)->first();

        // Validate location sequence if previous location exists
        if ($previousLocation) {
            $sequenceValidation = $this->validator->validateSequence(
                $previousLocation->latitude,
                $previousLocation->longitude,
                $previousLocation->updated_at,
                $latitude,
                $longitude,
                $speedKmh
            );

            if (!$sequenceValidation['valid']) {
                Log::warning('Invalid location sequence detected', [
                    'driver_id' => $driverId,
                    'errors' => $sequenceValidation['errors'],
                ]);
            }

            if (!empty($sequenceValidation['warnings'])) {
                Log::info('Location sequence warnings', [
                    'driver_id' => $driverId,
                    'warnings' => $sequenceValidation['warnings'],
                    'distance_km' => $sequenceValidation['distance_km'] ?? null,
                    'implied_speed_kmh' => $sequenceValidation['implied_speed_kmh'] ?? null,
                ]);
            }
        }

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
                'trip_id' => $tripId,
            ]
        );

        // Cache the location for faster access
        $this->cacheLocation($driverId, $location);

        // Broadcast location update to passengers tracking this driver
        $this->broadcastLocationUpdate($driverId, $location);

        // Sync to Firebase asynchronously
        dispatch(new DriverLocationSyncJob(
            driverId: $driverId,
            latitude: $latitude,
            longitude: $longitude,
            accuracy: $accuracy,
            tripId: $tripId,
        ));

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
            // Check if cached location is stale
            if ($cached->updated_at && $cached->updated_at->lt(now()->subMinutes(self::STALE_LOCATION_THRESHOLD_MINUTES))) {
                Cache::forget($this->getCacheKey($driverId));
            } else {
                return $cached;
            }
        }

        // Fallback to database
        $location = DriverLocation::where('driver_id', $driverId)->first();
        
        if ($location) {
            $this->cacheLocation($driverId, $location);
        }
        
        return $location;
    }

    /**
     * Get nearby drivers within radius (in kilometers)
     */
    public function getNearbyDrivers(float $latitude, float $longitude, float $radiusKm = 5.0): array
    {
        $staleThreshold = now()->subMinutes(self::STALE_LOCATION_THRESHOLD_MINUTES);
        
        $drivers = DriverLocation::selectRaw('
                driver_locations.*,
                (6371 * acos(cos(radians(?)) * cos(radians(latitude)) * cos(radians(longitude) - radians(?)) + sin(radians(?)) * sin(radians(latitude)))) AS distance_km
            ', [$latitude, $longitude, $latitude])
            ->where('is_online', true)
            ->where('last_activity_at', '>', $staleThreshold)
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
            // Clear cache for offline drivers
            DriverLocation::where('is_online', false)
                ->where('last_activity_at', '<', $staleThreshold)
                ->pluck('driver_id')
                ->each(fn ($id) => Cache::forget($this->getCacheKey($id)));
        }

        return $updated;
    }

    /**
     * Get online drivers count
     */
    public function getOnlineDriversCount(): int
    {
        $staleThreshold = now()->subMinutes(self::STALE_LOCATION_THRESHOLD_MINUTES);
        return DriverLocation::where('is_online', true)
            ->where('last_activity_at', '>', $staleThreshold)
            ->count();
    }

    /**
     * Get driver last seen timestamp
     */
    public function getDriverLastSeen(int $driverId): ?\Carbon\Carbon
    {
        $location = DriverLocation::where('driver_id', $driverId)->first();
        return $location?->last_activity_at;
    }

    /**
     * Check if driver location is stale
     */
    public function isLocationStale(int $driverId, int $thresholdMinutes = null): bool
    {
        $threshold = $thresholdMinutes ?? self::STALE_LOCATION_THRESHOLD_MINUTES;
        $lastSeen = $this->getDriverLastSeen($driverId);
        
        if (!$lastSeen) {
            return true;
        }
        
        return $lastSeen->lt(now()->subMinutes($threshold));
    }

    /**
     * Get trip-specific driver location
     */
    public function getTripDriverLocation(int $tripId): ?DriverLocation
    {
        return DriverLocation::where('trip_id', $tripId)
            ->where('is_online', true)
            ->orderBy('last_activity_at', 'desc')
            ->first();
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
