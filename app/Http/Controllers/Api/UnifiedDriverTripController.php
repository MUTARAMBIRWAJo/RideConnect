<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MotorcycleTrip;
use App\Models\Trip;
use App\Models\TripStatusEvent;
use App\Models\RideEvent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class UnifiedDriverTripController extends Controller
{
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
            if (is_array($payload)) {
                unset($payload['success'], $payload['message'], $payload['status'], $payload['code']);
            }
            return $this->respondSuccess($payload, $data['message'] ?? 'Success', $code);
        } else {
            return $this->respondError($data['message'] ?? 'Error', $code, $data['error_code'] ?? 'ERROR');
        }
    }

    /**
     * GET /driver/trips/active
     */
    public function active(Request $request): JsonResponse
    {
        $driver = $request->user()->driver;
        if (!$driver) {
            return $this->respondError('Driver profile not found', 404, 'DRIVER_NOT_FOUND');
        }

        $activeStatuses = ['DRIVER_FOUND', 'ASSIGNED', 'ACCEPTED', 'ARRIVED', 'STARTED', 'DRIVER_ASSIGNED', 'PASSENGER_WAITING', 'IN_PROGRESS'];

        // Search motorcycle active trip
        $motoTrip = MotorcycleTrip::query()
            ->where('driver_id', $driver->id)
            ->whereIn('status', $activeStatuses)
            ->first();

        if ($motoTrip) {
            return $this->respondSuccess([
                'trip_id' => $motoTrip->id,
                'status' => $motoTrip->status,
                'pickup_location' => $motoTrip->pickup_location,
                'dropoff_location' => $motoTrip->dropoff_location,
                'pickup_lat' => (float) $motoTrip->pickup_lat,
                'pickup_lng' => (float) $motoTrip->pickup_lng,
                'dropoff_lat' => (float) $motoTrip->dropoff_lat,
                'dropoff_lng' => (float) $motoTrip->dropoff_lng,
                'estimated_fare' => (float) $motoTrip->estimated_fare,
                'actual_fare' => $motoTrip->actual_fare ? (float) $motoTrip->actual_fare : null,
                'transport_type' => 'motorcycle',
                'passenger_id' => $motoTrip->passenger_id,
            ], 'Active trip retrieved');
        }

        // Search standard active trip
        $standardTrip = Trip::query()
            ->where('driver_id', $driver->id)
            ->whereIn('status', $activeStatuses)
            ->first();

        if ($standardTrip) {
            return $this->respondSuccess([
                'trip_id' => $standardTrip->id,
                'status' => $standardTrip->status,
                'pickup_location' => $standardTrip->pickup_location,
                'dropoff_location' => $standardTrip->dropoff_location,
                'pickup_lat' => (float) $standardTrip->pickup_lat,
                'pickup_lng' => (float) $standardTrip->pickup_lng,
                'dropoff_lat' => (float) $standardTrip->dropoff_lat,
                'dropoff_lng' => (float) $standardTrip->dropoff_lng,
                'estimated_fare' => (float) $standardTrip->fare,
                'actual_fare' => $standardTrip->actual_fare ? (float) $standardTrip->actual_fare : null,
                'transport_type' => $standardTrip->transport_type,
                'passenger_id' => $standardTrip->passenger_id,
            ], 'Active trip retrieved');
        }

        return $this->respondSuccess(null, 'No active trip found');
    }

    /**
     * POST /driver/trips/{id}/accept
     */
    public function accept(Request $request, int $id): JsonResponse
    {
        $motoTrip = MotorcycleTrip::find($id);
        if ($motoTrip) {
            // Invalidate cache
            Cache::forget("active_trip_{$id}");
            $controller = app(MotorcycleTripController::class);
            $response = $controller->accept($request, $id);
            return $this->standardize($response);
        }

        $standardTrip = Trip::find($id);
        if ($standardTrip) {
            // Invalidate cache
            Cache::forget("active_trip_{$id}");
            $controller = app(TripController::class);
            $response = $controller->accept($request, $id);
            return $this->standardize($response);
        }

        return $this->respondError('Trip not found', 404, 'TRIP_NOT_FOUND');
    }

    /**
     * POST /driver/trips/{id}/arrived
     */
    public function arrived(Request $request, int $id): JsonResponse
    {
        // Invalidate cache
        Cache::forget("active_trip_{$id}");

        $motoTrip = MotorcycleTrip::find($id);
        if ($motoTrip) {
            $controller = app(MotorcycleTripController::class);
            $response = $controller->arrived($request, $id);
            return $this->standardize($response);
        }

        $standardTrip = Trip::find($id);
        if ($standardTrip) {
            // Assert transition
            try {
                \App\Domain\Trip\TripStateMachine::assertTransitionForTrip($standardTrip, 'ARRIVED');
            } catch (\Exception $e) {
                return $this->respondError($e->getMessage(), 422, 'INVALID_TRANSITION');
            }

            DB::transaction(function () use ($standardTrip, $request) {
                $standardTrip->update([
                    'status' => 'ARRIVED',
                ]);

                TripStatusEvent::query()->create([
                    'trip_id' => $standardTrip->id,
                    'actor_type' => 'driver',
                    'actor_id' => $request->user()->id,
                    'old_status' => $standardTrip->getOriginal('status'),
                    'new_status' => 'ARRIVED',
                    'created_at' => now(),
                ]);

                RideEvent::query()->create([
                    'trip_id' => $standardTrip->id,
                    'driver_id' => $standardTrip->driver_id,
                    'passenger_id' => $standardTrip->passenger_id,
                    'event_type' => 'driver_arrived',
                    'event_time' => now(),
                ]);
            });

            // Broadcast to passenger
            app(\App\Services\SupabaseRealtimeService::class)->broadcast("trip:{$standardTrip->id}", 'trip_status_changed', [
                'trip_id' => $standardTrip->id,
                'status' => 'ARRIVED',
            ]);

            // Send in-app notification to passenger
            app(\App\Services\NotificationService::class)->sendInAppNotification(
                $standardTrip->passenger_id,
                'DRIVER_ARRIVED',
                'Driver Arrived',
                'Your driver has arrived at the pickup location.',
                ['trip_id' => $standardTrip->id]
            );

            // Sync to Firebase
            app(\App\Services\Sync\TripStateSyncService::class)->syncToFirebase($standardTrip);

            return $this->respondSuccess($standardTrip->fresh(), 'Driver arrived successfully');
        }

        return $this->respondError('Trip not found', 404, 'TRIP_NOT_FOUND');
    }

    /**
     * POST /driver/trips/{id}/start
     */
    public function start(Request $request, int $id): JsonResponse
    {
        // Invalidate cache
        Cache::forget("active_trip_{$id}");

        $motoTrip = MotorcycleTrip::find($id);
        if ($motoTrip) {
            $controller = app(MotorcycleTripController::class);
            $response = $controller->start($request, $id);
            return $this->standardize($response);
        }

        $standardTrip = Trip::find($id);
        if ($standardTrip) {
            $controller = app(TripController::class);
            $response = $controller->start($id);
            return $this->standardize($response);
        }

        return $this->respondError('Trip not found', 404, 'TRIP_NOT_FOUND');
    }

    /**
     * POST /driver/trips/{id}/complete
     */
    public function complete(Request $request, int $id): JsonResponse
    {
        // Invalidate cache
        Cache::forget("active_trip_{$id}");

        $motoTrip = MotorcycleTrip::find($id);
        if ($motoTrip) {
            $controller = app(MotorcycleTripController::class);
            $response = $controller->complete($request, $id);
            return $this->standardize($response);
        }

        $standardTrip = Trip::find($id);
        if ($standardTrip) {
            $controller = app(TripController::class);
            $response = $controller->complete($id);
            return $this->standardize($response);
        }

        return $this->respondError('Trip not found', 404, 'TRIP_NOT_FOUND');
    }
}
