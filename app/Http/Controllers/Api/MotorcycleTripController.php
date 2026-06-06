<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Driver;
use App\Models\MotorcycleTrip;
use App\Models\User;
use App\Services\GeocodingService;
use App\Services\MatchingService;
use App\Services\MotorcycleTripService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class MotorcycleTripController extends Controller
{
    private MotorcycleTripService $tripService;
    private MatchingService $matchingService;
    private GeocodingService $geocodingService;

    public function __construct(
        MotorcycleTripService $tripService,
        MatchingService $matchingService,
        GeocodingService $geocodingService
    ) {
        $this->tripService = $tripService;
        $this->matchingService = $matchingService;
        $this->geocodingService = $geocodingService;
    }

    /**
     * POST /api/v1/passenger/motor-vehicle/trip-requests
     * Passenger creates a motorcycle trip request
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'pickup_location' => 'required|string|max:255',
                'dropoff_location' => 'required|string|max:255',
                'pickup_lat' => 'sometimes|numeric',
                'pickup_lng' => 'sometimes|numeric',
                'dropoff_lat' => 'sometimes|numeric',
                'dropoff_lng' => 'sometimes|numeric',
                'estimated_fare' => 'sometimes|numeric|min:0',
            ]);

            $passengerId = auth()->id();
            $pickupLat = $validated['pickup_lat'] ?? null;
            $pickupLng = $validated['pickup_lng'] ?? null;
            $dropoffLat = $validated['dropoff_lat'] ?? null;
            $dropoffLng = $validated['dropoff_lng'] ?? null;

            // Geocode if coordinates not provided
            if (!$pickupLat || !$pickupLng) {
                $pickupCoords = $this->geocodingService->geocode($validated['pickup_location']);
                if (!$pickupCoords) {
                    return response()->json([
                        'success' => false,
                        'error_code' => 'GEOCODING_FAILED',
                        'message' => 'Could not geocode pickup location',
                    ], 400);
                }
                $pickupLat = $pickupCoords['lat'];
                $pickupLng = $pickupCoords['lng'];
            }

            if (!$dropoffLat || !$dropoffLng) {
                $dropoffCoords = $this->geocodingService->geocode($validated['dropoff_location']);
                if (!$dropoffCoords) {
                    return response()->json([
                        'success' => false,
                        'error_code' => 'GEOCODING_FAILED',
                        'message' => 'Could not geocode dropoff location',
                    ], 400);
                }
                $dropoffLat = $dropoffCoords['lat'];
                $dropoffLng = $dropoffCoords['lng'];
            }

            // Estimate fare (placeholder - integrate with fare calculation service)
            $estimatedFare = $validated['estimated_fare'] ?? $this->estimateFare($pickupLat, $pickupLng, $dropoffLat, $dropoffLng);

            // Create trip
            $result = $this->tripService->createTrip(
                passengerId: $passengerId,
                pickupLocation: $validated['pickup_location'],
                dropoffLocation: $validated['dropoff_location'],
                pickupLat: $pickupLat,
                pickupLng: $pickupLng,
                dropoffLat: $dropoffLat,
                dropoffLng: $dropoffLng,
                estimatedFare: $estimatedFare
            );

            if (!$result['success']) {
                return response()->json($result, 500);
            }

            // Start matching immediately
            $trip = MotorcycleTrip::find($result['trip_id']);
            $matchResult = $this->tripService->startMatching($trip);

            if (!$matchResult['success']) {
                return response()->json([
                    'success' => true,
                    'trip_id' => $result['trip_id'],
                    'status' => $trip->fresh()->status,
                    'matching_status' => 'NO_DRIVERS_AVAILABLE',
                ], 202);
            }

            return response()->json([
                'success' => true,
                'trip_id' => $result['trip_id'],
                'status' => $trip->fresh()->status,
                'driver_id' => $matchResult['driver_id'] ?? null,
                'estimated_fare' => $estimatedFare,
            ], 201);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'error_code' => 'VALIDATION_ERROR',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('Error creating motorcycle trip', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json([
                'success' => false,
                'error_code' => 'INTERNAL_ERROR',
                'message' => 'Failed to create trip',
            ], 500);
        }
    }

    /**
     * POST /api/v1/driver/motor-vehicle/trip-requests/{id}/accept
     * Driver accepts a motorcycle trip
     */
    public function accept(Request $request, int $id): JsonResponse
    {
        try {
            $driverId = auth()->user()->driver?->id;
            if (!$driverId) {
                return response()->json([
                    'success' => false,
                    'error_code' => 'DRIVER_NOT_FOUND',
                    'message' => 'Driver profile not found',
                ], 404);
            }

            $trip = MotorcycleTrip::find($id);
            if (!$trip) {
                return response()->json([
                    'success' => false,
                    'error_code' => 'TRIP_NOT_FOUND',
                    'message' => 'Trip not found',
                ], 404);
            }

            $result = $this->tripService->acceptTrip($trip, $driverId);

            if (!$result['success']) {
                $statusCode = match ($result['error'] ?? 'UNKNOWN') {
                    'NOT_ASSIGNED_TO_DRIVER' => 403,
                    'INVALID_STATUS' => 409,
                    default => 500,
                };
                return response()->json($result, $statusCode);
            }

            return response()->json($result, 200);
        } catch (\Exception $e) {
            Log::error('Error accepting trip', [
                'trip_id' => $id,
                'driver_id' => auth()->user()->driver?->id,
                'error' => $e->getMessage(),
            ]);
            return response()->json([
                'success' => false,
                'error_code' => 'ACCEPT_ERROR',
                'message' => 'Failed to accept trip',
            ], 500);
        }
    }

    /**
     * POST /api/v1/driver/motor-vehicle/trip-requests/{id}/reject
     * Driver rejects a motorcycle trip
     */
    public function reject(Request $request, int $id): JsonResponse
    {
        try {
            $validated = $request->validate([
                'reason' => 'sometimes|string|max:500',
            ]);

            $driverId = auth()->user()->driver?->id;
            if (!$driverId) {
                return response()->json([
                    'success' => false,
                    'error_code' => 'DRIVER_NOT_FOUND',
                    'message' => 'Driver profile not found',
                ], 404);
            }

            $trip = MotorcycleTrip::find($id);
            if (!$trip) {
                return response()->json([
                    'success' => false,
                    'error_code' => 'TRIP_NOT_FOUND',
                    'message' => 'Trip not found',
                ], 404);
            }

            $result = $this->tripService->rejectTrip($trip, $driverId, $validated['reason'] ?? 'No reason provided');

            if (!$result['success']) {
                return response()->json($result, 500);
            }

            return response()->json($result, 200);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'error_code' => 'VALIDATION_ERROR',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('Error rejecting trip', [
                'trip_id' => $id,
                'driver_id' => auth()->user()->driver?->id,
                'error' => $e->getMessage(),
            ]);
            return response()->json([
                'success' => false,
                'error_code' => 'REJECT_ERROR',
                'message' => 'Failed to reject trip',
            ], 500);
        }
    }

    /**
     * POST /api/v1/driver/motor-vehicle/trip-requests/{id}/arrived
     * Driver marks as arrived at pickup location
     */
    public function arrived(Request $request, int $id): JsonResponse
    {
        try {
            $driverId = auth()->user()->driver?->id;
            if (!$driverId) {
                return response()->json([
                    'success' => false,
                    'error_code' => 'DRIVER_NOT_FOUND',
                    'message' => 'Driver profile not found',
                ], 404);
            }

            $trip = MotorcycleTrip::find($id);
            if (!$trip) {
                return response()->json([
                    'success' => false,
                    'error_code' => 'TRIP_NOT_FOUND',
                    'message' => 'Trip not found',
                ], 404);
            }

            $result = $this->tripService->driverArrived($trip, $driverId);

            if (!$result['success']) {
                return response()->json($result, 400);
            }

            return response()->json($result, 200);
        } catch (\Exception $e) {
            Log::error('Error marking driver arrived', [
                'trip_id' => $id,
                'driver_id' => auth()->user()->driver?->id,
                'error' => $e->getMessage(),
            ]);
            return response()->json([
                'success' => false,
                'error_code' => 'ARRIVED_ERROR',
                'message' => 'Failed to mark arrival',
            ], 500);
        }
    }

    /**
     * POST /api/v1/driver/motor-vehicle/trip-requests/{id}/start
     * Driver starts the trip
     */
    public function start(Request $request, int $id): JsonResponse
    {
        try {
            $driverId = auth()->user()->driver?->id;
            if (!$driverId) {
                return response()->json([
                    'success' => false,
                    'error_code' => 'DRIVER_NOT_FOUND',
                    'message' => 'Driver profile not found',
                ], 404);
            }

            $trip = MotorcycleTrip::find($id);
            if (!$trip) {
                return response()->json([
                    'success' => false,
                    'error_code' => 'TRIP_NOT_FOUND',
                    'message' => 'Trip not found',
                ], 404);
            }

            $result = $this->tripService->startTrip($trip, $driverId);

            if (!$result['success']) {
                return response()->json($result, 400);
            }

            return response()->json($result, 200);
        } catch (\Exception $e) {
            Log::error('Error starting trip', [
                'trip_id' => $id,
                'driver_id' => auth()->user()->driver?->id,
                'error' => $e->getMessage(),
            ]);
            return response()->json([
                'success' => false,
                'error_code' => 'START_ERROR',
                'message' => 'Failed to start trip',
            ], 500);
        }
    }

    /**
     * POST /api/v1/driver/motor-vehicle/trip-requests/{id}/complete
     * Driver completes the trip
     */
    public function complete(Request $request, int $id): JsonResponse
    {
        try {
            $validated = $request->validate([
                'actual_fare' => 'sometimes|numeric|min:0',
            ]);

            $driverId = auth()->user()->driver?->id;
            if (!$driverId) {
                return response()->json([
                    'success' => false,
                    'error_code' => 'DRIVER_NOT_FOUND',
                    'message' => 'Driver profile not found',
                ], 404);
            }

            $trip = MotorcycleTrip::find($id);
            if (!$trip) {
                return response()->json([
                    'success' => false,
                    'error_code' => 'TRIP_NOT_FOUND',
                    'message' => 'Trip not found',
                ], 404);
            }

            $result = $this->tripService->completeTrip($trip, $driverId, $validated['actual_fare'] ?? null);

            if (!$result['success']) {
                return response()->json($result, 400);
            }

            return response()->json($result, 200);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'error_code' => 'VALIDATION_ERROR',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('Error completing trip', [
                'trip_id' => $id,
                'driver_id' => auth()->user()->driver?->id,
                'error' => $e->getMessage(),
            ]);
            return response()->json([
                'success' => false,
                'error_code' => 'COMPLETE_ERROR',
                'message' => 'Failed to complete trip',
            ], 500);
        }
    }

    /**
     * POST /api/v1/passenger/motor-vehicle/trip-requests/{id}/cancel
     * Passenger cancels their trip
     */
    public function cancel(Request $request, int $id): JsonResponse
    {
        try {
            $validated = $request->validate([
                'reason' => 'sometimes|string|max:500',
            ]);

            $passengerId = auth()->id();

            $trip = MotorcycleTrip::find($id);
            if (!$trip) {
                return response()->json([
                    'success' => false,
                    'error_code' => 'TRIP_NOT_FOUND',
                    'message' => 'Trip not found',
                ], 404);
            }

            $result = $this->tripService->cancelByPassenger($trip, $passengerId, $validated['reason'] ?? null);

            if (!$result['success']) {
                $statusCode = match ($result['error'] ?? 'UNKNOWN') {
                    'NOT_PASSENGER' => 403,
                    'CANNOT_CANCEL' => 409,
                    default => 500,
                };
                return response()->json($result, $statusCode);
            }

            return response()->json($result, 200);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'error_code' => 'VALIDATION_ERROR',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('Error cancelling trip', [
                'trip_id' => $id,
                'passenger_id' => auth()->id(),
                'error' => $e->getMessage(),
            ]);
            return response()->json([
                'success' => false,
                'error_code' => 'CANCEL_ERROR',
                'message' => 'Failed to cancel trip',
            ], 500);
        }
    }

    /**
     * Estimate fare based on distance (placeholder)
     */
    private function estimateFare(float $pickupLat, float $pickupLng, float $dropoffLat, float $dropoffLng): float
    {
        // Simple distance-based calculation
        $baseFare = 1000; // RWF
        $perKmFare = 500; // RWF per km
        
        // Haversine formula for approximate distance
        $distance = $this->haversineDistance($pickupLat, $pickupLng, $dropoffLat, $dropoffLng);
        
        return round($baseFare + ($distance * $perKmFare), 0);
    }

    /**
     * Calculate distance between two coordinates using Haversine formula
     */
    private function haversineDistance(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $R = 6371; // Earth's radius in km
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);
        
        $a = sin($dLat / 2) * sin($dLat / 2) +
             cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
             sin($dLon / 2) * sin($dLon / 2);
        
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
        
        return $R * $c;
    }
}
