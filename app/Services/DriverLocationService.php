<?php

namespace App\Services;

use App\Events\DriverLocationUpdated;
use App\Models\Driver;
use App\Models\DriverLocation;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * DriverLocationService - Manages real-time driver location tracking
 *
 * Features:
 * - Location update throttling (max 1 per 3-5 seconds per driver)
 * - Active trip validation
 * - Real-time event broadcasting
 * - Location history storage
 */
class DriverLocationService
{
    private const THROTTLE_SECONDS = 5; // Max 1 update per 5 seconds
    private const THROTTLE_KEY_PREFIX = 'driver_location_throttle_';

    /**
     * Update driver location
     *
     * @param Driver $driver
     * @param float $lat Latitude
     * @param float $lng Longitude
     * @param ?float $speed Speed in km/h
     * @param ?int $heading Heading in degrees (0-360)
     * @param ?float $accuracy Accuracy in meters
     * @param ?int $tripId Active trip ID
     * @return array{success: bool, message: string, throttled?: bool}
     */
    public function updateLocation(
        Driver $driver,
        float $lat,
        float $lng,
        ?float $speed = null,
        ?int $heading = null,
        ?float $accuracy = null,
        ?int $tripId = null
    ): array {
        try {
            // Validate coordinates
            if (!$this->isValidCoordinates($lat, $lng)) {
                Log::warning('DriverLocationService: Invalid coordinates', [
                    'driver_id' => $driver->id,
                    'lat' => $lat,
                    'lng' => $lng,
                ]);
                return [
                    'success' => false,
                    'message' => 'Invalid coordinates',
                ];
            }

            // Check throttling
            if ($this->isThrottled($driver->id)) {
                Log::debug('DriverLocationService: Location update throttled', [
                    'driver_id' => $driver->id,
                ]);
                return [
                    'success' => false,
                    'message' => 'Too many location updates. Please wait.',
                    'throttled' => true,
                ];
            }

            // Validate active trip if provided
            if ($tripId) {
                $hasActiveTrip = $driver->motorcycleTrips()
                    ->where('id', $tripId)
                    ->whereIn('status', ['ASSIGNED', 'DRIVER_ASSIGNED', 'PASSENGER_WAITING', 'IN_PROGRESS'])
                    ->exists();

                if (!$hasActiveTrip) {
                    Log::warning('DriverLocationService: Driver not on specified trip', [
                        'driver_id' => $driver->id,
                        'trip_id' => $tripId,
                    ]);
                    return [
                        'success' => false,
                        'message' => 'Driver not on active trip',
                    ];
                }
            } else {
                // Get current active trip
                $activeTrip = $driver->motorcycleTrips()
                    ->whereIn('status', ['ASSIGNED', 'DRIVER_ASSIGNED', 'PASSENGER_WAITING', 'IN_PROGRESS'])
                    ->latest('updated_at')
                    ->first();
                
                $tripId = $activeTrip?->id;
            }

            // Update driver's current location
            $driver->update([
                'last_location_lat' => $lat,
                'last_location_lng' => $lng,
            ]);

            // Cache current location in Redis for ultra-fast lookup
            Cache::put("driver_location_{$driver->id}", [
                'driver_id' => $driver->id,
                'lat' => $lat,
                'lng' => $lng,
                'speed' => $speed,
                'heading' => $heading,
                'accuracy' => $accuracy,
                'recorded_at' => now()->toIso8601String(),
            ], 300);

            // Store location history (dual-write lat/lng + latitude/longitude for compatibility)
            DriverLocation::create([
                'driver_id' => $driver->id,
                'trip_id' => $tripId,
                'lat' => $lat,
                'lng' => $lng,
                'latitude' => $lat,
                'longitude' => $lng,
                'speed' => $speed,
                'speed_kmh' => $speed,
                'heading' => $heading,
                'accuracy' => $accuracy,
                'recorded_at' => now(),
            ]);

            // Broadcast location update if on active trip
            if ($tripId) {
                event(new DriverLocationUpdated(
                    $driver->id,
                    $tripId,
                    $lat,
                    $lng,
                    $speed,
                    $heading,
                    $accuracy
                ));

                Log::info('DriverLocationService: Location updated and broadcast', [
                    'driver_id' => $driver->id,
                    'trip_id' => $tripId,
                    'lat' => $lat,
                    'lng' => $lng,
                    'speed' => $speed,
                ]);
            } else {
                Log::info('DriverLocationService: Location updated (no active trip)', [
                    'driver_id' => $driver->id,
                    'lat' => $lat,
                    'lng' => $lng,
                ]);
            }

            // Set throttle
            $this->setThrottle($driver->id);

            return [
                'success' => true,
                'message' => 'Location updated successfully',
            ];
        } catch (\Exception $e) {
            Log::error('DriverLocationService: Exception during location update', [
                'driver_id' => $driver->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'message' => 'Failed to update location',
            ];
        }
    }

    /**
     * Get driver's current location
     */
    public function getCurrentLocation(Driver $driver): ?DriverLocation
    {
        $cached = Cache::get("driver_location_{$driver->id}");
        if ($cached) {
            $location = new DriverLocation();
            $location->forceFill([
                'driver_id' => (int) $cached['driver_id'],
                'lat' => (float) $cached['lat'],
                'lng' => (float) $cached['lng'],
                'latitude' => (float) $cached['lat'],
                'longitude' => (float) $cached['lng'],
                'speed' => $cached['speed'] ? (float) $cached['speed'] : null,
                'heading' => $cached['heading'] ? (int) $cached['heading'] : null,
                'accuracy' => $cached['accuracy'] ? (float) $cached['accuracy'] : null,
                'recorded_at' => \Carbon\Carbon::parse($cached['recorded_at']),
            ]);
            $location->exists = true;
            return $location;
        }

        return DriverLocation::where('driver_id', $driver->id)
            ->latest('recorded_at')
            ->first();
    }

    /**
     * Get location history for a trip
     */
    public function getTripLocationHistory(int $tripId): \Illuminate\Database\Eloquent\Collection
    {
        return DriverLocation::where('trip_id', $tripId)
            ->orderBy('recorded_at')
            ->get();
    }

    /**
     * Validate coordinates
     */
    private function isValidCoordinates(float $lat, float $lng): bool
    {
        return $lat >= -90 && $lat <= 90 && $lng >= -180 && $lng <= 180;
    }

    /**
     * Check if driver location update is throttled
     */
    private function isThrottled(int $driverId): bool
    {
        $key = self::THROTTLE_KEY_PREFIX . $driverId;
        return Cache::has($key);
    }

    /**
     * Set throttle for driver
     */
    private function setThrottle(int $driverId): void
    {
        $key = self::THROTTLE_KEY_PREFIX . $driverId;
        Cache::put($key, true, self::THROTTLE_SECONDS);
    }
}
