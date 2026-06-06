<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Public Bus Trip Request Controller
 *
 * Handles driver accept/reject decisions for trip requests
 */
class PublicBusTripController extends Controller
{
    /**
     * Driver accepts a trip request.
     *
     * POST /api/v1/driver/trip-requests/{id}/accept
     *
     * @param  Request  $request
     * @param  int  $id
     * @return JsonResponse
     */
    public function accept(Request $request, int $id): JsonResponse
    {
        $driver = $request->user()->driver;
        if (!$driver) {
            return response()->json([
                'success' => false,
                'message' => 'Driver profile not found',
            ], 404);
        }

        try {
            // Fetch trip request
            $tripRequest = \App\Models\TripRequest::find($id);
            
            if (!$tripRequest) {
                return response()->json([
                    'success' => false,
                    'message' => "Trip request {$id} not found. Please check your assigned trips.",
                    'error_code' => 'TRIP_NOT_FOUND',
                ], 404);
            }

            // Validate trip is pending
            if ($tripRequest->status !== 'PENDING_MATCH') {
                return response()->json([
                    'success' => false,
                    'message' => "Trip request is not pending (current status: {$tripRequest->status})",
                    'error_code' => 'TRIP_NOT_PENDING',
                ], 422);
            }

            // Validate driver owns the matched vehicle
            if ($tripRequest->matched_vehicle_id && !$driver->vehicles()->where('id', $tripRequest->matched_vehicle_id)->exists()) {
                return response()->json([
                    'success' => false,
                    'message' => 'You are not authorized to accept this trip (vehicle mismatch)',
                    'error_code' => 'UNAUTHORIZED_VEHICLE',
                ], 403);
            }

            // Transition status using safe transition service
            $transitionService = app(\App\Services\TripStatusTransitionService::class);
            
            if (!$transitionService->canBeAccepted($tripRequest)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Trip cannot be accepted in its current state',
                    'error_code' => 'CANNOT_ACCEPT_TRIP',
                ], 422);
            }

            // Update trip request
            $transitionService->transition($tripRequest, 'PASSENGER_WAITING');
            $tripRequest->refresh();

            // Dispatch event
            event(new \App\Events\Domain\DriverAcceptedTrip(
                $tripRequest,
                $driver->id,
                'Driver accepted trip request'
            ));

            // Return response
            return response()->json([
                'success' => true,
                'message' => 'Trip request accepted successfully',
                'data' => [
                    'trip_request_id' => $tripRequest->id,
                    'status' => $tripRequest->status,
                    'passenger' => [
                        'id' => $tripRequest->passenger_id,
                        'pickup_location' => $tripRequest->pickup_location,
                        'pickup_lat' => (float) $tripRequest->pickup_lat,
                        'pickup_lng' => (float) $tripRequest->pickup_lng,
                        'dropoff_location' => $tripRequest->dropoff_location,
                        'dropoff_lat' => (float) $tripRequest->dropoff_lat,
                        'dropoff_lng' => (float) $tripRequest->dropoff_lng,
                    ],
                    'bus' => [
                        'vehicle_id' => $tripRequest->matched_vehicle_id,
                        'driver_id' => $tripRequest->matched_driver_id,
                    ],
                    'route' => [
                        'distance_km' => (float) $tripRequest->trip_distance_km,
                        'duration_minutes' => $tripRequest->trip_duration_minutes,
                    ],
                    'fare' => [
                        'amount' => (float) $tripRequest->estimated_fare,
                        'currency' => $tripRequest->currency,
                    ],
                ],
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error accepting trip request', [
                'trip_request_id' => $id,
                'driver_id' => $driver->id ?? null,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'An error occurred while accepting the trip',
                'error_code' => 'ACCEPT_ERROR',
            ], 500);
        }
    }

    /**
     * Driver rejects a trip request.
     *
     * POST /api/v1/driver/trip-requests/{id}/reject
     *
     * @param  Request  $request
     * @param  int  $id
     * @return JsonResponse
     */
    public function reject(Request $request, int $id): JsonResponse
    {
        $driver = $request->user()->driver;
        if (!$driver) {
            return response()->json([
                'success' => false,
                'message' => 'Driver profile not found',
            ], 404);
        }

        try {
            $validated = $request->validate([
                'reason' => 'nullable|string|max:255',
                'notes' => 'nullable|string|max:500',
            ]);

            // Fetch trip request
            $tripRequest = \App\Models\TripRequest::find($id);
            
            if (!$tripRequest) {
                return response()->json([
                    'success' => false,
                    'message' => "Trip request {$id} not found. Please check your assigned trips.",
                    'error_code' => 'TRIP_NOT_FOUND',
                ], 404);
            }

            // Validate driver is assigned to this trip
            if ($tripRequest->matched_driver_id && $tripRequest->matched_driver_id !== $driver->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'You are not authorized to reject this trip (driver mismatch)',
                    'error_code' => 'UNAUTHORIZED_DRIVER',
                ], 403);
            }

            // Check if trip can be rejected (not IN_TRANSIT or COMPLETED)
            $transitionService = app(\App\Services\TripStatusTransitionService::class);
            
            if (!$transitionService->canBeRejected($tripRequest)) {
                return response()->json([
                    'success' => false,
                    'message' => "Cannot reject trip in {$tripRequest->status} status",
                    'error_code' => 'CANNOT_REJECT_TRIP',
                ], 422);
            }

            // Update trip request to CANCELLED
            $transitionService->transition($tripRequest, 'CANCELLED');
            $tripRequest->refresh();

            // Dispatch event
            event(new \App\Events\Domain\DriverRejectedTrip(
                $tripRequest,
                $driver->id,
                $validated['reason'] ?? 'DRIVER_DECLINED',
                $validated['notes'] ?? null
            ));

            // Log rejection for analytics
            Log::info('Driver rejected trip request', [
                'trip_request_id' => $tripRequest->id,
                'driver_id' => $driver->id,
                'reason' => $validated['reason'] ?? null,
                'passenger_id' => $tripRequest->passenger_id,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Trip request rejected successfully. Re-matching initiated.',
                'data' => [
                    'trip_request_id' => $tripRequest->id,
                    'status' => $tripRequest->status,
                    'reason' => $validated['reason'] ?? 'DRIVER_DECLINED',
                    'next_action' => 're-matching',
                ],
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error rejecting trip request', [
                'trip_request_id' => $id,
                'driver_id' => $driver->id ?? null,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'An error occurred while rejecting the trip',
                'error_code' => 'REJECT_ERROR',
            ], 500);
        }
    }

    /**
     * Get all trips assigned to the current driver.
     *
     * GET /api/v1/driver/trip-requests/assigned
     *
     * @param  Request  $request
     * @return JsonResponse
     */
    public function getAssigned(Request $request): JsonResponse
    {
        $driver = $request->user()->driver;
        if (!$driver) {
            return response()->json([
                'success' => false,
                'message' => 'Driver profile not found',
            ], 404);
        }

        try {
            // Get all trips assigned to this driver, ordered by most recent pending first
            $trips = \App\Models\TripRequest::where('matched_driver_id', $driver->id)
                ->orderByRaw("CASE WHEN status = 'PENDING_MATCH' THEN 0 ELSE 1 END")
                ->orderBy('created_at', 'desc')
                ->get([
                    'id',
                    'status',
                    'pickup_location',
                    'pickup_lat',
                    'pickup_lng',
                    'dropoff_location',
                    'dropoff_lat',
                    'dropoff_lng',
                    'estimated_fare',
                    'trip_distance_km',
                    'trip_duration_minutes',
                    'created_at',
                ])
                ->map(function ($trip) {
                    return [
                        'id' => $trip->id,
                        'status' => $trip->status,
                        'pickup_location' => $trip->pickup_location,
                        'pickup_lat' => (float) $trip->pickup_lat,
                        'pickup_lng' => (float) $trip->pickup_lng,
                        'dropoff_location' => $trip->dropoff_location,
                        'dropoff_lat' => (float) $trip->dropoff_lat,
                        'dropoff_lng' => (float) $trip->dropoff_lng,
                        'fare' => (float) $trip->estimated_fare,
                        'distance_km' => (float) $trip->trip_distance_km,
                        'duration_minutes' => $trip->trip_duration_minutes,
                        'created_at' => $trip->created_at->toIso8601String(),
                    ];
                });

            $pending = $trips->where('status', 'PENDING_MATCH')->count();
            $active = $trips->where('status', 'PASSENGER_WAITING')->count() + 
                     $trips->where('status', 'PASSENGER_BOARDED')->count();
            $completed = $trips->where('status', 'COMPLETED')->count();

            return response()->json([
                'success' => true,
                'message' => 'Driver trip assignments retrieved successfully',
                'data' => [
                    'total' => $trips->count(),
                    'pending' => $pending,
                    'active' => $active,
                    'completed' => $completed,
                    'trips' => $trips->values(),
                ],
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error retrieving driver trip assignments', [
                'driver_id' => $driver->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'An error occurred while retrieving trip assignments',
                'error_code' => 'FETCH_ERROR',
            ], 500);
        }
    }
}

