<?php

namespace App\Http\Controllers\Api;

use App\Domain\Driver\DriverPolicy;
use App\Domain\Trip\TripStateMachine;
use App\Events\Domain\DriverAccepted;
use App\Events\Domain\TripStarted;
use App\Events\Domain\TripCompleted;
use App\Exceptions\DomainException;
use App\Models\Driver;
use App\Models\Trip;
use App\Services\Location\DriverLocationService;
use App\Services\Location\TripLocationService;
use App\Services\TransportMappingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Http\Controllers\Controller;

/**
 * MobileDriverController handles mobile app APIs for drivers.
 *
 * Focused on Flutter mobile app integration with standardized responses.
 */
class MobileDriverController extends Controller
{
    public function __construct(
        private readonly TripLocationService $tripLocationService,
        private readonly DriverLocationService $driverLocationService,
        private readonly TransportMappingService $transportMappingService,
    ) {}

    /**
     * POST /api/mobile/driver/status
     * Update driver online/offline status.
     */
    public function updateStatus(Request $request): JsonResponse
    {
        $user = $request->user();
        $driver = $user->driver;

        if (!$driver) {
            return response()->json([
                'status' => 'error',
                'type' => 'DOMAIN_ERROR',
                'message' => 'Driver profile not found',
                'code' => 404,
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'is_online' => 'required|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $validated = $validator->validated();

        // Update driver status
        $driver->availability_status = $validated['is_online'] ? 'available' : 'offline';
        $driver->last_online_at = now();
        $driver->save();

        return response()->json([
            'status' => 'success',
            'data' => [
                'is_online' => $validated['is_online'],
                'status' => $driver->availability_status,
            ],
        ]);
    }

    /**
     * GET /api/mobile/driver/trips/available
     * Get available trip requests for driver.
     */
    public function getAvailableTrips(Request $request): JsonResponse
    {
        $user = $request->user();
        $driver = $user->driver;

        if (!$driver) {
            return response()->json([
                'status' => 'error',
                'type' => 'DOMAIN_ERROR',
                'message' => 'Driver profile not found',
                'code' => 404,
            ], 404);
        }

        // Get driver's location for proximity filtering
        $driverLat = $request->query('lat');
        $driverLng = $request->query('lng');

        $query = Trip::query()
            ->with(['passenger', 'ride'])
            ->where('status', 'PENDING')
            ->whereNull('driver_id'); // Not yet assigned

        $allowedTransports = $this->getDriverTransportTypes($driver);

        if (empty($allowedTransports)) {
            return response()->json([
                'status' => 'success',
                'data' => [],
            ]);
        }

        // Filter by transport type compatibility
        $query->whereHas('ride', function ($q) use ($allowedTransports) {
            $q->whereIn('transport_type', $allowedTransports);
        });

        // Filter by proximity if coordinates provided
        if ($driverLat && $driverLng) {
            // Simple distance filter - within 10km
            $query->whereRaw(
                "(6371 * acos(cos(radians(?)) * cos(radians(pickup_lat)) * cos(radians(pickup_lng) - radians(?)) + sin(radians(?)) * sin(radians(pickup_lat)))) <= 10",
                [$driverLat, $driverLng, $driverLat]
            );
        }

        $trips = $query->orderBy('requested_at', 'asc')->limit(20)->get();

        $data = $trips->map(function ($trip) use ($driverLat, $driverLng) {
            return [
                'id' => $trip->id,
                'passenger' => [
                    'name' => $trip->passenger?->name ?? 'Unknown',
                ],
                'pickup_location' => $trip->pickup_location,
                'pickup_lat' => $trip->pickup_lat,
                'pickup_lng' => $trip->pickup_lng,
                'dropoff_location' => $trip->dropoff_location,
                'dropoff_lat' => $trip->dropoff_lat,
                'dropoff_lng' => $trip->dropoff_lng,
                'fare' => $trip->fare,
                'requested_at' => $trip->requested_at?->toIso8601String(),
                'distance_km' => $driverLat && $driverLng ? $this->calculateDistance(
                    $driverLat, $driverLng, $trip->pickup_lat, $trip->pickup_lng
                ) : null,
            ];
        });

        return response()->json([
            'status' => 'success',
            'data' => $data,
        ]);
    }

    private function getDriverTransportTypes(Driver $driver): array
    {
        return $driver->vehicles()
            ->where('is_active', true)
            ->pluck('vehicle_type')
            ->map(fn ($vehicleType) => TransportMappingService::toTransportType($vehicleType))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /**
     * POST /api/mobile/driver/trips/{id}/accept
     * Accept a trip request.
     */
    public function acceptTrip(int $id): JsonResponse
    {
        $user = request()->user();
        $driver = $user->driver;

        if (!$driver) {
            return response()->json([
                'status' => 'error',
                'type' => 'DOMAIN_ERROR',
                'message' => 'Driver profile not found',
                'code' => 404,
            ], 404);
        }

        $trip = Trip::query()
            ->with(['ride', 'passenger'])
            ->where('id', $id)
            ->where('status', 'PENDING')
            ->whereNull('driver_id')
            ->firstOrFail();

        try {
            DriverPolicy::assertCanAcceptTrip($driver, $trip);

            // Assign driver and transition state
            TripStateMachine::assertTransitionForTrip($trip, TripStateMachine::ACCEPTED);
            $trip->driver_id = $driver->id;
            $trip->status = TripStateMachine::ACCEPTED;
            $trip->save();

            event(new DriverAccepted($trip->id, $driver->id));

        } catch (DomainException $e) {
            return response()->json([
                'status' => 'error',
                'type' => 'DOMAIN_ERROR',
                'message' => $e->getMessage(),
                'code' => 422,
            ], 422);
        }

        return response()->json([
            'status' => 'success',
            'data' => [
                'trip_id' => $trip->id,
                'trip_state' => $trip->status,
            ],
        ]);
    }

    /**
     * POST /api/mobile/driver/location
     * Update driver location for active trip.
     */
    public function updateLocation(Request $request): JsonResponse
    {
        $user = $request->user();
        $driver = $user->driver;

        if (!$driver) {
            return response()->json([
                'status' => 'error',
                'type' => 'DOMAIN_ERROR',
                'message' => 'Driver profile not found',
                'code' => 404,
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'trip_id' => 'required|exists:trips,id',
            'lat' => 'required|numeric|between:-90,90',
            'lng' => 'required|numeric|between:-180,180',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $validated = $validator->validated();

        // Verify driver is assigned to this trip
        $trip = Trip::query()
            ->where('id', $validated['trip_id'])
            ->where('driver_id', $driver->id)
            ->whereIn('status', ['ACCEPTED', 'STARTED'])
            ->first();

        if (!$trip) {
            return response()->json([
                'status' => 'error',
                'type' => 'DOMAIN_ERROR',
                'message' => 'Trip not found or not assigned to you',
                'code' => 404,
            ], 404);
        }

        // Update location and broadcast live driver position
        $this->tripLocationService->updateAndBroadcast(
            $trip->id,
            $validated['lat'],
            $validated['lng']
        );

        return response()->json([
            'status' => 'success',
            'data' => [
                'trip_id' => $trip->id,
                'location_updated' => true,
            ],
        ]);
    }

    /**
     * POST /api/mobile/driver/live-location
     * Update driver live location for real-time tracking when online.
     */
    public function updateLiveLocation(Request $request): JsonResponse
    {
        $user = $request->user();
        $driver = $user->driver;

        if (!$driver) {
            return response()->json([
                'status' => 'error',
                'type' => 'DOMAIN_ERROR',
                'message' => 'Driver profile not found',
                'code' => 404,
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'lat' => 'required|numeric|between:-90,90',
            'lng' => 'required|numeric|between:-180,180',
            'speed_kmh' => 'nullable|numeric|min:0|max:200',
            'heading' => 'nullable|numeric|between:0,360',
            'accuracy' => 'nullable|numeric|min:0|max:1000',
            'is_online' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $validated = $validator->validated();

        // Update driver location for real-time tracking
        $location = $this->driverLocationService->updateLocation(
            driverId: $driver->id,
            latitude: (float) $validated['lat'],
            longitude: (float) $validated['lng'],
            speedKmh: isset($validated['speed_kmh']) ? (float) $validated['speed_kmh'] : null,
            heading: isset($validated['heading']) ? (float) $validated['heading'] : null,
            accuracy: isset($validated['accuracy']) ? (float) $validated['accuracy'] : null,
            isOnline: $validated['is_online'] ?? true,
        );

        return response()->json([
            'status' => 'success',
            'data' => [
                'driver_id' => $driver->id,
                'latitude' => (float) $location->latitude,
                'longitude' => (float) $location->longitude,
                'speed_kmh' => (float) $location->speed_kmh,
                'heading' => (float) $location->heading,
                'accuracy' => (float) $location->accuracy,
                'is_online' => (bool) $location->is_online,
                'updated_at' => $location->updated_at?->toIso8601String(),
            ],
        ]);
    }

    /**
     * PUT /api/mobile/driver/trips/{id}/start
     * Start trip (driver action).
     */
    public function startTrip(int $id): JsonResponse
    {
        $user = request()->user();
        $driver = $user->driver;

        if (!$driver) {
            return response()->json([
                'status' => 'error',
                'type' => 'DOMAIN_ERROR',
                'message' => 'Driver profile not found',
                'code' => 404,
            ], 404);
        }

        $trip = Trip::query()
            ->where('id', $id)
            ->where('driver_id', $driver->id)
            ->firstOrFail();

        try {
            TripStateMachine::assertTransitionForTrip($trip, TripStateMachine::STARTED);
            $trip->status = TripStateMachine::STARTED;
            $trip->save();

            event(new TripStarted($trip->id));

        } catch (DomainException $e) {
            return response()->json([
                'status' => 'error',
                'type' => 'DOMAIN_ERROR',
                'message' => $e->getMessage(),
                'code' => 422,
            ], 422);
        }

        return response()->json([
            'status' => 'success',
            'data' => [
                'trip_id' => $trip->id,
                'trip_state' => $trip->status,
            ],
        ]);
    }

    /**
     * PUT /api/mobile/driver/trips/{id}/complete
     * Complete trip (driver action).
     */
    public function completeTrip(int $id): JsonResponse
    {
        $user = request()->user();
        $driver = $user->driver;

        if (!$driver) {
            return response()->json([
                'status' => 'error',
                'type' => 'DOMAIN_ERROR',
                'message' => 'Driver profile not found',
                'code' => 404,
            ], 404);
        }

        $trip = Trip::query()
            ->where('id', $id)
            ->where('driver_id', $driver->id)
            ->firstOrFail();

        try {
            TripStateMachine::assertTransitionForTrip($trip, TripStateMachine::COMPLETED);
            $trip->status = TripStateMachine::COMPLETED;
            $trip->save();

            event(new TripCompleted($trip->id));

        } catch (DomainException $e) {
            return response()->json([
                'status' => 'error',
                'type' => 'DOMAIN_ERROR',
                'message' => $e->getMessage(),
                'code' => 422,
            ], 422);
        }

        return response()->json([
            'status' => 'success',
            'data' => [
                'trip_id' => $trip->id,
                'trip_state' => $trip->status,
            ],
        ]);
    }

    /**
     * PUT /api/mobile/driver/trips/{id}/cancel
     * Cancel trip (driver action).
     */
    public function cancelTrip(int $id): JsonResponse
    {
        $user = request()->user();
        $driver = $user->driver;

        if (!$driver) {
            return response()->json([
                'status' => 'error',
                'type' => 'DOMAIN_ERROR',
                'message' => 'Driver profile not found',
                'code' => 404,
            ], 404);
        }

        $trip = Trip::query()
            ->where('id', $id)
            ->where('driver_id', $driver->id)
            ->firstOrFail();

        try {
            TripStateMachine::assertTransitionForTrip($trip, TripStateMachine::CANCELLED);
            $trip->status = TripStateMachine::CANCELLED;
            $trip->save();

        } catch (DomainException $e) {
            return response()->json([
                'status' => 'error',
                'type' => 'DOMAIN_ERROR',
                'message' => $e->getMessage(),
                'code' => 422,
            ], 422);
        }

        return response()->json([
            'status' => 'success',
            'data' => [
                'trip_id' => $trip->id,
                'trip_state' => $trip->status,
            ],
        ]);
    }

    private function calculateDistance(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earthRadius = 6371; // km

        $latDelta = deg2rad($lat2 - $lat1);
        $lngDelta = deg2rad($lng2 - $lng1);

        $a = sin($latDelta / 2) * sin($latDelta / 2) +
             cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
             sin($lngDelta / 2) * sin($lngDelta / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadius * $c;
    }
}