<?php

namespace App\Events;

use App\Models\MotorcycleTrip;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Broadcasting\InteractsWithBroadcasting;
use Illuminate\Queue\SerializesModels;

class MotorcycleTripRejected implements ShouldBroadcast
{
    use Dispatchable, InteractsWithBroadcasting, SerializesModels;

    public function __construct(
        public MotorcycleTrip $trip,
        public int $rejectedDriverId,
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("driver.{$this->rejectedDriverId}"),
            new PrivateChannel("passenger.{$this->trip->passenger_id}"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'motorcycle.trip.rejected';
    }

    public function broadcastWith(): array
    {
        return [
            'trip_id' => $this->trip->id,
            'status' => $this->trip->status,
            'message' => 'Finding another driver...',
        ];
    }
}
