<?php

namespace App\Http\Controllers\Api\V3;

use App\Http\Controllers\Controller;
use App\Jobs\V3\ProcessTripMatchingV3;
use App\Models\V3\TripV3;
use App\Services\V3\NotificationServiceV3;
use App\Services\V3\TripLifecycleEngineV3;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DriverTripControllerV3 extends Controller
{
    public function accept(Request $request, string $id): JsonResponse
    {
        // Assuming $request->user() returns a User model that has a driver relationship, 
        // or returns a Driver directly based on auth setup.
        $driverId = $request->user()->driver->id ?? $request->user()->id;

        return DB::transaction(function () use ($id, $driverId) {
            $trip = TripV3::where('id', $id)->lockForUpdate()->firstOrFail();

            if ($trip->status !== 'AWAITING_DRIVER_RESPONSE' || $trip->driver_id !== $driverId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Trip is no longer available or not assigned to you.',
                ], 400);
            }

            $trip->status = 'ACCEPTED';
            $trip->save();

            // Insert into trip_events_v3 to trigger Realtime broadcast
            DB::table('trip_events_v3')->insert([
                'id' => (string) \Illuminate\Support\Str::uuid(),
                'trip_id' => $trip->id,
                'event_type' => 'TRIP_ACCEPTED',
                'payload' => json_encode(['driver_id' => $driverId]),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Update active_trips_v3 status
            DB::table('active_trips_v3')
                ->where('trip_id', $trip->id)
                ->update(['status' => 'ACCEPTED', 'updated_at' => now()]);

            return response()->json([
                'success' => true,
                'data' => $trip,
            ]);
        });
    }

    public function reject(Request $request, string $id): JsonResponse
    {
        $driverId = $request->user()->driver->id ?? $request->user()->id;

        return DB::transaction(function () use ($id, $driverId) {
            $trip = TripV3::where('id', $id)->lockForUpdate()->firstOrFail();

            if ($trip->status !== 'AWAITING_DRIVER_RESPONSE' || $trip->driver_id !== $driverId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Trip is no longer available or not assigned to you.',
                ], 400);
            }

            $trip->status = 'REJECTED';
            // Unassign driver so matching engine can re-assign
            $trip->driver_id = null;
            $trip->save();

            DB::table('trip_events_v3')->insert([
                'id' => (string) \Illuminate\Support\Str::uuid(),
                'trip_id' => $trip->id,
                'event_type' => 'TRIP_REJECTED',
                'payload' => json_encode(['driver_id' => $driverId]),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Typically here you'd dispatch a job to rematch or call the matching engine immediately
            // app(\App\Services\V3\DriverMatchingEngineV3::class)->findAndNotifyNearbyDrivers($trip);

            return response()->json([
                'success' => true,
                'message' => 'Trip rejected successfully. Re-matching...',
            ]);
        });
    }
}
