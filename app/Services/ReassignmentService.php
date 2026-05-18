<?php

namespace App\Services;

use App\Events\Domain\TripReassigned;
use App\Exceptions\DomainException;
use App\Models\ReassignmentLog;
use App\Models\Trip;
use App\Models\TripAssignmentAttempt;
use Illuminate\Support\Facades\DB;

class ReassignmentService
{
    public function __construct(private readonly TripAssignmentService $assignmentService) {}

    public function reassign(int $tripId, string $reason = 'reassignment_required', string $triggeredBy = 'system'): ?TripAssignmentAttempt
    {
        return DB::transaction(function () use ($tripId, $reason, $triggeredBy): ?TripAssignmentAttempt {
            $trip = Trip::query()->with('ride')->lockForUpdate()->findOrFail($tripId);
            $oldDriverId = $trip->driver_id;

            if (in_array($trip->status, ['COMPLETED', 'CANCELLED'], true)) {
                throw DomainException::make('Cannot reassign a closed trip', 'TRIP_CLOSED');
            }

            TripAssignmentAttempt::query()
                ->where('trip_id', $trip->id)
                ->whereIn('status', [TripAssignmentAttempt::STATUS_PENDING, TripAssignmentAttempt::STATUS_NOTIFIED])
                ->lockForUpdate()
                ->update(['status' => TripAssignmentAttempt::STATUS_EXPIRED]);

            $excludedDriverIds = TripAssignmentAttempt::query()
                ->where('trip_id', $trip->id)
                ->whereIn('status', [TripAssignmentAttempt::STATUS_REJECTED, TripAssignmentAttempt::STATUS_EXPIRED])
                ->pluck('driver_id')
                ->all();

            $candidate = $this->assignmentService
                ->candidatesFor($trip)
                ->reject(fn ($driver) => in_array($driver->id, $excludedDriverIds, true))
                ->first();

            if (! $candidate) {
                $trip->update([
                    'driver_id' => null,
                    'assignment_status' => 'unassigned',
                    'current_assignment_attempt_id' => null,
                ]);

                ReassignmentLog::query()->create([
                    'trip_id' => $trip->id,
                    'old_driver_id' => $oldDriverId,
                    'new_driver_id' => null,
                    'reason' => $reason,
                    'triggered_by' => $triggeredBy,
                ]);

                return null;
            }

            $attempt = $this->assignmentService->createAttempt(
                $trip,
                $candidate,
                $this->assignmentService->defaultScoreBreakdown($trip, $candidate)
            );

            ReassignmentLog::query()->create([
                'trip_id' => $trip->id,
                'old_driver_id' => $oldDriverId,
                'new_driver_id' => $candidate->id,
                'reason' => $reason,
                'triggered_by' => $triggeredBy,
            ]);

            event(new TripReassigned($trip->id, $oldDriverId, $candidate->id));

            return $attempt;
        });
    }
}
