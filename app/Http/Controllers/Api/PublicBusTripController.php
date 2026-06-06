<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\TripLifecycleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Public Bus Trip Request Controller
 *
 * Handles driver accept/reject decisions for trip requests with full lifecycle management.
 * Manages notifications, seat availability, and real-time updates.
 */
class PublicBusTripController extends Controller
{
    private TripLifecycleService $tripLifecycle;

    public function __construct(TripLifecycleService $tripLifecycle)
    {
        $this->tripLifecycle = $tripLifecycle;
    }

    /**
     * Driver accepts a trip request.
     *
     * POST /api/v1/driver/trip-requests/{id}/accept
     *
     * Handles:
     * - Trip status update to PASSENGER_WAITING
     * - Seat availability deduction for public buses
     * - Driver and passenger notifications
     * - Real-time event broadcasting
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
                'error_code' => 'DRIVER_NOT_FOUND',
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

            // Use trip lifecycle service
            $result = $this->tripLifecycle->acceptTrip($tripRequest, $driver->id);

            if (!$result['success']) {
                return response()->json($result, 422);
            }

            return response()->json($result, 200);
        } catch (\Exception $e) {
            Log::error('Unexpected error accepting trip', [
                'trip_id' => $id,
                'driver_id' => $driver->id ?? null,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'An unexpected error occurred',
                'error_code' => 'ACCEPT_ERROR',
            ], 500);
        }
    }

    /**
     * Driver rejects a trip request.
     *
     * POST /api/v1/driver/trip-requests/{id}/reject
     *
     * Handles:
     * - Trip status update to REJECTED_BY_DRIVER
     * - Passenger notification of rejection
     * - Automatic reassignment via ML service
     * - Real-time event broadcasting
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
                'error_code' => 'DRIVER_NOT_FOUND',
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
                    'message' => "Trip request {$id} not found.",
                    'error_code' => 'TRIP_NOT_FOUND',
                ], 404);
            }

            // Use trip lifecycle service
            $result = $this->tripLifecycle->rejectTrip(
                $tripRequest,
                $driver->id,
                $validated['reason'] ?? 'DRIVER_DECLINED'
            );

            if (!$result['success']) {
                return response()->json($result, 422);
            }

            return response()->json($result, 200);
        } catch (\Exception $e) {
            Log::error('Unexpected error rejecting trip', [
                'trip_id' => $id,
                'driver_id' => $driver->id ?? null,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'An unexpected error occurred',
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

