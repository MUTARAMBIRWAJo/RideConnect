<?php

namespace App\Events;

use App\Models\TripRequest;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithBroadcasting;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired when trip is rejected and reassigned.
 */
class TripReassignedToNewDriver implements ShouldBroadcast
{
    use Dispatchable, InteractsWithBroadcasting, SerializesModels;

    public function __construct(
        public TripRequest $tripRequest,
        public int $oldDriverId,
        public int $newDriverId,
        public int $passengerId,
        public string $reason = 'DRIVER_DECLINED',
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("driver.{$this->newDriverId}"),
            new PrivateChannel("passenger.{$this->passengerId}"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'trip.reassigned';
    }

    public function broadcastWith(): array
    {
        return [
            'trip_id' => $this->tripRequest->id,
            'message' => 'New driver has been assigned',
            'reason' => $this->reason,
            'status' => 'ASSIGNED',
        ];
    }
}
