<?php

namespace App\Events;

use App\Models\MotorcycleTrip;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MotorcycleTripAssigned implements ShouldBroadcast
{
    use Dispatchable, SerializesModels;

    use \Illuminate\Broadcasting\InteractsWithBroadcasting;

    public function __construct(
        public MotorcycleTrip $trip,
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("driver.{$this->trip->driver_id}"),
            new PrivateChannel("passenger.{$this->trip->passenger_id}"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'motorcycle.trip.assigned';
    }

    public function broadcastWith(): array
    {
        return [
            'trip_id' => $this->trip->id,
            'status' => $this->trip->status,
            'pickup_location' => $this->trip->pickup_location,
            'dropoff_location' => $this->trip->dropoff_location,
            'estimated_fare' => $this->trip->estimated_fare,
            'message' => 'Trip assigned',
        ];
    }
}
