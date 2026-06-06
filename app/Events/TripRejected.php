<?php

namespace App\Events;

use App\Models\TripRequest;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TripRejected implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public TripRequest $tripRequest,
        public int $rejectedDriverId,
        public string $reason = 'DRIVER_DECLINED'
    ) {}

    public function broadcastOn(): array
    {
        return [
            new Channel("driver.{$this->rejectedDriverId}"),
            new Channel("passenger.{$this->tripRequest->passenger_id}"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'trip.rejected';
    }

    public function broadcastWith(): array
    {
        return [
            'trip_id' => $this->tripRequest->id,
            'status' => 'REJECTED_BY_DRIVER',
            'reason' => $this->reason,
            'message' => 'Driver cannot accept trip. Finding alternative...',
        ];
    }
}
