<?php

namespace App\Services\V3;

use App\Events\V3\TripV3StatusChanged;
use App\Models\V3\TripV3;
use InvalidArgumentException;

class TripLifecycleEngineV3
{
    /**
     * Allowed state transitions.
     */
    protected const TRANSITIONS = [
        'REQUESTED' => ['MATCHING', 'CANCELLED'],
        'PENDING_MATCH' => ['MATCHING', 'CANCELLED'],
        'MATCHING' => ['MATCHING', 'DRIVER_FOUND', 'DRIVER_ASSIGNED', 'CANCELLED', 'FAILED'],
        'DRIVER_FOUND' => ['MATCHING', 'DRIVER_ASSIGNED', 'CANCELLED'],
        'DRIVER_ASSIGNED' => ['DRIVER_ARRIVED', 'IN_PROGRESS', 'CANCELLED'],
        'DRIVER_ARRIVED' => ['IN_PROGRESS', 'CANCELLED'],
        'IN_PROGRESS' => ['COMPLETED', 'CANCELLED'],
        'COMPLETED' => ['PAID'],
        'PAID' => ['RATED'],
        'RATED' => [],
        'CANCELLED' => [],
        'FAILED' => [],
    ];

    /**
     * Transition trip to new status.
     *
     * @throws InvalidArgumentException
     */
    public function transition(TripV3 $trip, string $newStatus): TripV3
    {
        if (!array_key_exists($newStatus, self::TRANSITIONS)) {
            throw new InvalidArgumentException("Invalid status: {$newStatus}");
        }

        $allowedTransitions = self::TRANSITIONS[$trip->status] ?? [];

        if (!in_array($newStatus, $allowedTransitions, true)) {
            throw new InvalidArgumentException("Cannot transition from {$trip->status} to {$newStatus}");
        }

        $trip->status = $newStatus;
        $trip->save();

        event(new TripV3StatusChanged($trip));

        return $trip;
    }

    /**
     * Cancel a trip.
     */
    public function cancel(TripV3 $trip, string $reason): TripV3
    {
        $metadata = $trip->metadata ?? [];
        $metadata['cancellation_reason'] = $reason;
        $trip->metadata = $metadata;

        return $this->transition($trip, 'CANCELLED');
    }
}
