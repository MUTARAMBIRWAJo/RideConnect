<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\FindAndNotifyDriverJob;
use App\Models\Driver;
use App\Models\MobileUser;
use App\Models\RideEvent;
use App\Models\Trip;
use App\Models\TripAssignmentAttempt;
use App\Models\TripRejection;
use App\Models\TripStatusEvent;
use App\Models\UserNotification;
use App\Services\MobilePushService;
use App\Services\SupabaseRealtimeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DriverTripController extends Controller
{
    public function __construct(
        private readonly MobilePushService $pushService,
        private readonly SupabaseRealtimeService $supabase,
    ) {}

    public function respond(Request $request, Trip $trip): JsonResponse
    {
        $validated = $request->validate([
            'action' => 'required|in:accept,reject',
            'reason' => 'required_if:action,reject|string|max:255',
        ]);

        $mobileUserId = $this->mobileUserId($request->user());
        $driver = Driver::query()->where('user_id', $mobileUserId)->first();

        if (! $driver) {
            return response()->json(['status' => 'error', 'message' => 'Driver profile not found'], 404);
        }

        $attempt = TripAssignmentAttempt::query()->find($trip->current_assignment_attempt_id);
        if (! $attempt || $attempt->status !== 'pending' || ($attempt->expires_at && $attempt->expires_at->isPast())) {
            return response()->json(['status' => 'error', 'message' => 'Trip request already expired'], 409);
        }

        if ((int) $attempt->driver_id !== (int) $driver->id) {
            return response()->json(['status' => 'error', 'message' => 'This trip request is not assigned to this driver'], 403);
        }

        return $validated['action'] === 'accept'
            ? $this->accept($trip, $attempt, $driver)
            : $this->reject($trip, $attempt, $driver, $validated['reason']);
    }

    private function accept(Trip $trip, TripAssignmentAttempt $attempt, Driver $driver): JsonResponse
    {
        DB::transaction(function () use ($trip, $attempt, $driver): void {
            $attempt->update([
                'status' => 'accepted',
                'responded_at' => now(),
            ]);

            $trip->update([
                'status' => 'ACCEPTED',
                'assignment_status' => 'assigned',
                'driver_id' => $driver->id,
                'accepted_at' => now(),
            ]);

            TripStatusEvent::query()->create([
                'trip_id' => $trip->id,
                'actor_type' => 'driver',
                'actor_id' => $driver->id,
                'old_status' => $trip->status,
                'new_status' => 'ACCEPTED',
                'created_at' => now(),
            ]);

            RideEvent::query()->create([
                'trip_id' => $trip->id,
                'driver_id' => $driver->id,
                'passenger_id' => $trip->passenger_id,
                'event_type' => 'driver_accepted',
                'event_time' => now(),
            ]);

            DB::table('matching_sessions')
                ->where('matching_session_id', $trip->matching_session_id)
                ->update([
                    'status' => 'matched',
                    'selected_driver_id' => $driver->id,
                    'updated_at' => now(),
                ]);

            $driver->update(['availability_status' => 'busy']);
        });

        $mobileUser = MobileUser::query()->find($driver->user_id);
        $driverName = trim(($mobileUser?->first_name ?? '').' '.($mobileUser?->last_name ?? ''));
        $payload = [
            'type' => 'trip_accepted',
            'trip_id' => $trip->id,
            'driver_id' => $driver->id,
            'driver_name' => $driverName,
            'driver_lat' => $driver->current_latitude,
            'driver_lng' => $driver->current_longitude,
            'license_plate' => $driver->license_plate,
        ];

        $this->pushService->sendToMobileUser((int) $trip->passenger_id, 'Driver on the way', 'Your driver accepted the trip.', $payload);

        UserNotification::query()->create([
            'user_id' => (int) $trip->passenger_id,
            'type' => 'trip.accepted',
            'title' => 'Driver on the way',
            'message' => 'Your driver accepted the trip.',
            'data' => $payload,
            'is_read' => false,
        ]);

        $this->supabase->broadcast("trip:{$trip->id}", 'trip_accepted', $payload);

        return response()->json([
            'status' => 'success',
            'message' => 'Trip accepted',
            'data' => $trip->fresh(['driver', 'passenger']),
        ]);
    }

    private function reject(Trip $trip, TripAssignmentAttempt $attempt, Driver $driver, string $reason): JsonResponse
    {
        $rejectedCount = DB::transaction(function () use ($trip, $attempt, $driver, $reason): int {
            $attempt->update([
                'status' => 'rejected',
                'rejection_reason' => $reason,
                'responded_at' => now(),
            ]);

            TripRejection::query()->create([
                'trip_id' => $trip->id,
                'driver_id' => $driver->id,
                'reason' => $reason,
            ]);

            Trip::query()->where('id', $trip->id)->update([
                'rejected_drivers_count' => DB::raw('rejected_drivers_count + 1'),
                'assignment_status' => 'unassigned',
                'status' => 'requested',
                'current_assignment_attempt_id' => null,
                'driver_id' => null,
                'updated_at' => now(),
            ]);

            $driver->update(['availability_status' => 'online']);

            TripStatusEvent::query()->create([
                'trip_id' => $trip->id,
                'actor_type' => 'driver',
                'actor_id' => $driver->id,
                'old_status' => 'assigning',
                'new_status' => 'requested',
                'metadata' => ['reason' => $reason],
                'created_at' => now(),
            ]);

            RideEvent::query()->create([
                'trip_id' => $trip->id,
                'driver_id' => $driver->id,
                'passenger_id' => $trip->passenger_id,
                'event_type' => 'driver_rejected',
                'metadata' => ['reason' => $reason],
                'event_time' => now(),
            ]);

            return (int) Trip::query()->where('id', $trip->id)->value('rejected_drivers_count');
        });

        if ($rejectedCount < (int) config('ride.max_driver_rejections', 5)) {
            FindAndNotifyDriverJob::dispatch((int) $trip->id);
        } else {
            DB::transaction(function () use ($trip): void {
                $trip->update(['status' => 'cancelled', 'assignment_status' => 'failed']);

                TripStatusEvent::query()->create([
                    'trip_id' => $trip->id,
                    'actor_type' => 'system',
                    'old_status' => 'requested',
                    'new_status' => 'cancelled',
                    'metadata' => ['reason' => 'max_driver_rejections'],
                    'created_at' => now(),
                ]);

                RideEvent::query()->create([
                    'trip_id' => $trip->id,
                    'passenger_id' => $trip->passenger_id,
                    'event_type' => 'trip_cancelled',
                    'metadata' => ['reason' => 'max_driver_rejections'],
                    'event_time' => now(),
                ]);
            });

            UserNotification::query()->create([
                'user_id' => (int) $trip->passenger_id,
                'type' => 'trip.cancelled',
                'title' => 'Trip cancelled',
                'message' => 'No drivers accepted this trip.',
                'data' => ['trip_id' => $trip->id],
                'is_read' => false,
            ]);

            $this->pushService->sendToMobileUser((int) $trip->passenger_id, 'Trip cancelled', 'No drivers accepted this trip.', [
                'type' => 'trip_cancelled',
                'trip_id' => $trip->id,
            ]);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Rejection recorded',
        ]);
    }

    private function mobileUserId($user): int
    {
        if ($user->mobile_user_id) {
            return (int) $user->mobile_user_id;
        }

        return (int) (MobileUser::query()->where('email', $user->email)->value('id') ?? $user->id);
    }
}
