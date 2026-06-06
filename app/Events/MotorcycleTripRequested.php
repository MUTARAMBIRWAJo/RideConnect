<?php

namespace App\Events;

use App\Models\MotorcycleTrip;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithBroadcasting;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MotorcycleTripRequested implements ShouldBroadcast
{
    use Dispatchable, InteractsWithBroadcasting, SerializesModels;

    public function __construct(
        public MotorcycleTrip $trip,
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("passenger.{$this->trip->passenger_id}"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'motorcycle.trip.requested';
    }

    public function broadcastWith(): array
    {
        return [
            'trip_id' => $this->trip->id,
            'status' => $this->trip->status,
            'pickup_location' => $this->trip->pickup_location,
            'dropoff_location' => $this->trip->dropoff_location,
            'estimated_fare' => $this->trip->estimated_fare,
            'message' => 'Your motorcycle trip request has been created',
        ];
    }
}
