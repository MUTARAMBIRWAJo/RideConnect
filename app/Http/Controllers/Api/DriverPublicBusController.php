<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\DomainException;
use App\Http\Controllers\Controller;
use App\Models\PassengerRouteBoarding;
use App\Services\PublicBusTransportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DriverPublicBusController extends Controller
{
    public function __construct(private readonly PublicBusTransportService $busService) {}

    public function location(Request $request): JsonResponse
    {
        $driver = $request->user()->driver;
        if (! $driver) {
            return response()->json(['success' => false, 'message' => 'Driver profile not found'], 404);
        }

        $validated = $request->validate([
            'bus_route_assignment_id' => 'required|integer|exists:bus_route_assignments,id',
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'speed_kph' => 'nullable|numeric|min:0',
            'heading_degrees' => 'nullable|integer|min:0|max:360',
            'next_stop_id' => 'nullable|integer|exists:corridor_stops,id',
            'eta_minutes' => 'nullable|integer|min:0',
            'route_progress_percent' => 'nullable|numeric|min:0|max:100',
            'captured_at' => 'nullable|date',
        ]);

        $update = $this->busService->updateLocation((int) $validated['bus_route_assignment_id'], $validated);

        return response()->json(['success' => true, 'data' => $update]);
    }

    public function arrivedStop(Request $request): JsonResponse
    {
        $driver = $request->user()->driver;
        if (! $driver) {
            return response()->json(['success' => false, 'message' => 'Driver profile not found'], 404);
        }

        $validated = $request->validate([
            'bus_route_assignment_id' => 'required|integer|exists:bus_route_assignments,id',
            'corridor_stop_id' => 'required|integer|exists:corridor_stops,id',
            'trip_id' => 'nullable|integer|exists:trips,id',
            'metadata' => 'nullable|array',
        ]);

        try {
            $arrival = $this->busService->markArrivedStop(
                (int) $validated['bus_route_assignment_id'],
                (int) $validated['corridor_stop_id'],
                $validated['trip_id'] ?? null,
                $validated['metadata'] ?? []
            );
        } catch (DomainException $exception) {
            return response()->json([
                'success' => false,
                'message' => $exception->getMessage(),
                'error_code' => $exception->getErrorCode(),
            ], 422);
        }

        return response()->json(['success' => true, 'data' => $arrival]);
    }

    public function passengerBoarded(Request $request): JsonResponse
    {
        $driver = $request->user()->driver;
        if (! $driver) {
            return response()->json(['success' => false, 'message' => 'Driver profile not found'], 404);
        }

        $validated = $request->validate([
            'passenger_route_boarding_id' => 'required|integer|exists:passenger_route_boardings,id',
            'metadata' => 'nullable|array',
        ]);

        try {
            $event = $this->busService->markPassengerBoarded(
                (int) $validated['passenger_route_boarding_id'],
                (int) $driver->id,
                $validated['metadata'] ?? []
            );
        } catch (DomainException $exception) {
            return response()->json([
                'success' => false,
                'message' => $exception->getMessage(),
                'error_code' => $exception->getErrorCode(),
            ], 422);
        }

        return response()->json(['success' => true, 'data' => $event]);
    }

    public function passengerCompleted(Request $request): JsonResponse
    {
        $driver = $request->user()->driver;
        if (! $driver) {
            return response()->json(['success' => false, 'message' => 'Driver profile not found'], 404);
        }

        $validated = $request->validate([
            'passenger_route_boarding_id' => 'required|integer|exists:passenger_route_boardings,id',
            'metadata' => 'nullable|array',
        ]);

        try {
            $boarding = $this->busService->markPassengerCompleted(
                (int) $validated['passenger_route_boarding_id'],
                (int) $driver->id,
                $validated['metadata'] ?? []
            );
        } catch (DomainException $exception) {
            return response()->json([
                'success' => false,
                'message' => $exception->getMessage(),
                'error_code' => $exception->getErrorCode(),
            ], 422);
        }

        return response()->json(['success' => true, 'data' => $boarding]);
    }

    /**
     * Driver accepts a pending trip request.
     *
     * POST /api/v1/driver/public-bus/trip-requests/{trip_request_id}/accept
     *
     * @param  Request  $request
     * @param  int  $tripRequestId
     * @return JsonResponse
     */
    public function acceptTripRequest(Request $request, int $tripRequestId): JsonResponse
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
            $tripRequest = \App\Models\TripRequest::findOrFail($tripRequestId);

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
            \Illuminate\Support\Facades\Log::error('Error accepting trip request', [
                'trip_request_id' => $tripRequestId,
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
     * Driver rejects a pending trip request.
     *
     * POST /api/v1/driver/public-bus/trip-requests/{trip_request_id}/reject
     *
     * @param  Request  $request
     * @param  int  $tripRequestId
     * @return JsonResponse
     */
    public function rejectTripRequest(Request $request, int $tripRequestId): JsonResponse
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
            $tripRequest = \App\Models\TripRequest::findOrFail($tripRequestId);

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
            \Illuminate\Support\Facades\Log::info('Driver rejected trip request', [
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
            \Illuminate\Support\Facades\Log::error('Error rejecting trip request', [
                'trip_request_id' => $tripRequestId,
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
}
