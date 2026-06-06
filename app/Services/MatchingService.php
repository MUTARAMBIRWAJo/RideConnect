<?php

namespace App\Services;

use App\Models\Driver;
use App\Models\MotorcycleTrip;
use App\Models\Vehicle;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MatchingService
{
    private string $mlServiceUrl;
    private int $timeout;
    private int $maxRetries;

    public function __construct()
    {
        $this->mlServiceUrl = config('services.ml_service.url') ?? 'https://ml-service-j72g.onrender.com';
        $this->timeout = config('services.ml_service.timeout', 30);
        $this->maxRetries = 2;
    }

    /**
     * Match a motorcycle trip to an available driver
     */
    public function matchMotorcycleTrip(MotorcycleTrip $trip, array $excludeDriverIds = []): ?array
    {
        try {
            Log::info('Matching motorcycle trip', [
                'trip_id' => $trip->id,
                'pickup_lat' => $trip->pickup_lat,
                'pickup_lng' => $trip->pickup_lng,
                'exclude_drivers' => $excludeDriverIds,
            ]);

            // Prepare payload
            $payload = [
                'trip_request_id' => $trip->id,
                'vehicle_type' => 'MOTORCYCLE',
                'pickup_lat' => (float) $trip->pickup_lat,
                'pickup_lng' => (float) $trip->pickup_lng,
                'exclude_drivers' => $excludeDriverIds,
            ];

            // Call matching service
            $response = Http::timeout($this->timeout)
                ->retry($this->maxRetries, 100)
                ->post("{$this->mlServiceUrl}/match", $payload);

            if (!$response->successful()) {
                Log::warning('Matching service returned error', [
                    'trip_id' => $trip->id,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                return null;
            }

            $data = $response->json();
            $driverId = $data['driver_id'] ?? null;

            if (!$driverId) {
                Log::warning('Matching service returned no driver', [
                    'trip_id' => $trip->id,
                    'response' => $data,
                ]);
                return null;
            }

            // Verify driver is eligible
            if (!$this->isDriverEligible($driverId)) {
                Log::warning('Driver from matching service is not eligible', [
                    'trip_id' => $trip->id,
                    'driver_id' => $driverId,
                ]);
                // Request next match excluding this driver
                return $this->matchMotorcycleTrip($trip, array_merge($excludeDriverIds, [$driverId]));
            }

            Log::info('Matching service returned eligible driver', [
                'trip_id' => $trip->id,
                'driver_id' => $driverId,
                'score' => $data['score'] ?? null,
            ]);

            return [
                'driver_id' => $driverId,
                'score' => $data['score'] ?? 0,
                'metadata' => $data,
            ];
        } catch (\Exception $e) {
            Log::error('Exception calling matching service', [
                'trip_id' => $trip->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return null;
        }
    }

    /**
     * Verify driver is eligible for matching
     *
     * Checks:
     * - Driver exists
     * - Driver is active
     * - Driver has motorcycle
     * - Driver is available
     * - Driver has no active trip
     */
    private function isDriverEligible(int $driverId): bool
    {
        // Get driver with relationships
        $driver = Driver::with('vehicles')->find($driverId);

        if (!$driver) {
            Log::warning('Driver not found', ['driver_id' => $driverId]);
            return false;
        }

        // Check driver is active
        if (!$driver->is_active) {
            Log::warning('Driver is not active', ['driver_id' => $driverId]);
            return false;
        }

        // Check driver is available
        if (!$driver->is_available) {
            Log::warning('Driver is not available', ['driver_id' => $driverId]);
            return false;
        }

        // Check driver has no active trip
        if ($driver->hasActiveMotoTrip()) {
            Log::warning('Driver has active motorcycle trip', ['driver_id' => $driverId]);
            return false;
        }

        // Check driver has motorcycle
        $hasMotorcycle = $driver->vehicles()
            ->where('vehicle_type', 'MOTORCYCLE')
            ->where('is_active', true)
            ->exists();

        if (!$hasMotorcycle) {
            Log::warning('Driver has no active motorcycle', ['driver_id' => $driverId]);
            return false;
        }

        return true;
    }

    /**
     * Request rematching for a trip
     * Used when driver rejects or is unavailable
     */
    public function rematchTrip(MotorcycleTrip $trip, array $excludeDriverIds = []): bool
    {
        $match = $this->matchMotorcycleTrip($trip, $excludeDriverIds);

        if (!$match) {
            Log::warning('No drivers available for rematching', [
                'trip_id' => $trip->id,
                'excluded_drivers' => $excludeDriverIds,
            ]);
            return false;
        }

        // Assign new driver
        $trip->update([
            'driver_id' => $match['driver_id'],
            'status' => 'ASSIGNED',
            'assigned_at' => now(),
        ]);

        // Update driver availability
        $driver = Driver::find($match['driver_id']);
        if ($driver) {
            $driver->update([
                'is_available' => false,
                'current_trip_id' => $trip->id,
            ]);
        }

        Log::info('Trip rematched to new driver', [
            'trip_id' => $trip->id,
            'driver_id' => $match['driver_id'],
        ]);

        return true;
    }
}
