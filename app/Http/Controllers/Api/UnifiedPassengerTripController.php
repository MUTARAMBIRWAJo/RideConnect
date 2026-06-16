<?php

namespace App\Http\Controllers\Api;

use App\Http\Concerns\ResolvesCanonicalIdentity;
use App\Http\Controllers\Controller;
use App\Models\MotorcycleTrip;
use App\Models\Trip;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class UnifiedPassengerTripController extends Controller
{
    use ResolvesCanonicalIdentity;

    /**
     * Standardized success response envelope
     */
    protected function respondSuccess($data = [], string $message = 'Success', int $code = 200): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $data,
            'meta' => [
                'timestamp' => now()->toIso8601String(),
                'version' => 'v1',
            ],
        ], $code);
    }

    /**
     * Standardized error response envelope
     */
    protected function respondError(string $message = 'Error', int $code = 400, string $errorCode = 'ERROR'): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'error_code' => $errorCode,
            'data' => null,
            'meta' => [
                'timestamp' => now()->toIso8601String(),
                'version' => 'v1',
            ],
        ], $code);
    }

    /**
     * Standardize a delegate controller response
     */
    protected function standardize(JsonResponse $response): JsonResponse
    {
        $data = $response->getData(true);
        $code = $response->getStatusCode();
        
        $success = $response->isSuccessful();
        if (isset($data['success'])) {
            $success = (bool) $data['success'];
        } elseif (isset($data['status']) && $data['status'] === 'error') {
            $success = false;
        }
        
        if ($success) {
            $payload = $data['data'] ?? $data;
            // Clean up wrapping fields
            if (is_array($payload)) {
                unset($payload['success'], $payload['message'], $payload['status'], $payload['code']);
            }
            return $this->respondSuccess($payload, $data['message'] ?? 'Success', $code);
        } else {
            return $this->respondError($data['message'] ?? 'Error', $code, $data['error_code'] ?? 'ERROR');
        }
    }

    /**
     * Normalize type parameter to standard vehicle types
     */
    protected function normalizeType(string $type): string
    {
        $type = strtolower(trim($type));
        if (in_array($type, ['motor-vehicle', 'motor_vehicle', 'motorcycle', 'moto'], true)) {
            return 'motorcycle';
        }
        if (in_array($type, ['private-vehicle', 'private_vehicle', 'car', 'private_car'], true)) {
            return 'car';
        }
        if (in_array($type, ['public-bus', 'public_bus', 'bus'], true)) {
            return 'bus';
        }
        return $type;
    }

    /**
     * GET /passenger/{type}/trip-requests/{id}
     */
    public function show(Request $request, string $type, int $id): JsonResponse
    {
        $typeNormalized = $this->normalizeType($type);
        $passengerIds = $this->passengerOwnerIdsForQuery($request->user());

        // Cache active trips in Redis for 60 seconds (active state read-side efficiency)
        $cacheKey = "active_trip_{$id}";
        $tripData = Cache::remember($cacheKey, 60, function () use ($id, $typeNormalized) {
            if ($typeNormalized === 'motorcycle') {
                return MotorcycleTrip::with('driver.user')->find($id);
            } else {
                return Trip::with('driver.user')->find($id);
            }
        });

        if (!$tripData) {
            return $this->respondError('Trip request not found', 404, 'TRIP_NOT_FOUND');
        }

        // Verify ownership
        if (!in_array((int) $tripData->passenger_id, $passengerIds, true)) {
            return $this->respondError('Unauthorized to view this trip', 403, 'FORBIDDEN');
        }

        // Poll check status change
        $clientStatus = $request->query('last_status') ?? $request->query('status');
        $statusChanged = true;
        if ($clientStatus !== null) {
            $statusChanged = strtoupper(trim($clientStatus)) !== strtoupper(trim($tripData->status));
        }

        // Standardize output payload
        $driver = $tripData->driver;
        $driverBlock = $driver ? [
            'id' => $driver->id,
            'name' => $driver->user?->name,
            'phone' => $driver->user?->phone,
            'rating' => $driver->rating,
            'vehicle_plate' => $driver->motorcycle_plate ?? $driver->license_plate,
        ] : null;

        $payload = [
            'trip_id' => $tripData->id,
            'status' => $tripData->status,
            'status_changed' => $statusChanged,
            'last_updated_at' => $tripData->updated_at?->toIso8601String(),
            'pickup_location' => $tripData->pickup_location,
            'dropoff_location' => $tripData->dropoff_location,
            'pickup_lat' => (float) $tripData->pickup_lat,
            'pickup_lng' => (float) $tripData->pickup_lng,
            'dropoff_lat' => (float) $tripData->dropoff_lat,
            'dropoff_lng' => (float) $tripData->dropoff_lng,
            'estimated_fare' => (float) ($tripData->estimated_fare ?? $tripData->fare ?? 0),
            'actual_fare' => $tripData->actual_fare ? (float) $tripData->actual_fare : null,
            'driver' => $driverBlock,
            'transport_type' => $typeNormalized,
        ];

        return $this->respondSuccess($payload, 'Trip retrieved successfully');
    }

    /**
     * GET /passenger/{type}/trip-history
     */
    public function history(Request $request, string $type): JsonResponse
    {
        $typeNormalized = $this->normalizeType($type);
        $passengerIds = $this->passengerOwnerIdsForQuery($request->user());
        $perPage = $request->query('per_page', 15);

        if ($typeNormalized === 'motorcycle') {
            $query = MotorcycleTrip::query()
                ->whereIn('passenger_id', $passengerIds)
                ->whereIn('status', ['COMPLETED', 'CANCELLED', 'CANCELLED_BY_PASSENGER', 'CANCELLED_BY_DRIVER', 'FAILED']);
        } else {
            $query = Trip::query()
                ->whereIn('passenger_id', $passengerIds)
                ->where('transport_type', $typeNormalized)
                ->whereIn('status', ['COMPLETED', 'CANCELLED', 'FAILED']);
        }

        $trips = $query->orderBy('created_at', 'desc')->paginate($perPage);

        return $this->respondSuccess($trips, 'Trip history retrieved successfully');
    }

    /**
     * POST /passenger/{type}/trip-request
     */
    public function store(Request $request, string $type): JsonResponse
    {
        $activeTrip = \App\Models\Trip::where('passenger_id', $request->user()->id)
            ->whereIn('status', ['requested', 'assigning', 'assigned', 'accepted', 'started', 'REQUESTED', 'MATCHING', 'ASSIGNED', 'ACCEPTED', 'STARTED'])
            ->first()
            ?? \App\Models\MotorcycleTrip::where('passenger_id', $request->user()->id)
            ->whereIn('status', ['REQUESTED', 'MATCHING', 'DRIVER_FOUND', 'ASSIGNED', 'ACCEPTED', 'ARRIVED', 'STARTED', 'DRIVER_ASSIGNED', 'PASSENGER_WAITING', 'IN_PROGRESS'])
            ->first();

        if ($activeTrip) {
            return response()->json([
                'success' => false,
                'error_code' => 'ACTIVE_TRIP_EXISTS',
                'message' => 'There is another trip in progress.',
                'data' => [
                    'trip_id' => $activeTrip->id,
                    'status' => $activeTrip->status,
                    'can_cancel' => in_array(strtoupper((string) $activeTrip->status), ['REQUESTED', 'MATCHING', 'ASSIGNED', 'ASSIGNING', 'DRIVER_FOUND', 'DRIVER_ASSIGNED', 'PASSENGER_WAITING']),
                ]
            ], 409);
        }

        $typeNormalized = $this->normalizeType($type);
        
        if ($typeNormalized === 'motorcycle') {
            $controller = app(MotorcycleTripController::class);
            $response = $controller->store($request);
        } else {
            $request->merge(['transport_type' => $typeNormalized]);
            $controller = app(TripController::class);
            $response = $controller->store($request);
        }

        return $this->standardize($response);
    }

    /**
     * POST /passenger/{type}/trip-cancel
     */
    public function cancel(Request $request, string $type): JsonResponse
    {
        $typeNormalized = $this->normalizeType($type);
        $passengerIds = $this->passengerOwnerIdsForQuery($request->user());
        $tripId = $request->input('trip_id');

        if (!$tripId) {
            // Find active trip for passenger
            if ($typeNormalized === 'motorcycle') {
                $active = MotorcycleTrip::query()
                    ->whereIn('passenger_id', $passengerIds)
                    ->whereIn('status', ['REQUESTED', 'MATCHING', 'DRIVER_FOUND', 'ASSIGNED', 'ACCEPTED', 'ARRIVED', 'STARTED', 'DRIVER_ASSIGNED', 'PASSENGER_WAITING', 'IN_PROGRESS'])
                    ->latest()
                    ->first();
            } else {
                $active = Trip::query()
                    ->whereIn('passenger_id', $passengerIds)
                    ->where('transport_type', $typeNormalized)
                    ->whereIn('status', ['REQUESTED', 'MATCHING', 'DRIVER_FOUND', 'ASSIGNED', 'ACCEPTED', 'ARRIVED', 'STARTED'])
                    ->latest()
                    ->first();
            }

            if (!$active) {
                return $this->respondError('No active trip request found to cancel', 404, 'ACTIVE_TRIP_NOT_FOUND');
            }

            $tripId = $active->id;
        }

        // Invalidate active trip cache
        Cache::forget("active_trip_{$tripId}");

        if ($typeNormalized === 'motorcycle') {
            $controller = app(MotorcycleTripController::class);
            $response = $controller->cancel($request, $tripId);
        } else {
            $controller = app(TripController::class);
            $response = $controller->cancel($tripId);
        }

        return $this->standardize($response);
    }
}
