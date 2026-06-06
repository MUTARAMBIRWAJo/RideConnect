<?php

namespace App\Services;

use App\Models\TripRequest;
use Illuminate\Support\Facades\Log;

class TripStatusTransitionService
{
    /**
     * Valid status transitions for trip requests.
     *
     * @var array
     */
    private const VALID_TRANSITIONS = [
        'PENDING_MATCH' => ['PASSENGER_WAITING', 'CANCELLED'],
        'PASSENGER_WAITING' => ['PASSENGER_BOARDED', 'CANCELLED'],
        'PASSENGER_BOARDED' => ['IN_TRANSIT', 'CANCELLED'],
        'IN_TRANSIT' => ['COMPLETED', 'CANCELLED'],
        'COMPLETED' => [],
        'CANCELLED' => [],
    ];

    /**
     * Check if a status transition is valid.
     *
     * @param  string  $currentStatus
     * @param  string  $newStatus
     * @return bool
     */
    public function isValidTransition(string $currentStatus, string $newStatus): bool
    {
        return in_array($newStatus, self::VALID_TRANSITIONS[$currentStatus] ?? [], true);
    }

    /**
     * Transition a trip request to a new status with validation.
     *
     * @param  TripRequest  $tripRequest
     * @param  string  $newStatus
     * @return bool
     *
     * @throws \Exception
     */
    public function transition(TripRequest $tripRequest, string $newStatus): bool
    {
        $currentStatus = $tripRequest->status;

        if (!$this->isValidTransition($currentStatus, $newStatus)) {
            Log::warning('Invalid trip status transition attempted', [
                'trip_request_id' => $tripRequest->id,
                'current_status' => $currentStatus,
                'attempted_status' => $newStatus,
            ]);

            throw new \Exception(
                "Cannot transition from {$currentStatus} to {$newStatus}",
                'INVALID_STATUS_TRANSITION'
            );
        }

        $updated = $tripRequest->update(['status' => $newStatus]);

        Log::info('Trip status transitioned', [
            'trip_request_id' => $tripRequest->id,
            'from_status' => $currentStatus,
            'to_status' => $newStatus,
        ]);

        return $updated;
    }

    /**
     * Check if a trip can be accepted by a driver.
     *
     * @param  TripRequest  $tripRequest
     * @return bool
     */
    public function canBeAccepted(TripRequest $tripRequest): bool
    {
        // Can only accept if in PENDING_MATCH status
        return $tripRequest->status === 'PENDING_MATCH';
    }

    /**
     * Check if a trip can be rejected by a driver.
     *
     * @param  TripRequest  $tripRequest
     * @return bool
     */
    public function canBeRejected(TripRequest $tripRequest): bool
    {
        // Cannot reject if already IN_TRANSIT or COMPLETED
        $nonRejectable = ['IN_TRANSIT', 'COMPLETED'];
        return !in_array($tripRequest->status, $nonRejectable, true);
    }

    /**
     * Get all valid transitions from a status.
     *
     * @param  string  $status
     * @return array
     */
    public function getValidTransitions(string $status): array
    {
        return self::VALID_TRANSITIONS[$status] ?? [];
    }
}
