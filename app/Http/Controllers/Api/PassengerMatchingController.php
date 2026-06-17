<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Trip;
use App\Services\DriverMatchingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * PassengerMatchingController
 *
 * Handles passenger-initiated driver matching requests via trip ID.
 * This is distinct from the generic DriverMatchingController (which takes coordinates).
 * This controller triggers matching for a specific trip that already exists.
 */
class PassengerMatchingController extends Controller
{
    public function __construct(
        private readonly DriverMatchingService $driverMatchingService
    ) {}

    /**
     * Trigger driver matching for a specific trip.
     *
     * POST /matching/driver/{tripId}
     *
     * @param Request $request
     * @param int|string $tripId
     * @return JsonResponse
     */
    public function match(Request $request, int|string $tripId): JsonResponse
    {
        try {
            $trip = Trip::find((int) $tripId);

            if (! $trip) {
                return response()->json([
                    'success' => false,
                    'message' => 'Trip not found',
                    'error_code' => 'TRIP_NOT_FOUND',
                ], 404);
            }

            // Ensure the passenger owns this trip (if authenticated)
            if ($request->user()) {
                $userId = $request->user()->id;
                $mobileUserId = $request->user()->mobile_user_id;
                $passengerIds = array_filter([$userId, $mobileUserId]);

                if (! in_array((int) $trip->passenger_id, $passengerIds, true)) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Unauthorized to match drivers for this trip',
                        'error_code' => 'UNAUTHORIZED',
                    ], 403);
                }
            }

            // Check trip status is appropriate for matching
            $matchableStatuses = ['pending', 'searching'];
            if (! in_array($trip->status, $matchableStatuses, true)) {
                return response()->json([
                    'success' => false,
                    'message' => "Trip is not in a matchable state. Current status: {$trip->status}",
                    'error_code' => 'INVALID_TRIP_STATUS',
                    'data' => [
                        'trip_id' => $trip->id,
                        'status' => $trip->status,
                    ],
                ], 422);
            }

            // Build matching parameters from trip data
            $matchingPayload = [
                'transport_type' => $trip->transport_type ?? 'motor_vehicle',
                'pickup_lat' => (float) ($trip->pickup_lat ?? $trip->pickup_latitude ?? 0),
                'pickup_lng' => (float) ($trip->pickup_lng ?? $trip->pickup_longitude ?? 0),
                'dropoff_lat' => (float) ($trip->dropoff_lat ?? $trip->dropoff_latitude ?? 0),
                'dropoff_lng' => (float) ($trip->dropoff_lng ?? $trip->dropoff_longitude ?? 0),
                'limit' => $request->input('limit', 10),
            ];

            // Include any excluded driver IDs from request
            if ($request->has('excluded_driver_ids')) {
                $matchingPayload['excluded_driver_ids'] = $request->input('excluded_driver_ids');
            }

            $passengerId = $request->user()?->mobile_user_id ?? $request->user()?->id ?? (int) $trip->passenger_id;

            $data = $this->driverMatchingService->match($matchingPayload, (int) $passengerId);

            return response()->json([
                'success' => true,
                'message' => 'Drivers matched successfully',
                'data' => array_merge($data, [
                    'trip_id' => $trip->id,
                ]),
            ]);

        } catch (\Throwable $e) {
            Log::error('PassengerMatchingController@match failed', [
                'trip_id' => $tripId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $body = [
                'success' => false,
                'message' => 'Driver matching failed. Please try again.',
                'error_code' => 'MATCHING_FAILURE',
            ];

            if (config('app.debug')) {
                $body['debug'] = ['exception' => $e->getMessage()];
            }

            return response()->json($body, 500);
        }
    }
}
