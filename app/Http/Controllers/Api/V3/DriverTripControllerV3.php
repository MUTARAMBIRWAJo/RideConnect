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
    private TripLifecycleEngineV3 $lifecycle;
    private NotificationServiceV3 $notificationService;

    public function __construct(TripLifecycleEngineV3 $lifecycle, NotificationServiceV3 $notificationService)
    {
        $this->lifecycle = $lifecycle;
        $this->notificationService = $notificationService;
    }

    public function accept(Request $request, string $id): JsonResponse
    {
        $driver = $request->user();

        return DB::transaction(function () use ($id, $driver) {
            $trip = TripV3::where('id', $id)->lockForUpdate()->firstOrFail();

            if ($trip->status !== 'DRIVER_OFFERED' || $trip->matched_driver_id !== $driver->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Trip is no longer available or not assigned to you.',
                ], 400);
            }

            $trip->driver_id = $driver->id;
            $trip->driver_response_status = 'accepted';
            
            $this->lifecycle->transition($trip, 'ASSIGNED');

            $this->notificationService->sendToPassenger($trip->user_id, [
                'type' => 'TRIP_ACCEPTED',
                'driver_name' => $driver->name,
                'eta' => 5,
                'message' => 'Your driver has accepted your trip.',
            ]);

            return response()->json([
                'success' => true,
                'data' => $trip,
            ]);
        });
    }

    public function reject(Request $request, string $id): JsonResponse
    {
        $driver = $request->user();

        return DB::transaction(function () use ($id, $driver) {
            $trip = TripV3::where('id', $id)->lockForUpdate()->firstOrFail();

            if ($trip->status !== 'DRIVER_OFFERED' || $trip->matched_driver_id !== $driver->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Trip is no longer available or not assigned to you.',
                ], 400);
            }

            $trip->matched_driver_id = null;
            $trip->driver_response_status = 'rejected';

            $ignored = $trip->ignored_driver_ids ?? [];
            if (!in_array($driver->id, $ignored)) {
                $ignored[] = $driver->id;
            }
            $trip->ignored_driver_ids = $ignored;

            $this->lifecycle->transition($trip, 'SEARCHING');

            // Dispatch matching for the next driver
            ProcessTripMatchingV3::dispatch($trip);

            return response()->json([
                'success' => true,
                'message' => 'Trip rejected successfully.',
            ]);
        });
    }
}
