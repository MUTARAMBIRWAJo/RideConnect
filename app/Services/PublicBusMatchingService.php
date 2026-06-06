<?php

namespace App\Services;

use App\Exceptions\GeocodingException;
use App\Models\BusRouteAssignment;
use App\Models\TransportCorridor;
use App\Models\TripRequest;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * PublicBusMatchingService handles the smart public bus trip request and matching flow.
 *
 * Business flow:
 * 1. Accept corridor_id, pickup_location (name), dropoff_location (name)
 * 2. Geocode both location names to coordinates
 * 3. Find active buses on the corridor
 * 4. Calculate distance to nearest bus
 * 5. Calculate ETA based on bus location and speed
 * 6. Calculate route details (distance, duration) using Google Directions API
 * 7. Calculate fare
 * 8. Create trip_request record
 * 9. Return matching data
 */
class PublicBusMatchingService
{
    private const GOOGLE_DIRECTIONS_URL = 'https://maps.googleapis.com/maps/api/directions/json';
    private const AVERAGE_BUS_SPEED_KMH = 40; // Default bus speed in km/h

    public function __construct(
        private readonly GoogleMapsGeocodingService $googleMapsGeocodingService,
        private readonly FareCalculatorService $fareCalculatorService,
        private readonly PublicBusTransportService $busTransportService,
        private readonly DistanceService $distanceService,
    ) {}

    /**
     * Request a public bus trip with smart matching.
     *
     * @param User $passenger
     * @param array{
     *     corridor_id: int,
     *     pickup_location: string,
     *     dropoff_location: string
     * } $data
     * @return array Matching result with bus and fare details
     */
    public function requestTrip(User $passenger, array $data): array
    {
        // Step 1: Load corridor
        $corridor = TransportCorridor::query()->findOrFail($data['corridor_id']);

        try {
            // Step 2: Geocode pickup location using robust service with fallback
            $pickupCoords = $this->googleMapsGeocodingService->geocode($data['pickup_location']);
        } catch (GeocodingException $e) {
            Log::error('Pickup geocoding failed', ['location' => $data['pickup_location']]);
            throw new \Exception("Could not geocode pickup location: {$data['pickup_location']}");
        }

        try {
            // Step 3: Geocode dropoff location using robust service with fallback
            $dropoffCoords = $this->googleMapsGeocodingService->geocode($data['dropoff_location']);
        } catch (GeocodingException $e) {
            Log::error('Dropoff geocoding failed', ['location' => $data['dropoff_location']]);
            throw new \Exception("Could not geocode dropoff location: {$data['dropoff_location']}");
        }

        // Step 4: Find active buses on corridor
        $activeBuses = $this->busTransportService->activeBuses($corridor);
        if ($activeBuses->isEmpty()) {
            throw new \Exception('No active buses found on this corridor');
        }

        // Step 5: Find nearest bus with distance calculation
        $nearestBus = $this->findNearestBus(
            $activeBuses->toArray(),
            $pickupCoords['lat'],
            $pickupCoords['lng']
        );

        if (! $nearestBus) {
            throw new \Exception('Could not calculate distance to buses');
        }

        // Step 6: Calculate ETA to passenger
        $busEtaMinutes = $this->calculateEta(
            $nearestBus['distance_to_passenger_km'],
            self::AVERAGE_BUS_SPEED_KMH
        );

        // Step 7: Get route details (distance and duration) using Google Directions
        $routeDetails = $this->getRouteDetails(
            $pickupCoords['lat'],
            $pickupCoords['lng'],
            $dropoffCoords['lat'],
            $dropoffCoords['lng']
        );

        // Step 8: Calculate fare
        $estimatedFare = $this->fareCalculatorService->estimate(
            $pickupCoords['lat'],
            $pickupCoords['lng'],
            $dropoffCoords['lat'],
            $dropoffCoords['lng'],
            'bus'
        );

        // Step 9: Create trip request record
        $tripRequest = TripRequest::query()->create([
            'passenger_id' => $passenger->id,
            'corridor_id' => $corridor->id,
            'pickup_location' => $data['pickup_location'],
            'pickup_lat' => $pickupCoords['lat'],
            'pickup_lng' => $pickupCoords['lng'],
            'dropoff_location' => $data['dropoff_location'],
            'dropoff_lat' => $dropoffCoords['lat'],
            'dropoff_lng' => $dropoffCoords['lng'],
            'matched_driver_id' => $nearestBus['driver_id'],
            'matched_vehicle_id' => $nearestBus['vehicle_id'],
            'distance_to_bus_km' => $nearestBus['distance_to_passenger_km'],
            'bus_eta_minutes' => $busEtaMinutes,
            'trip_distance_km' => $routeDetails['distance_km'] ?? 0,
            'trip_duration_minutes' => $routeDetails['duration_minutes'] ?? 0,
            'estimated_fare' => $estimatedFare,
            'currency' => 'RWF',
            'status' => 'PENDING_MATCH',
        ]);

        // Step 10: Return formatted response
        return $this->formatResponse($tripRequest, $nearestBus);
    }

    /**
     * Get trip request details and current status.
     */
    public function getRequest(TripRequest $tripRequest): array
    {
        $tripRequest->load('passenger', 'corridor', 'driver.user', 'vehicle');

        return $this->formatResponse($tripRequest, [
            'vehicle_id' => $tripRequest->vehicle_id,
            'driver_id' => $tripRequest->driver_id,
            'vehicle_plate_number' => $tripRequest->vehicle?->license_plate,
            'vehicle_capacity' => $tripRequest->vehicle?->seats,
            'vehicle_available_seats' => $this->getAvailableSeats($tripRequest->vehicle_id),
            'driver_name' => $tripRequest->driver?->user?->name,
            'distance_to_passenger_km' => $tripRequest->distance_to_bus_km,
        ]);
    }

    /**
     * Find the nearest active bus to the passenger's pickup location.
     *
     * Uses DistanceService for accurate distance calculations with Google Distance Matrix
     * API fallback to Haversine formula.
     *
     * Extracts bus location from latest_position or location fields in the bus data.
     *
     * @param array $activeBuses List of active bus assignments (formatted by PublicBusTransportService)
     * @param float $pickupLat Passenger pickup latitude
     * @param float $pickupLng Passenger pickup longitude
     * @return array|null Bus data with distance or null if no buses
     */
    private function findNearestBus(array $activeBuses, float $pickupLat, float $pickupLng): ?array
    {
        if (empty($activeBuses)) {
            Log::warning('No active buses provided to findNearestBus');
            return null;
        }

        // Prepare destination coordinates for batch distance calculation
        $destinations = [];
        $busDataMap = [];

        foreach ($activeBuses as $index => $busData) {
            // Extract bus location from latest_position or location field
            $busLatitude = null;
            $busLongitude = null;

            // Try latest_position first (from bus_position_updates)
            if (isset($busData['latest_position']['latitude'], $busData['latest_position']['longitude'])) {
                $busLatitude = (float) $busData['latest_position']['latitude'];
                $busLongitude = (float) $busData['latest_position']['longitude'];
            }
            // Fallback to location field (from driver profile or bus_position)
            elseif (isset($busData['location']['latitude'], $busData['location']['longitude'])) {
                $busLatitude = (float) $busData['location']['latitude'];
                $busLongitude = (float) $busData['location']['longitude'];
            }

            if ($busLatitude === null || $busLongitude === null) {
                Log::debug('Skipping bus without location data', [
                    'bus_id' => $busData['bus_id'] ?? null,
                    'assignment_id' => $busData['assignment_id'] ?? null,
                ]);
                continue;
            }

            // Validate coordinates
            if ($busLatitude < -90 || $busLatitude > 90 || $busLongitude < -180 || $busLongitude > 180) {
                Log::warning('Invalid bus coordinates', [
                    'bus_id' => $busData['bus_id'] ?? null,
                    'lat' => $busLatitude,
                    'lng' => $busLongitude,
                ]);
                continue;
            }

            $destinations[] = [
                'id' => $index,
                'lat' => $busLatitude,
                'lng' => $busLongitude,
            ];
            $busDataMap[$index] = $busData;

            Log::debug('Added bus to distance calculation', [
                'bus_id' => $busData['bus_id'],
                'lat' => $busLatitude,
                'lng' => $busLongitude,
            ]);
        }

        if (empty($destinations)) {
            Log::warning('No valid bus coordinates found', [
                'total_buses' => count($activeBuses),
            ]);
            return null;
        }

        try {
            Log::info('Starting batch distance calculation', [
                'pickup_location' => ["lat" => $pickupLat, "lng" => $pickupLng],
                'bus_count' => count($destinations),
            ]);

            // Use DistanceService for batch distance calculation
            $sortedDestinations = $this->distanceService->calculateBatchDistances(
                $pickupLat,
                $pickupLng,
                $destinations
            );

            if (empty($sortedDestinations)) {
                Log::warning('Distance calculation returned no results');
                return null;
            }

            // Get the nearest bus (first in sorted array)
            $nearestDest = $sortedDestinations[0];
            $busIndex = $nearestDest['id'];
            $busData = $busDataMap[$busIndex];
            $distanceKm = $nearestDest['distance_km'];
            $durationMinutes = $nearestDest['duration_minutes'];

            Log::info('Nearest bus found', [
                'bus_id' => $busData['bus_id'],
                'distance_km' => $distanceKm,
                'duration_minutes' => $durationMinutes,
                'eta_minutes' => $durationMinutes,
            ]);

            return [
                'bus_id' => $busData['bus_id'],
                'vehicle_id' => $busData['bus_id'],
                'driver_id' => $busData['driver']['id'] ?? null,
                'vehicle_plate_number' => $busData['bus']['plate'] ?? $busData['bus']['license_plate'] ?? null,
                'vehicle_capacity' => $busData['bus']['seats'] ?? 0,
                'vehicle_available_seats' => $busData['available_seats'] ?? 0,
                'driver_name' => $busData['driver']['name'] ?? null,
                'distance_to_passenger_km' => $distanceKm,
                'eta_minutes' => $durationMinutes,
            ];
        } catch (\Exception $e) {
            Log::error('Error in findNearestBus', [
                'error' => $e->getMessage(),
                'bus_count' => count($destinations),
                'trace' => $e->getTraceAsString(),
            ]);
            return null;
        }
    }

    /**
     * Calculate distance between two geographic points using Haversine formula.
     *
     * DEPRECATED: Use DistanceService instead for better API integration.
     *
     * @param float $lat1 Latitude of first point
     * @param float $lng1 Longitude of first point
     * @param float $lat2 Latitude of second point
     * @param float $lng2 Longitude of second point
     * @return float Distance in kilometers
     */
    private function calculateDistance(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earthRadiusKm = 6371;
        $latDelta = deg2rad($lat2 - $lat1);
        $lngDelta = deg2rad($lng2 - $lng1);

        $a = sin($latDelta / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($lngDelta / 2) ** 2;

        return $earthRadiusKm * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }

    /**
     * Calculate ETA in minutes based on distance and speed.
     *
     * @param float $distanceKm Distance in kilometers
     * @param float $speedKmh Average speed in km/h
     * @return int ETA in minutes
     */
    private function calculateEta(float $distanceKm, float $speedKmh): int
    {
        if ($speedKmh <= 0) {
            $speedKmh = self::AVERAGE_BUS_SPEED_KMH;
        }

        return (int) round(($distanceKm / $speedKmh) * 60);
    }

    /**
     * Get route details (distance and duration) using DistanceService.
     *
     * Uses Google Distance Matrix API for accurate calculations with Haversine fallback.
     *
     * @param float $pickupLat
     * @param float $pickupLng
     * @param float $dropoffLat
     * @param float $dropoffLng
     * @return array{distance_km: float, duration_minutes: int}
     */
    private function getRouteDetails(
        float $pickupLat,
        float $pickupLng,
        float $dropoffLat,
        float $dropoffLng
    ): array {
        try {
            Log::debug('Calculating route details', [
                'pickup' => ["lat" => $pickupLat, "lng" => $pickupLng],
                'dropoff' => ["lat" => $dropoffLat, "lng" => $dropoffLng],
            ]);

            $result = $this->distanceService->calculateDistance(
                $pickupLat,
                $pickupLng,
                $dropoffLat,
                $dropoffLng
            );

            Log::info('Route details calculated successfully', [
                'distance_km' => $result['distance_km'],
                'duration_minutes' => $result['duration_minutes'],
            ]);

            return [
                'distance_km' => $result['distance_km'],
                'duration_minutes' => $result['duration_minutes'],
            ];
        } catch (\Exception $e) {
            Log::error('Error calculating route details', [
                'error' => $e->getMessage(),
                'pickup' => ["lat" => $pickupLat, "lng" => $pickupLng],
                'dropoff' => ["lat" => $dropoffLat, "lng" => $dropoffLng],
            ]);

            // Fallback to Haversine calculation
            $distanceKm = round($this->calculateDistance($pickupLat, $pickupLng, $dropoffLat, $dropoffLng), 2);
            return [
                'distance_km' => $distanceKm,
                'duration_minutes' => $this->calculateEta($distanceKm, self::AVERAGE_BUS_SPEED_KMH),
            ];
        }
    }

    /**
     * Get available seats for a vehicle.
     */
    private function getAvailableSeats(?int $vehicleId): int
    {
        if (! $vehicleId) {
            return 0;
        }

        // Query the bus route assignment for this vehicle
        $assignment = BusRouteAssignment::query()
            ->where('bus_id', $vehicleId)
            ->where('status', 'active')
            ->latest()
            ->first();

        if (! $assignment || ! $assignment->bus) {
            return 0;
        }

        $totalSeats = $assignment->bus->seats ?? 0;
        $bookedSeats = $assignment->passengerBoardings()->count();

        return max(0, $totalSeats - $bookedSeats);
    }

    /**
     * Format the response for API output.
     */
    private function formatResponse(TripRequest $tripRequest, array $busData): array
    {
        return [
            'success' => true,
            'message' => 'Public bus match found',
            'data' => [
                'trip_request_id' => $tripRequest->id,
                'corridor' => [
                    'id' => $tripRequest->corridor_id,
                    'code' => $tripRequest->corridor->corridor_code,
                    'name' => $tripRequest->corridor->corridor_name,
                ],
                'pickup' => [
                    'name' => $tripRequest->pickup_location,
                    'latitude' => (float) $tripRequest->pickup_lat,
                    'longitude' => (float) $tripRequest->pickup_lng,
                ],
                'dropoff' => [
                    'name' => $tripRequest->dropoff_location,
                    'latitude' => (float) $tripRequest->dropoff_lat,
                    'longitude' => (float) $tripRequest->dropoff_lng,
                ],
                'matched_bus' => [
                    'vehicle_id' => $busData['vehicle_id'] ?? $busData['bus_id'] ?? null,
                    'plate_number' => $busData['vehicle_plate_number'] ?? null,
                    'capacity' => $busData['vehicle_capacity'] ?? null,
                    'available_seats' => $busData['vehicle_available_seats'] ?? 0,
                ],
                'driver' => [
                    'id' => $busData['driver_id'] ?? null,
                    'name' => $busData['driver_name'] ?? null,
                ],
                'distance_to_bus_km' => $tripRequest->distance_to_bus_km,
                'bus_eta_minutes' => $tripRequest->bus_eta_minutes,
                'trip_distance_km' => (float) $tripRequest->trip_distance_km,
                'trip_duration_minutes' => $tripRequest->trip_duration_minutes,
                'estimated_fare' => (float) $tripRequest->estimated_fare,
                'currency' => $tripRequest->currency,
                'status' => $tripRequest->status,
            ],
        ];
    }
}
