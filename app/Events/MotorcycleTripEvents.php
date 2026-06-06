<?php

namespace App\Events;

use App\Models\MotorcycleTrip;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Broadcasting\InteractsWithBroadcasting;
use Illuminate\Queue\SerializesModels;

class MotorcycleTripStarted implements ShouldBroadcast
{
    use Dispatchable, InteractsWithBroadcasting, SerializesModels;

    public function __construct(public MotorcycleTrip $trip) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("driver.{$this->trip->driver_id}"),
            new PrivateChannel("passenger.{$this->trip->passenger_id}"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'motorcycle.trip.started';
    }

    public function broadcastWith(): array
    {
        return [
            'trip_id' => $this->trip->id,
            'status' => $this->trip->status,
            'message' => 'Trip has started',
        ];
    }
}

class MotorcycleTripCompleted implements ShouldBroadcast
{
    use Dispatchable, InteractsWithBroadcasting, SerializesModels;

    public function __construct(public MotorcycleTrip $trip) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("driver.{$this->trip->driver_id}"),
            new PrivateChannel("passenger.{$this->trip->passenger_id}"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'motorcycle.trip.completed';
    }

    public function broadcastWith(): array
    {
        return [
            'trip_id' => $this->trip->id,
            'status' => $this->trip->status,
            'fare' => $this->trip->actual_fare ?? $this->trip->estimated_fare,
            'message' => 'Trip completed successfully',
        ];
    }
}

class MotorcycleDriverArrived implements ShouldBroadcast
{
    use Dispatchable, InteractsWithBroadcasting, SerializesModels;

    public function __construct(public MotorcycleTrip $trip) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("passenger.{$this->trip->passenger_id}"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'motorcycle.driver.arrived';
    }

    public function broadcastWith(): array
    {
        return [
            'trip_id' => $this->trip->id,
            'status' => $this->trip->status,
            'message' => 'Your driver has arrived',
        ];
    }
}
