<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\RideEvent;
use App\Models\Trip;
use App\Models\TripStatusEvent;
use App\Services\SupabaseRealtimeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TripStatusController extends Controller
{
    private const ALLOWED = [
        'accepted' => ['enroute_to_pickup', 'cancelled'],
        'enroute_to_pickup' => ['arrived_at_pickup', 'cancelled'],
        'arrived_at_pickup' => ['in_progress', 'cancelled'],
        'in_progress' => ['completed', 'cancelled'],
        'ACCEPTED' => ['STARTED', 'CANCELLED'],
        'STARTED' => ['COMPLETED', 'CANCELLED'],
    ];

    public function __construct(private readonly SupabaseRealtimeService $supabase) {}

    public function update(Request $request, Trip $trip): JsonResponse
    {
        $validated = $request->validate([
            'status' => 'required|in:PENDING,STARTED,COMPLETED,CANCELLED,enroute_to_pickup,arrived_at_pickup,in_progress,completed,cancelled',
            'metadata' => 'nullable|array',
        ]);

        $oldStatus = $trip->status;
        $normalizedNewStatus = \App\Domain\Trip\TripStateMachine::normalize($validated['status']);
        if (! \App\Domain\Trip\TripStateMachine::canTransition($oldStatus, $normalizedNewStatus)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Invalid trip status transition: ' . $oldStatus . ' -> ' . $normalizedNewStatus,
            ], 422);
        }

        DB::transaction(function () use ($trip, $oldStatus, $validated, $request): void {
            $statusMap = [
                'enroute_to_pickup' => 'STARTED',
                'arrived_at_pickup' => 'STARTED',
                'in_progress' => 'STARTED',
                'completed' => 'COMPLETED',
                'cancelled' => 'CANCELLED',
            ];
            
            $newStatus = $statusMap[$validated['status']] ?? $validated['status'];
            $updates = ['status' => $newStatus];
            if ($validated['status'] === 'in_progress' || $newStatus === 'STARTED') {
                $updates['started_at'] = now();
            }
            if ($validated['status'] === 'completed' || $newStatus === 'COMPLETED') {
                $updates['completed_at'] = now();
            }

            $trip->update($updates);

            TripStatusEvent::query()->create([
                'trip_id' => $trip->id,
                'actor_type' => $request->user()?->isDriver() ? 'driver' : 'system',
                'actor_id' => $request->user()?->id,
                'old_status' => $oldStatus,
                'new_status' => $newStatus,
                'metadata' => $validated['metadata'] ?? null,
                'created_at' => now(),
            ]);

            RideEvent::query()->create([
                'trip_id' => $trip->id,
                'driver_id' => $trip->driver_id,
                'passenger_id' => $trip->passenger_id,
                'event_type' => match ($validated['status']) {
                    'in_progress' => 'trip_started',
                    'completed' => 'trip_completed',
                    'cancelled' => 'trip_cancelled',
                    default => 'location_update',
                },
                'metadata' => ['status' => $newStatus] + ($validated['metadata'] ?? []),
                'event_time' => now(),
            ]);
        });

        $this->supabase->broadcast("trip:{$trip->id}", 'trip_status_changed', [
            'trip_id' => $trip->id,
            'status' => $validated['status'],
        ]);

        return response()->json([
            'status' => 'success',
            'data' => $trip->fresh(),
        ]);
    }
}
