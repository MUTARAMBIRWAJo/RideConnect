<?php

namespace App\Events;

use App\Models\MotorcycleTrip;
use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TripCompleted implements ShouldBroadcast
{
    use Dispatchable, SerializesModels;

    public int $tripId;
    public int $passengerId;
    public int $driverId;
    public ?float $actualFare;

    public function __construct(MotorcycleTrip $trip, int $driverId, ?float $actualFare = null)
    {
        $this->tripId = (int) $trip->id;
        $this->passengerId = (int) $trip->passenger_id;
        $this->driverId = $driverId;
        $this->actualFare = $actualFare;
    }

    public function broadcastOn(): array
    {
        return [
            new Channel("trip.{$this->tripId}"),
            new Channel("driver.{$this->driverId}"),
            new Channel("user.{$this->passengerId}"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'TripCompleted';
    }

    public function broadcastWith(): array
    {
        return [
            'trip_id' => $this->tripId,
            'passenger_id' => $this->passengerId,
            'driver_id' => $this->driverId,
            'actual_fare' => $this->actualFare,
            'timestamp' => now()->toIso8601String(),
        ];
    }
}
