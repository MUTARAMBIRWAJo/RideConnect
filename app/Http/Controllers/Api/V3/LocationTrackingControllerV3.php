<?php

namespace App\Http\Controllers\Api\V3;

use App\Http\Controllers\Controller;
use App\Models\Driver;
use App\Models\DriverLocation;
use App\Models\V3\DriverLocationV3;
use App\Models\PassengerLocation;
use App\Models\LocationHistory;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use OpenApi\Attributes as OA;

class LocationTrackingControllerV3 extends Controller
{
    #[OA\Post(
        path: '/v3/location/update',
        summary: 'Update current location for authenticated user',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\MediaType(
                mediaType: 'application/json',
                schema: new OA\Schema(
                    required: ['latitude', 'longitude'],
                    properties: [
                        new OA\Property(property: 'latitude', type: 'number', format: 'float'),
                        new OA\Property(property: 'longitude', type: 'number', format: 'float'),
                        new OA\Property(property: 'heading', type: 'integer', nullable: true),
                        new OA\Property(property: 'speed', type: 'number', format: 'float', nullable: true),
                        new OA\Property(property: 'accuracy', type: 'number', format: 'float', nullable: true),
                        new OA\Property(property: 'trip_id', type: 'integer', nullable: true),
                    ]
                )
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Success')
        ]
    )]
    public function updateLocation(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'heading' => 'nullable|integer|between:0,360',
            'speed' => 'nullable|numeric|min:0',
            'accuracy' => 'nullable|numeric|min:0',
            'trip_id' => 'nullable|integer',
        ]);

        $user = $request->user();
        $lat = $validated['latitude'];
        $lng = $validated['longitude'];
        $heading = $validated['heading'] ?? null;
        $speed = $validated['speed'] ?? null;
        $accuracy = $validated['accuracy'] ?? null;
        $tripId = $validated['trip_id'] ?? null;

        $driver = Driver::where('user_id', $user->id)->first();
        $role = $driver ? 'driver' : 'passenger';

        DB::transaction(function () use ($user, $driver, $role, $lat, $lng, $heading, $speed, $accuracy, $tripId) {
            if ($role === 'driver' && $driver) {
                // 1. Update v3 driver location (for real-time matching / tracking)
                DriverLocationV3::updateOrCreate(
                    ['driver_id' => $driver->id],
                    [
                        'trip_id' => $tripId,
                        'lat' => $lat,
                        'lng' => $lng,
                        'latitude' => $lat,
                        'longitude' => $lng,
                        'heading' => $heading,
                        'speed' => $speed,
                        'is_online' => true,
                    ]
                );

                // 2. Also keep legacy driver_locations table synchronized
                DriverLocation::updateOrCreate(
                    ['driver_id' => $driver->id],
                    [
                        'trip_id' => $tripId,
                        'lat' => $lat,
                        'lng' => $lng,
                        'latitude' => $lat,
                        'longitude' => $lng,
                        'heading' => $heading,
                        'speed' => $speed,
                        'accuracy' => $accuracy,
                        'is_online' => true,
                        'last_activity_at' => now(),
                        'recorded_at' => now(),
                    ]
                );

                // 3. Update driver model itself
                $driver->update([
                    'current_latitude' => $lat,
                    'current_longitude' => $lng,
                    'last_seen_at' => now(),
                    'last_online_at' => now(),
                ]);
            } else {
                // Update passenger location
                PassengerLocation::updateOrCreate(
                    ['user_id' => $user->id],
                    [
                        'trip_id' => $tripId,
                        'lat' => $lat,
                        'lng' => $lng,
                        'latitude' => $lat,
                        'longitude' => $lng,
                        'heading' => $heading,
                        'speed' => $speed,
                        'accuracy' => $accuracy,
                        'is_online' => true,
                        'recorded_at' => now(),
                    ]
                );
            }

            // 4. Update user model online status
            $user->update([
                'last_seen_at' => now(),
                'is_online' => true,
            ]);

            // 5. Append point to history log permanently only every 5 minutes
            $lastHistory = LocationHistory::where('user_id', $user->id)
                ->orderBy('created_at', 'desc')
                ->first();

            if (!$lastHistory || $lastHistory->created_at->lte(now()->subMinutes(5))) {
                LocationHistory::create([
                    'user_id' => $user->id,
                    'role' => $role,
                    'trip_id' => $tripId,
                    'latitude' => $lat,
                    'longitude' => $lng,
                    'speed' => $speed,
                    'heading' => $heading,
                    'created_at' => now(),
                ]);
            }
        });

        return response()->json([
            'success' => true,
            'message' => 'Location updated successfully.',
        ]);
    }

    #[OA\Get(
        path: '/v3/location/live/{userId}',
        summary: 'Get latest live location of driver or passenger',
        parameters: [
            new OA\Parameter(name: 'userId', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))
        ],
        responses: [
            new OA\Response(response: 200, description: 'Success')
        ]
    )]
    public function getLiveLocation(int $userId): JsonResponse
    {
        $targetUser = User::findOrFail($userId);
        $driver = Driver::where('user_id', $targetUser->id)->first();

        if ($driver) {
            $loc = DriverLocationV3::where('driver_id', $driver->id)->first();
            $data = $loc ? [
                'user_id' => $targetUser->id,
                'role' => 'driver',
                'latitude' => (float) $loc->latitude,
                'longitude' => (float) $loc->longitude,
                'heading' => $loc->heading ? (float) $loc->heading : null,
                'speed' => $loc->speed ? (float) $loc->speed : null,
                'updated_at' => $loc->updated_at?->toIso8601String(),
            ] : null;
        } else {
            $loc = PassengerLocation::where('user_id', $targetUser->id)->first();
            $data = $loc ? [
                'user_id' => $targetUser->id,
                'role' => 'passenger',
                'latitude' => (float) $loc->latitude,
                'longitude' => (float) $loc->longitude,
                'heading' => $loc->heading ? (float) $loc->heading : null,
                'speed' => $loc->speed ? (float) $loc->speed : null,
                'updated_at' => $loc->updated_at?->toIso8601String(),
            ] : null;
        }

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    #[OA\Get(
        path: '/v3/location/history/{userId}',
        summary: 'Get location history for driver or passenger',
        parameters: [
            new OA\Parameter(name: 'userId', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'limit', in: 'query', schema: new OA\Schema(type: 'integer', default: 100))
        ],
        responses: [
            new OA\Response(response: 200, description: 'Success')
        ]
    )]
    public function getLocationHistory(Request $request, int $userId): JsonResponse
    {
        $limit = $request->query('limit', 100);
        $limit = min(500, max(1, (int)$limit));

        $history = LocationHistory::where('user_id', $userId)
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();

        return response()->json([
            'success' => true,
            'data' => $history,
        ]);
    }
}
