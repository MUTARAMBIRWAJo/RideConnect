<?php

namespace App\Services;

use App\Events\Domain\TripAssignmentCreated;
use App\Exceptions\DomainException;
use App\Jobs\ExpireAssignmentAttemptJob;
use App\Models\Driver;
use App\Models\Ride;
use App\Models\Trip;
use App\Models\TripAssignmentAttempt;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class TripAssignmentService
{
    public function __construct(private readonly PublicTransportAvailabilityService $availabilityService) {}

    public function createAttempt(Trip $trip, Driver $driver, array $scoreBreakdown = [], int $ttlSeconds = 45): TripAssignmentAttempt
    {
        return DB::transaction(function () use ($trip, $driver, $scoreBreakdown, $ttlSeconds): TripAssignmentAttempt {
            $lockedTrip = Trip::query()->lockForUpdate()->findOrFail($trip->id);
            $lockedDriver = Driver::query()->lockForUpdate()->findOrFail($driver->id);

            if ($lockedDriver->availability_status !== 'available') {
                throw DomainException::make('Driver is not available', 'DRIVER_NOT_AVAILABLE');
            }

            if ($lockedTrip->transport_type === Ride::TRANSPORT_MOTORCYCLE && $this->availabilityService->isMotoDriverBusy($lockedDriver->id)) {
                throw DomainException::make('Moto driver is busy', 'MOTO_DRIVER_BUSY');
            }

            TripAssignmentAttempt::query()
                ->where('trip_id', $lockedTrip->id)
                ->whereIn('status', [TripAssignmentAttempt::STATUS_PENDING, TripAssignmentAttempt::STATUS_NOTIFIED])
                ->lockForUpdate()
                ->update(['status' => TripAssignmentAttempt::STATUS_CANCELLED]);

            $attempt = TripAssignmentAttempt::query()->create([
                'trip_id' => $lockedTrip->id,
                'driver_id' => $lockedDriver->id,
                'score' => $this->compositeScore($scoreBreakdown),
                'score_breakdown' => $scoreBreakdown,
                'status' => TripAssignmentAttempt::STATUS_NOTIFIED,
                'expires_at' => now()->addSeconds($ttlSeconds),
            ]);

            $lockedTrip->update([
                'assignment_status' => 'notified',
                'current_assignment_attempt_id' => $attempt->id,
            ]);

            event(new TripAssignmentCreated($attempt->id));
            ExpireAssignmentAttemptJob::dispatch($attempt->id)->delay($attempt->expires_at);

            return $attempt;
        });
    }

    public function acceptAttempt(int $attemptId, int $driverId): Trip
    {
        return DB::transaction(function () use ($attemptId, $driverId): Trip {
            $attempt = TripAssignmentAttempt::query()->lockForUpdate()->findOrFail($attemptId);

            if ((int) $attempt->driver_id !== $driverId) {
                throw DomainException::make('Assignment does not belong to driver', 'ASSIGNMENT_DRIVER_MISMATCH');
            }

            if (! in_array($attempt->status, [TripAssignmentAttempt::STATUS_PENDING, TripAssignmentAttempt::STATUS_NOTIFIED], true)) {
                throw DomainException::make('Assignment is no longer active', 'ASSIGNMENT_NOT_ACTIVE');
            }

            if ($attempt->expires_at && $attempt->expires_at->isPast()) {
                $attempt->update(['status' => TripAssignmentAttempt::STATUS_EXPIRED]);
                throw DomainException::make('Assignment expired', 'ASSIGNMENT_EXPIRED');
            }

            $trip = Trip::query()->lockForUpdate()->findOrFail($attempt->trip_id);
            $driver = Driver::query()->lockForUpdate()->findOrFail($driverId);

            if ($trip->transport_type === Ride::TRANSPORT_MOTORCYCLE && $this->availabilityService->isMotoDriverBusy($driver->id)) {
                throw DomainException::make('Moto driver is busy', 'MOTO_DRIVER_BUSY');
            }

            $attempt->update([
                'status' => TripAssignmentAttempt::STATUS_ACCEPTED,
                'responded_at' => now(),
            ]);

            $trip->update([
                'driver_id' => $driver->id,
                'status' => 'ACCEPTED',
                'assignment_status' => 'accepted',
                'accepted_at' => now(),
            ]);

            if ($trip->transport_type === Ride::TRANSPORT_MOTORCYCLE) {
                $driver->update(['availability_status' => 'busy']);
            }

            return $trip->fresh();
        });
    }

    public function rejectAttempt(int $attemptId, int $driverId, string $reason): TripAssignmentAttempt
    {
        return DB::transaction(function () use ($attemptId, $driverId, $reason): TripAssignmentAttempt {
            $attempt = TripAssignmentAttempt::query()->lockForUpdate()->findOrFail($attemptId);

            if ((int) $attempt->driver_id !== $driverId) {
                throw DomainException::make('Assignment does not belong to driver', 'ASSIGNMENT_DRIVER_MISMATCH');
            }

            $attempt->update([
                'status' => TripAssignmentAttempt::STATUS_REJECTED,
                'rejection_reason' => $reason,
                'responded_at' => now(),
            ]);

            Trip::query()
                ->whereKey($attempt->trip_id)
                ->update(['assignment_status' => 'rejected']);

            event(new \App\Events\Domain\TripAssignmentRejected($attempt->id));

            return $attempt->fresh();
        });
    }

    public function candidatesFor(Trip $trip, ?Ride $ride = null): Collection
    {
        $ride = $ride ?: $trip->ride;
        if (! $ride) {
            return collect();
        }

        return $this->availabilityService
            ->availableQuery(['transport_type' => $ride->transport_type, 'route_id' => $ride->route_id])
            ->where('id', $ride->id)
            ->get()
            ->pluck('driver')
            ->filter()
            ->unique('id')
            ->values();
    }

    public function defaultScoreBreakdown(Trip $trip, Driver $driver): array
    {
        return [
            'distance' => 0.22,
            'behavior' => min(0.18, ((float) ($driver->rating ?? 0) / 5) * 0.18),
            'available_seats' => $trip->ride?->isBus() ? 0.15 : 0.10,
            'route_compatibility' => 0.12,
            'traffic' => 0.08,
            'fair_distribution' => 0.05,
        ];
    }

    private function compositeScore(array $breakdown): float
    {
        return round(min(1.0, max(0.0, array_sum(array_map('floatval', $breakdown)))), 4);
    }
}
