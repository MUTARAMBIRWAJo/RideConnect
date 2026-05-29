<?php

namespace App\Http\Controllers\Api;

use App\Events\Domain\DriverAvailabilityChanged;
use App\Events\Domain\TripStarted;
use App\Exceptions\DomainException;
use App\Http\Controllers\Controller;
use App\Models\DriverAvailabilitySnapshot;
use App\Models\Trip;
use App\Models\TripAssignmentAttempt;
use App\Models\TripStatusEvent;
use App\Services\TripAssignmentService;
use App\Services\TripCompletionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DriverPublicTransportController extends Controller
{
    public function __construct(
        private readonly TripAssignmentService $assignmentService,
        private readonly TripCompletionService $completionService,
    ) {}

    public function updateStatus(Request $request): JsonResponse
    {
        $driver = $request->user()->driver;
        if (! $driver) {
            return response()->json(['success' => false, 'message' => 'Driver profile not found'], 404);
        }

        $validated = $request->validate([
            'availability_status' => 'required|in:offline,available,busy,suspended',
            'lat' => 'nullable|numeric|between:-90,90',
            'lng' => 'nullable|numeric|between:-180,180',
        ]);

        $oldStatus = $driver->availability_status;
        $driver->forceFill([
            'availability_status' => $validated['availability_status'],
            'current_latitude' => $validated['lat'] ?? $driver->current_latitude,
            'current_longitude' => $validated['lng'] ?? $driver->current_longitude,
            'last_online_at' => $validated['availability_status'] === 'offline' ? $driver->last_online_at : now(),
            'online_since' => $validated['availability_status'] === 'available'
                ? ($driver->online_since ?: now())
                : null,
        ])->save();

        DriverAvailabilitySnapshot::query()->create([
            'driver_id' => $driver->id,
            'availability_status' => $driver->availability_status,
            'latitude' => $driver->current_latitude,
            'longitude' => $driver->current_longitude,
            'metadata' => ['old_status' => $oldStatus],
        ]);

        event(new DriverAvailabilityChanged((int) $driver->id, (string) $driver->availability_status));

        return response()->json(['success' => true, 'data' => $driver->fresh()]);
    }

    public function currentAssignment(Request $request): JsonResponse
    {
        $driver = $request->user()->driver;
        if (! $driver) {
            return response()->json(['success' => false, 'message' => 'Driver profile not found'], 404);
        }

        $attempt = TripAssignmentAttempt::query()
            ->with('trip.ride')
            ->where('driver_id', $driver->id)
            ->whereIn('status', ['pending', 'notified'])
            ->where('expires_at', '>', now())
            ->latest()
            ->first();

        return response()->json(['success' => true, 'data' => $attempt]);
    }

    public function accept(Request $request, TripAssignmentAttempt $attempt): JsonResponse
    {
        $driver = $request->user()->driver;
        if (! $driver) {
            return response()->json(['success' => false, 'message' => 'Driver profile not found'], 404);
        }

        try {
            $trip = $this->assignmentService->acceptAttempt((int) $attempt->id, (int) $driver->id);
        } catch (DomainException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'error_code' => $e->getErrorCode(),
            ], 422);
        }

        return response()->json(['success' => true, 'data' => $trip]);
    }

    public function reject(Request $request, TripAssignmentAttempt $attempt): JsonResponse
    {
        $driver = $request->user()->driver;
        if (! $driver) {
            return response()->json(['success' => false, 'message' => 'Driver profile not found'], 404);
        }

        $validated = $request->validate([
            'reason' => 'required|string|max:255',
        ]);

        try {
            $attempt = $this->assignmentService->rejectAttempt((int) $attempt->id, (int) $driver->id, $validated['reason']);
        } catch (DomainException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'error_code' => $e->getErrorCode(),
            ], 422);
        }

        return response()->json(['success' => true, 'data' => $attempt]);
    }

    public function pickupVerify(Request $request, int $trip): JsonResponse
    {
        // Explicit model fetching to handle invalid IDs gracefully (including 0)
        $tripModel = Trip::query()->find($trip);
        if (! $tripModel) {
            return response()->json(['success' => false, 'message' => 'Trip not found'], 404);
        }

        $driver = $request->user()->driver;
        if (! $driver || (int) $tripModel->driver_id !== (int) $driver->id) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        $tripModel->update(['pickup_verified_at' => now()]);

        return response()->json(['success' => true, 'data' => $tripModel->fresh()]);
    }

    public function start(Request $request, int $trip): JsonResponse
    {
        // Explicit model fetching to handle invalid IDs gracefully (including 0)
        $tripModel = Trip::query()->find($trip);
        if (! $tripModel) {
            return response()->json(['success' => false, 'message' => 'Trip not found'], 404);
        }

        $driver = $request->user()->driver;
        if (! $driver || (int) $tripModel->driver_id !== (int) $driver->id) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        $trip_result = DB::transaction(function () use ($tripModel, $driver): Trip {
            $locked = Trip::query()
                ->whereKey($tripModel->id)
                ->where('driver_id', $driver->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->status !== 'ACCEPTED') {
                throw DomainException::make('Trip must be accepted before start', 'INVALID_TRIP_STATUS');
            }

            $oldStatus = $locked->status;
            $locked->update(['status' => 'STARTED', 'started_at' => now()]);

            TripStatusEvent::query()->create([
                'trip_id' => $locked->id,
                'actor_type' => 'driver',
                'actor_id' => $driver->id,
                'old_status' => $oldStatus,
                'new_status' => 'STARTED',
                'metadata' => [],
                'created_at' => now(),
            ]);

            return $locked->fresh();
        }, 2);

        event(new TripStarted((int) $trip_result->id));

        return response()->json(['success' => true, 'data' => $trip_result]);
    }

    public function complete(Request $request, int $trip): JsonResponse
    {
        // Explicit model fetching to handle invalid IDs gracefully (including 0)
        $tripModel = Trip::query()->find($trip);
        if (! $tripModel) {
            return response()->json(['success' => false, 'message' => 'Trip not found'], 404);
        }

        $driver = $request->user()->driver;
        if (! $driver || (int) $tripModel->driver_id !== (int) $driver->id) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        try {
            $trip_result = $this->completionService->complete((int) $tripModel->id, (int) $driver->id);
        } catch (DomainException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'error_code' => $e->getErrorCode(),
            ], 422);
        }

        return response()->json(['success' => true, 'data' => $trip_result]);
    }
}
