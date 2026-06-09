<?php

namespace App\Events;

use App\Models\MotorcycleTrip;
use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DriverArrived implements ShouldBroadcast
{
    use Dispatchable, SerializesModels;

    public int $tripId;
    public int $passengerId;
    public int $driverId;

    public function __construct(MotorcycleTrip $trip, int $driverId)
    {
        $this->tripId = (int) $trip->id;
        $this->passengerId = (int) $trip->passenger_id;
        $this->driverId = $driverId;
    }

    public function broadcastOn(): array
    {
        return [
            new Channel("trip.{$this->tripId}"),
            new Channel("driver.{$this->driverId}"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'DriverArrived';
    }

    public function broadcastWith(): array
    {
        return [
            'trip_id' => $this->tripId,
            'passenger_id' => $this->passengerId,
            'driver_id' => $this->driverId,
            'timestamp' => now()->toIso8601String(),
        ];
    }
}
