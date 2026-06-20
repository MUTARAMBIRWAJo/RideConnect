<?php

namespace App\Http\Controllers\Api\V3;

use App\Http\Controllers\Controller;
use App\Http\Requests\V3\MotorVehicleTripRequestV3;
use App\Http\Requests\V3\PrivateCarTripRequestV3;
use App\Http\Requests\V3\PublicBusTripRequestV3;
use App\Models\V3\TripV3;
use App\Services\V3\TripLifecycleEngineV3;
use App\Services\V3\TripMatchingEngineV3;
use App\Services\V3\NotificationServiceV3;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TripControllerV3 extends Controller
{
    private TripLifecycleEngineV3 $lifecycle;
    private TripMatchingEngineV3 $matchingEngine;

    public function __construct(TripLifecycleEngineV3 $lifecycle, TripMatchingEngineV3 $matchingEngine)
    {
        $this->lifecycle = $lifecycle;
        $this->matchingEngine = $matchingEngine;
    }

    public function notifyDriver(string $id, NotificationServiceV3 $notificationService): JsonResponse
    {
        $trip = TripV3::findOrFail($id);

        $driverId = $trip->matched_driver_id ?: $trip->driver_id;

        if (!$driverId) {
            return response()->json([
                'success' => false,
                'message' => 'No driver is currently assigned or matched to this trip.'
            ], 422);
        }

        $trip->loadMissing('user');
        $passengerName = $trip->user?->name ?? 'Passenger';

        $notificationService->sendToDriver($driverId, [
            'type' => 'NEW_TRIP_REQUEST',
            'trip_id' => $trip->id,
            'passenger_name' => $passengerName,
            'pickup' => $trip->pickup_location,
            'dropoff' => $trip->dropoff_location,
            'fare' => $trip->fare_estimate ?? 4500,
            'message' => 'New trip request from ' . $passengerName . '. Accept or reject.',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Notification sent to assigned driver.',
            'data' => [
                'trip_id' => $trip->id,
                'driver_id' => $driverId,
            ]
        ]);
    }

    public function requestMotorVehicle(MotorVehicleTripRequestV3 $request): JsonResponse
    {
        $pickupLat = (float) $request->validated('pickup_lat');
        $pickupLng = (float) $request->validated('pickup_lng');

        // Create the trip V3
        $trip = new TripV3([
            'user_id' => $request->user()->id,
            'transport_type' => 'motor_vehicle',
            'pickup_location' => $request->validated('pickup_location'),
            'pickup_lat' => $pickupLat,
            'pickup_lng' => $pickupLng,
            'dropoff_location' => $request->validated('dropoff_location'),
            'dropoff_lat' => (float) $request->validated('dropoff_lat'),
            'dropoff_lng' => (float) $request->validated('dropoff_lng'),
            'metadata' => [
                'ride_mode' => $request->validated('ride_mode'),
                'payment_method' => $request->validated('payment_method'),
                'requested_seats' => 1,
            ],
        ]);
        $trip->save();

        // Calculate and query the top 3 nearest online motor vehicle drivers to this pickup location
        $haversine = "( 6371 * acos( cos( radians($pickupLat) ) * cos( radians( current_latitude ) ) * cos( radians( current_longitude ) - radians($pickupLng) ) + sin( radians($pickupLat) ) * sin( radians( current_latitude ) ) ) )";

        $drivers = \App\Models\Driver::query()
            ->with(['user', 'vehicle'])
            ->select('drivers.*')
            ->selectRaw("$haversine AS distance_km")
            ->join('vehicles', 'vehicles.driver_id', '=', 'drivers.id')
            ->where('drivers.status', 'approved')
            ->where('drivers.is_online', true)
            ->whereIn('drivers.availability_status', ['online', 'available'])
            ->whereNull('drivers.current_trip_id')
            ->whereIn('vehicles.vehicle_type', ['motorcycle', 'boda', 'moto', 'motorbike', 'tuk-tuk'])
            ->orderByRaw("$haversine ASC")
            ->limit(3)
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Trip request created. Online matching drivers retrieved.',
            'data' => [
                'trip' => $trip,
                'drivers' => $drivers->map(fn ($d) => [
                    'id' => $d->id,
                    'name' => $d->user?->name,
                    'phone' => $d->user?->phone,
                    'rating' => (float) $d->rating,
                    'distance_km' => round((float) $d->distance_km, 2),
                    'estimated_arrival_minutes' => max(1, (int) ceil(($d->distance_km / 25) * 60)),
                    'current_location' => [
                        'latitude' => (float) $d->current_latitude,
                        'longitude' => (float) $d->current_longitude,
                    ],
                    'vehicle' => $d->vehicle ? [
                        'vehicle_type' => $d->vehicle->vehicle_type,
                        'plate_number' => $d->license_plate,
                        'color' => $d->vehicle->color,
                    ] : null,
                ])
            ]
        ], 201);
    }

    public function selectDriver(Request $request, string $id): JsonResponse
    {
        $driverId = $request->input('driver_id');
        $tripId = $id;

        if (!$driverId) {
            // Fallback: check if the passenger has an active trip, and treat the URL parameter as the driver_id
            $activeTrip = TripV3::where('user_id', $request->user()->id)
                ->whereIn('status', ['created', 'searching', 'REQUESTED', 'MATCHING'])
                ->latest()
                ->first();

            if ($activeTrip) {
                $driverId = $id;
                $trip = $activeTrip;
            } else {
                // Trigger standard validation if no active trip is found
                $request->validate([
                    'driver_id' => 'required|integer|exists:drivers,id',
                ]);
                return response()->json([], 422); // Unreachable, but satisfies static analysis
            }
        } else {
            $trip = TripV3::findOrFail($tripId);
        }

        // Validate the driver_id explicitly
        $validator = \Illuminate\Support\Facades\Validator::make(['driver_id' => $driverId], [
            'driver_id' => 'required|integer|exists:drivers,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'The given data was invalid.',
                'errors' => $validator->errors()
            ], 422);
        }

        if (!in_array($trip->status, ['created', 'searching', 'REQUESTED', 'MATCHING'], true)) {
            return response()->json([
                'success' => false,
                'message' => 'Trip is no longer in a state where a driver can be assigned. Current status: ' . $trip->status,
            ], 422);
        }

        $trip->matched_driver_id = $driverId;
        $trip->save();

        $this->matchingEngine->startMatching($trip);

        $trip->loadMissing(['user', 'matchedDriver.user', 'matchedDriver.vehicle']);

        return response()->json([
            'success' => true,
            'message' => 'Driver selected and matching initiated.',
            'data' => [
                'trip' => $trip,
                'passenger' => [
                    'id' => $trip->user?->id,
                    'name' => $trip->user?->name,
                    'phone' => $trip->user?->phone,
                ],
                'driver' => $trip->matchedDriver ? [
                    'id' => $trip->matchedDriver->id,
                    'name' => $trip->matchedDriver->user?->name,
                    'phone' => $trip->matchedDriver->user?->phone,
                    'rating' => (float) $trip->matchedDriver->rating,
                    'vehicle' => $trip->matchedDriver->vehicle ? [
                        'vehicle_type' => $trip->matchedDriver->vehicle->vehicle_type,
                        'plate_number' => $trip->matchedDriver->license_plate,
                        'color' => $trip->matchedDriver->vehicle->color,
                    ] : null,
                ] : null,
            ],
        ]);
    }

    public function requestPrivateCar(PrivateCarTripRequestV3 $request): JsonResponse
    {
        $trip = new TripV3([
            'user_id' => $request->user()->id,
            'transport_type' => 'private_car',
            'pickup_location' => $request->validated('pickup_location'),
            'pickup_lat' => $request->validated('pickup_lat'),
            'pickup_lng' => $request->validated('pickup_lng'),
            'dropoff_location' => $request->validated('dropoff_location'),
            'dropoff_lat' => $request->validated('dropoff_lat'),
            'dropoff_lng' => $request->validated('dropoff_lng'),
            'metadata' => [
                'car_type_preference' => $request->validated('car_type_preference'),
                'scheduled_time' => $request->validated('scheduled_time'),
                'requested_seats' => $request->validated('requested_seats') ?? 1,
            ],
        ]);
        $trip->save();

        $this->matchingEngine->startMatching($trip);

        return response()->json([
            'success' => true,
            'data' => $trip,
        ], 201);
    }

    public function requestPublicBus(PublicBusTripRequestV3 $request): JsonResponse
    {
        $trip = new TripV3([
            'user_id' => $request->user()->id,
            'transport_type' => 'public_bus',
            'pickup_location' => $request->validated('pickup_stop'),
            'pickup_lat' => $request->validated('pickup_lat'),
            'pickup_lng' => $request->validated('pickup_lng'),
            'dropoff_location' => $request->validated('dropoff_stop'),
            'dropoff_lat' => $request->validated('dropoff_lat'),
            'dropoff_lng' => $request->validated('dropoff_lng'),
            'metadata' => [
                'route_id' => $request->validated('route_id'),
                'driver_id' => $request->validated('driver_id'),
                'passenger_count' => $request->validated('passenger_count'),
                'preferred_time' => $request->validated('preferred_time'),
            ],
        ]);
        $trip->save();

        $this->matchingEngine->startMatching($trip);

        return response()->json([
            'success' => true,
            'data' => $trip,
        ], 201);
    }
    public function matchingStatus(string $id): JsonResponse
    {
        $trip = TripV3::findOrFail($id);
        
        $elapsedSeconds = $trip->matching_started_at ? $trip->matching_started_at->diffInSeconds(now()) : 0;
        
        $driver = null;
        $driverId = $trip->matched_driver_id ?: $trip->driver_id;
        if ($driverId) {
            $d = \App\Models\Driver::with(['user', 'vehicle'])->find($driverId);
            if ($d) {
                $driver = [
                    'id' => $d->id,
                    'name' => $d->user?->name,
                    'phone' => $d->user?->phone,
                    'rating' => (float) ($d->rating ?? 0.0),
                    'vehicle_type' => $d->vehicle?->vehicle_type ?? $d->license_plate,
                    'plate_number' => $d->license_plate,
                    'color' => $d->vehicle?->color,
                ];
            }
        }

        return response()->json([
            'success' => true,
            'data' => [
                'status' => $trip->status,
                'attempts' => $trip->match_attempt_count,
                'elapsed_seconds' => $elapsedSeconds,
                'fallback_used' => (bool) $trip->fallback_match_used,
                'driver' => $driver,
            ]
        ]);
    }

    public function status(string $id): JsonResponse
    {
        $trip = TripV3::findOrFail($id);
        
        $driverLocation = null;
        $driver = null;
        $driverId = $trip->driver_id ?: $trip->matched_driver_id;

        if ($driverId) {
            $d = \App\Models\Driver::with(['user', 'vehicle'])->find($driverId);
            if ($d) {
                $driver = [
                    'id' => $d->id,
                    'name' => $d->user?->name,
                    'phone' => $d->user?->phone,
                    'rating' => (float) ($d->rating ?? 0.0),
                    'vehicle_type' => $d->vehicle?->vehicle_type,
                    'plate_number' => $d->license_plate,
                    'color' => $d->vehicle?->color,
                ];
            }

            $driverUserId = $d?->user_id ?: $driverId;
            $loc = \App\Models\DriverLocation::where('driver_id', $driverUserId)->first();
            if ($loc) {
                $driverLocation = [
                    'lat' => (float) ($loc->lat ?? $loc->latitude),
                    'lng' => (float) ($loc->lng ?? $loc->longitude),
                    'heading' => $loc->heading ? (float) $loc->heading : null,
                    'speed' => $loc->speed ? (float) $loc->speed : ((float) $loc->speed_kmh ?: null),
                    'updated_at' => $loc->updated_at?->toIso8601String(),
                ];
            }
        }

        return response()->json([
            'success' => true,
            'data' => [
                'trip_id' => $trip->id,
                'status' => $trip->status,
                'driver_location' => $driverLocation,
                'driver' => $driver,
                'eta' => '5 min', // In a real app, calculate via Google Maps Matrix API
                'distance_remaining' => '2.3 km' // In a real app, calculate distance
            ]
        ]);
    }

    public function passengerTrips(Request $request): JsonResponse
    {
        $trips = TripV3::where('user_id', $request->user()->id)
            ->with(['driver.user', 'driver.vehicle'])
            ->latest()
            ->get();

        return response()->json([
            'success' => true,
            'data' => $trips,
        ]);
    }

    public function matchTrip(string $id): JsonResponse
    {
        $trip = TripV3::findOrFail($id);

        if (! in_array($trip->status, ['created', 'searching', 'MATCHING', 'REQUESTED'], true)) {
            return response()->json([
                'success' => false,
                'message' => "Trip is not in a matchable state. Current status: {$trip->status}",
            ], 422);
        }

        $this->matchingEngine->startMatching($trip);

        return response()->json([
            'success' => true,
            'message' => 'Matching started/retried successfully.',
            'data' => $trip->fresh(),
        ]);
    }

    public function onlineDrivers(Request $request): JsonResponse
    {
        $user = $request->user();

        // Check if user is a passenger
        if (method_exists($user, 'isPassenger') && !$user->isPassenger()) {
            return response()->json([
                'success' => false,
                'message' => 'Only passengers can access online drivers',
            ], 403);
        }

        $query = \App\Models\Driver::query()
            ->with(['user:id,name,phone,is_approved', 'vehicles'])
            ->where('status', 'approved')
            ->whereIn('availability_status', ['online', 'available'])
            ->whereHas('user', fn ($q) => $q->where('is_approved', true));

        // Optional transport type filtering for V3
        if ($request->has('transport_type')) {
            $transportType = $request->input('transport_type');
            if (in_array($transportType, ['motor_vehicle', 'motorcycle', 'moto', 'motorbike'], true)) {
                $query->whereHas('vehicles', fn($q) => $q->where('is_active', true)->whereIn('vehicle_type', ['motorcycle', 'boda', 'moto', 'motorbike', 'tuk-tuk']));
            } elseif (in_array($transportType, ['private_car', 'car', 'private'], true)) {
                $query->whereHas('vehicles', fn($q) => $q->where('is_active', true)->whereIn('vehicle_type', ['sedan', 'suv', 'hatchback', 'van', 'compact', 'minivan']));
            }
        }

        $drivers = $query->orderByDesc('last_seen_at')
            ->limit((int) $request->integer('limit', 100))
            ->get();

        return response()->json([
            'success' => true,
            'data' => $drivers->map(fn (\App\Models\Driver $driver) => [
                'id' => $driver->id,
                'name' => $driver->user?->name,
                'phone' => $driver->user?->phone,
                'rating' => (float) $driver->rating,
                'total_rides' => (int) $driver->total_rides,
                'availability_status' => $driver->availability_status,
                'current_latitude' => $driver->current_latitude,
                'current_longitude' => $driver->current_longitude,
                'last_online_at' => ($driver->last_seen_at ?? $driver->last_online_at)?->toIso8601String(),
                'vehicle' => $driver->vehicle ? [
                    'vehicle_type' => $driver->vehicle->vehicle_type,
                    'plate_number' => $driver->license_plate,
                    'color' => $driver->vehicle->color,
                ] : null,
            ]),
        ]);
    }
}
