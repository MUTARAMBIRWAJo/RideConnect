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

        $notificationService->sendToDriver($driverId, [
            'type' => 'NEW_TRIP_REQUEST',
            'trip_id' => $trip->id,
            'pickup' => $trip->pickup_location,
            'dropoff' => $trip->dropoff_location,
            'fare' => $trip->fare_estimate ?? 4500,
            'message' => 'New trip request available. Accept or reject.',
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
        $trip = new TripV3([
            'user_id' => $request->user()->id,
            'transport_type' => 'motor_vehicle',
            'pickup_location' => $request->validated('pickup_location'),
            'pickup_lat' => $request->validated('pickup_lat'),
            'pickup_lng' => $request->validated('pickup_lng'),
            'dropoff_location' => $request->validated('dropoff_location'),
            'dropoff_lat' => $request->validated('dropoff_lat'),
            'dropoff_lng' => $request->validated('dropoff_lng'),
            'metadata' => [
                'ride_mode' => $request->validated('ride_mode'),
                'payment_method' => $request->validated('payment_method'),
                'requested_seats' => 1,
            ],
        ]);
        $trip->save();

        $this->matchingEngine->startMatching($trip);

        return response()->json([
            'success' => true,
            'data' => $trip,
        ], 201);
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
        
        return response()->json([
            'status' => $trip->status,
            'attempts' => $trip->match_attempt_count,
            'elapsed_seconds' => $elapsedSeconds,
            'fallback_used' => (bool) $trip->fallback_match_used,
        ]);
    }

    public function status(string $id): JsonResponse
    {
        $trip = TripV3::findOrFail($id);
        
        $driverLocation = null;
        if ($trip->driver_id) {
            $driverUserId = \App\Models\Driver::query()->where('id', $trip->driver_id)->value('user_id') ?: $trip->driver_id;
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
            'trip_id' => $trip->id,
            'status' => $trip->status,
            'driver_location' => $driverLocation,
            'eta' => '5 min', // In a real app, calculate via Google Maps Matrix API
            'distance_remaining' => '2.3 km' // In a real app, calculate distance
        ]);
    }
}
