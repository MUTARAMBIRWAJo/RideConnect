<?php

namespace App\Jobs;

use App\Models\Driver;
use App\Models\RideEvent;
use App\Models\Trip;
use App\Models\TripAssignmentAttempt;
use App\Models\TripRejection;
use App\Models\TripStatusEvent;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

class AttemptTimeoutJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public readonly int $attemptId) {}

    public function handle(): void
    {
        $tripId = DB::transaction(function (): ?int {
            $attempt = TripAssignmentAttempt::query()
                ->where('id', $this->attemptId)
                ->where('status', 'pending')
                ->lockForUpdate()
                ->first();

            if (! $attempt) {
                return null;
            }

            $trip = Trip::query()->lockForUpdate()->find($attempt->trip_id);
            if (! $trip) {
                return null;
            }

            $attempt->update([
                'status' => 'expired',
                'responded_at' => now(),
            ]);

            TripRejection::query()->create([
                'trip_id' => $trip->id,
                'driver_id' => $attempt->driver_id,
                'reason' => 'timeout',
            ]);

            Trip::query()->where('id', $trip->id)->update([
                'rejected_drivers_count' => DB::raw('rejected_drivers_count + 1'),
                'assignment_status' => 'unassigned',
                'status' => 'requested',
                'current_assignment_attempt_id' => null,
                'driver_id' => null,
                'updated_at' => now(),
            ]);

            Driver::query()->where('id', $attempt->driver_id)->update([
                'availability_status' => 'online',
                'updated_at' => now(),
            ]);

            TripStatusEvent::query()->create([
                'trip_id' => $trip->id,
                'actor_type' => 'system',
                'actor_id' => null,
                'old_status' => 'assigning',
                'new_status' => 'requested',
                'metadata' => ['reason' => 'timeout'],
                'created_at' => now(),
            ]);

            RideEvent::query()->create([
                'trip_id' => $trip->id,
                'driver_id' => $attempt->driver_id,
                'passenger_id' => $trip->passenger_id,
                'event_type' => 'driver_rejected',
                'metadata' => ['reason' => 'timeout'],
                'event_time' => now(),
            ]);

            return (int) $trip->id;
        });

        if (! $tripId) {
            return;
        }

        $trip = Trip::query()->find($tripId);
        if ($trip && (int) $trip->rejected_drivers_count < (int) config('ride.max_driver_rejections', 5)) {
            FindAndNotifyDriverJob::dispatch($tripId);
        }
    }
}
