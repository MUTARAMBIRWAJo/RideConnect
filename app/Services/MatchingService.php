<?php

namespace App\Services;

use App\Models\Driver;
use App\Models\DriverLocation;
use App\Models\MotorcycleTrip;
use App\Models\Ride;
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
        $this->timeout = 5; // Reduced to 5 seconds to ensure fast fallback matching
        $this->maxRetries = 1; // 1 retry only to prevent blocking the worker
    }

    /**
     * Match a motorcycle trip to an available driver
     *
     * Enhanced with weighted ML scoring:
     * - final_score = (distance_score × 0.4) + (eta_score × 0.3) + (availability_score × 0.2) + (rating_score × 0.1)
     *
     * @param MotorcycleTrip $trip
     * @param array $excludeDriverIds Drivers to exclude from matching
     * @param float $searchRadiusKm Search radius in kilometers (default: 5 km, max: 25 km)
     */
    /**
     * Fast, ML-free match: query nearby eligible drivers and pick the best by a
     * distance-dominant local score. No ML/route HTTP calls, so it returns in
     * tens of milliseconds and never hangs on a cold ML dyno. Use this on the
     * passenger's critical path; treat ML as optional background refinement.
     *
     * @return array{driver_id:int,score:float,reason:string,distance_km:float,candidate_count:int}|null
     */
    public function fastLocalMatch(MotorcycleTrip $trip, array $excludeDriverIds = [], float $searchRadiusKm = 5.0): ?array
    {
        $searchRadiusKm = max(1.0, min(\App\Services\Matching\RadiusExpansionService::MAX_RADIUS_KM, $searchRadiusKm));

        $eligible = $this->buildEligibleDriversList($trip, $excludeDriverIds, $searchRadiusKm);
        if (empty($eligible)) {
            return null;
        }

        usort($eligible, function ($a, $b) {
            $sa = ($a['preliminary_distance_score'] * 0.8) + ($a['preliminary_rating_score'] * 0.2);
            $sb = ($b['preliminary_distance_score'] * 0.8) + ($b['preliminary_rating_score'] * 0.2);
            return $sb <=> $sa;
        });

        $best = $eligible[0];

        return [
            'driver_id' => (int) $best['id'],
            'score' => round((($best['preliminary_distance_score'] * 0.8) + ($best['preliminary_rating_score'] * 0.2)) * 100, 1),
            'reason' => 'fast local match (nearest eligible driver)',
            'distance_km' => $best['distance_from_pickup_km'],
            'candidate_count' => count($eligible),
        ];
    }

    public function matchMotorcycleTrip(MotorcycleTrip $trip, array $excludeDriverIds = [], float $searchRadiusKm = 5): ?array
    {
        $eligibleDrivers = [];
        try {
            // Ensure radius is within bounds
            $searchRadiusKm = max(1, min(25, $searchRadiusKm));

            Log::info('Matching motorcycle trip with enhanced scoring', [
                'trip_id' => $trip->id,
                'pickup_lat' => $trip->pickup_lat,
                'pickup_lng' => $trip->pickup_lng,
                'exclude_drivers' => $excludeDriverIds,
                'search_radius_km' => $searchRadiusKm,
            ]);

            // Build eligible drivers list with scoring data
            $eligibleDrivers = $this->buildEligibleDriversList($trip, $excludeDriverIds, $searchRadiusKm);

            if (empty($eligibleDrivers)) {
                Log::warning('No eligible drivers found for trip', [
                    'trip_id' => $trip->id,
                    'search_radius_km' => $searchRadiusKm,
                ]);
                return null;
            }

            // Get route data for ETA calculation
            $routeService = app(GoogleRouteService::class);
            $routeData = $routeService->computeRoute(
                ['lat' => $trip->pickup_lat, 'lng' => $trip->pickup_lng],
                ['lat' => $trip->dropoff_lat, 'lng' => $trip->dropoff_lng]
            );

            $tripDurationStr = $routeData['duration'] ?? '0s';
            $tripDurationSeconds = (int) filter_var($tripDurationStr, FILTER_SANITIZE_NUMBER_INT);
            $tripDurationMinutes = (int) ceil($tripDurationSeconds / 60);
            $tripDistanceMeters = $routeData['distance_meters'] ?? 0;
            $tripDistanceKm = $tripDistanceMeters / 1000;

            // Prepare enhanced ML service payload with weighted scoring
            $payload = [
                'trip_request_id' => $trip->id,
                'vehicle_type' => 'MOTORCYCLE',
                'pickup_lat' => (float) $trip->pickup_lat,
                'pickup_lng' => (float) $trip->pickup_lng,
                'dropoff_lat' => (float) $trip->dropoff_lat,
                'dropoff_lng' => (float) $trip->dropoff_lng,
                'trip_distance_km' => $tripDistanceKm,
                'trip_duration_minutes' => $tripDurationMinutes,
                'exclude_drivers' => $excludeDriverIds,
                'search_radius_km' => $searchRadiusKm,
                'estimated_fare' => (float) $trip->estimated_fare,
                'vehicle_type_filter' => 'MOTORCYCLE',
                'drivers' => $eligibleDrivers,
                'weights' => [
                    'distance_weight' => 0.4,
                    'eta_weight' => 0.3,
                    'availability_weight' => 0.2,
                    'rating_weight' => 0.1,
                ],
            ];

            Log::debug('ML matching payload prepared', [
                'eligible_drivers_count' => count($eligibleDrivers),
                'trip_distance_km' => $tripDistanceKm,
                'trip_duration_minutes' => $tripDurationMinutes,
            ]);

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
                return $this->bestLocalMatch($eligibleDrivers, 'ML service unavailable; selected nearest eligible driver');
            }

            $data = $response->json();
            $driverId = $data['selected_driver_id'] ?? $data['driver_id'] ?? null;

            if (!$driverId) {
                Log::warning('Matching service returned no driver', [
                    'trip_id' => $trip->id,
                    'response' => $data,
                    'search_radius_km' => $searchRadiusKm,
                ]);
                return $this->bestLocalMatch($eligibleDrivers, 'ML service returned no driver; selected nearest eligible driver');
            }

            // Verify driver is still eligible
            if (!$this->isDriverEligible($driverId)) {
                Log::warning('Driver from matching service is not eligible', [
                    'trip_id' => $trip->id,
                    'driver_id' => $driverId,
                ]);
                // Request next match excluding this driver
                return $this->matchMotorcycleTrip($trip, array_merge($excludeDriverIds, [$driverId]), $searchRadiusKm);
            }

            Log::info('Matching service returned eligible driver with enhanced scoring', [
                'trip_id' => $trip->id,
                'driver_id' => $driverId,
                'score' => $data['score'] ?? null,
                'reason' => $data['reason'] ?? null,
            ]);

            return [
                'driver_id' => $driverId,
                'score' => $data['score'] ?? 0,
                'reason' => $data['reason'] ?? 'Selected by ML engine',
                'metadata' => $data,
            ];
        } catch (\Exception $e) {
            Log::error('Exception calling matching service', [
                'trip_id' => $trip->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return $this->bestLocalMatch($eligibleDrivers, 'Matching service exception: ' . $e->getMessage());
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

        if (!$this->isDriverApproved($driver)) {
            Log::warning('Driver is not approved for matching', [
                'driver_id' => $driverId,
                'status' => $driver->status,
            ]);
            return false;
        }

        if (!$this->isDriverAvailable($driver)) {
            Log::warning('Driver is not available', [
                'driver_id' => $driverId,
                'availability_status' => $driver->availability_status,
            ]);
            return false;
        }

        // Check driver has no active trip
        if ($driver->hasActiveMotoTrip()) {
            Log::warning('Driver has active motorcycle trip', ['driver_id' => $driverId]);
            return false;
        }

        // Check driver has motorcycle
        $hasMotorcycle = $this->hasActiveMotorcycle($driver);

        if (!$hasMotorcycle) {
            Log::warning('Driver has no active motorcycle', ['driver_id' => $driverId]);
            return false;
        }

        return true;
    }

    /**
     * Build list of eligible drivers with scoring data
     *
     * Calculates preliminary scores for ML engine:
     * - distance_score: 1 / (1 + distance_km)
     * - eta_score: 1 / (1 + travel_time_minutes)
     * - availability_score: 1 (always available if eligible)
     * - rating_score: driver_rating / 5
     */
    private function buildEligibleDriversList(MotorcycleTrip $trip, array $excludeDriverIds, float $searchRadiusKm): array
    {
        $drivers = [];

        try {
            // Get eligible drivers within search radius
            $eligibleDrivers = Driver::with('vehicles')
                ->where('status', 'approved')
                ->whereIn('availability_status', ['online', 'available'])
                ->where('is_online', true)
                ->where('last_seen_at', '>=', now()->subMinutes(15))
                ->whereNotIn('id', $excludeDriverIds)
                ->whereDoesntHave('motorcycleTrips', function ($query) {
                    $query->whereIn('status', ['ASSIGNED', 'DRIVER_ASSIGNED', 'PASSENGER_WAITING', 'IN_PROGRESS']);
                })
                ->get();

            // Filter by search radius and vehicle type
            foreach ($eligibleDrivers as $driver) {
                // Check if driver has motorcycle
                if (!$this->hasActiveMotorcycle($driver)) {
                    continue;
                }

                // Get driver location
                [$driverLat, $driverLng] = $this->driverCoordinates($driver);

                if (!$driverLat || !$driverLng) {
                    continue; // Skip if no location
                }

                // Calculate distance using Haversine formula
                $distance = $this->haversineDistance(
                    $trip->pickup_lat,
                    $trip->pickup_lng,
                    $driverLat,
                    $driverLng
                );

                // Filter by search radius
                if ($distance > $searchRadiusKm) {
                    continue;
                }

                // Calculate preliminary scores
                $distanceScore = 1 / (1 + $distance);
                $availabilityScore = 1; // Always 1 for eligible drivers

                // Get driver rating (default 4.5 if not set)
                $driverRating = $driver->rating ?? 4.5;
                $ratingScore = min($driverRating / 5, 1.0); // Cap at 1.0

                $drivers[] = [
                    'id' => $driver->id,
                    'lat' => (float) $driverLat,
                    'lng' => (float) $driverLng,
                    'rating' => (float) $driverRating,
                    'available' => true,
                    'distance_from_pickup_km' => round($distance, 2),
                    'preliminary_distance_score' => round($distanceScore, 3),
                    'preliminary_availability_score' => $availabilityScore,
                    'preliminary_rating_score' => round($ratingScore, 3),
                ];
            }

            Log::debug('Eligible drivers list built', [
                'trip_id' => $trip->id,
                'search_radius_km' => $searchRadiusKm,
                'eligible_drivers_count' => count($drivers),
            ]);

            return $drivers;
        } catch (\Exception $e) {
            Log::error('Exception building eligible drivers list', [
                'trip_id' => $trip->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return [];
        }
    }

    /**
     * Calculate distance between two coordinates using Haversine formula
     * Returns distance in kilometers
     */
    /** Public great-circle distance in km (used for ETA estimates). */
    public function haversineKm(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        return $this->haversineDistance($lat1, $lng1, $lat2, $lng2);
    }

    private function haversineDistance(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earthRadiusKm = 6371;

        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);

        $a = sin($dLat / 2) * sin($dLat / 2) +
             cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
             sin($dLng / 2) * sin($dLng / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadiusKm * $c;
    }

    private function isDriverApproved(Driver $driver): bool
    {
        return strtolower((string) $driver->status) === 'approved';
    }

    private function isDriverAvailable(Driver $driver): bool
    {
        if (! in_array((string) $driver->availability_status, ['online', 'available'], true)) {
            return false;
        }

        // Must have an active heartbeat within 15 minutes and be marked online.
        if (! $driver->is_online || ! $driver->last_seen_at || $driver->last_seen_at->lt(now()->subMinutes(15))) {
            return false;
        }

        if ($driver->current_trip_id) {
            return false;
        }

        return $driver->is_available === null || (bool) $driver->is_available;
    }

    private function hasActiveMotorcycle(Driver $driver): bool
    {
        return $driver->vehicles
            ->contains(fn (Vehicle $vehicle): bool => $vehicle->is_active
                && TransportMappingService::isCompatible($vehicle->vehicle_type, Ride::TRANSPORT_MOTORCYCLE));
    }

    /**
     * Resolve a driver's live coordinates (driver_locations first, then the
     * driver row). Public so callers render the SAME location the matcher used,
     * avoiding stale drivers.current_latitude values.
     *
     * @return array{0: float|null, 1: float|null}
     */
    public function driverCoordinates(Driver $driver): array
    {
        $locationDriverIds = array_filter(array_unique([
            (int) $driver->id,
            (int) $driver->user_id,
            (int) ($driver->user?->mobile_user_id ?? 0),
        ]));

        $latestLocation = DriverLocation::query()
            ->whereIn('driver_id', $locationDriverIds)
            ->orderByDesc('id')
            ->first();

        $lat = $latestLocation?->latitude ?? $latestLocation?->lat ?? $driver->current_latitude ?? $driver->last_location_lat;
        $lng = $latestLocation?->longitude ?? $latestLocation?->lng ?? $driver->current_longitude ?? $driver->last_location_lng;

        return [
            $lat !== null ? (float) $lat : null,
            $lng !== null ? (float) $lng : null,
        ];
    }

    private function bestLocalMatch(array $eligibleDrivers, string $reason): ?array
    {
        if (empty($eligibleDrivers)) {
            return null;
        }

        usort(
            $eligibleDrivers,
            fn (array $a, array $b): int => ($a['distance_from_pickup_km'] ?? INF) <=> ($b['distance_from_pickup_km'] ?? INF)
        );

        $driver = $eligibleDrivers[0];

        return [
            'driver_id' => (int) $driver['id'],
            'score' => (float) ($driver['preliminary_distance_score'] ?? 0),
            'reason' => $reason,
            'metadata' => [
                'fallback' => true,
                'distance_from_pickup_km' => $driver['distance_from_pickup_km'] ?? null,
            ],
        ];
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
                'availability_status' => 'busy',
            ]);
        }

        Log::info('Trip rematched to new driver', [
            'trip_id' => $trip->id,
            'driver_id' => $match['driver_id'],
        ]);

        return true;
    }
}
