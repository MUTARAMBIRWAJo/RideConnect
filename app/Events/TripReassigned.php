<?php

namespace App\Events;

use App\Models\TripRequest;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TripReassigned implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public TripRequest $tripRequest,
        public int $newDriverId,
        public ?int $previousDriverId = null
    ) {}

    public function broadcastOn(): array
    {
        $channels = [
            new Channel("driver.{$this->newDriverId}"),
            new Channel("passenger.{$this->tripRequest->passenger_id}"),
        ];
        
        if ($this->previousDriverId) {
            $channels[] = new Channel("driver.{$this->previousDriverId}");
        }
        
        return $channels;
    }

    public function broadcastAs(): string
    {
        return 'trip.reassigned';
    }

    public function broadcastWith(): array
    {
        return [
            'trip_id' => $this->tripRequest->id,
            'status' => 'REASSIGNED',
            'new_driver_id' => $this->newDriverId,
            'message' => 'New driver has been assigned to your trip',
        ];
    }
}
