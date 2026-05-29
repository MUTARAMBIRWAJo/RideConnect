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
    ];

    public function __construct(private readonly SupabaseRealtimeService $supabase) {}

    public function update(Request $request, Trip $trip): JsonResponse
    {
        $validated = $request->validate([
            'status' => 'required|in:enroute_to_pickup,arrived_at_pickup,in_progress,completed,cancelled',
            'metadata' => 'nullable|array',
        ]);

        $oldStatus = $trip->status;
        if (! in_array($validated['status'], self::ALLOWED[$oldStatus] ?? [], true)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Invalid trip status transition',
            ], 422);
        }

        DB::transaction(function () use ($trip, $oldStatus, $validated, $request): void {
            $updates = ['status' => $validated['status']];
            if ($validated['status'] === 'in_progress') {
                $updates['started_at'] = now();
            }
            if ($validated['status'] === 'completed') {
                $updates['completed_at'] = now();
            }

            $trip->update($updates);

            TripStatusEvent::query()->create([
                'trip_id' => $trip->id,
                'actor_type' => $request->user()?->isDriver() ? 'driver' : 'system',
                'actor_id' => $request->user()?->id,
                'old_status' => $oldStatus,
                'new_status' => $validated['status'],
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
                'metadata' => ['status' => $validated['status']] + ($validated['metadata'] ?? []),
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
