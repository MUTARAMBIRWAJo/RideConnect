<?php

namespace App\Events;

use App\Models\MotorcycleTrip;
use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DriverAssigned implements ShouldBroadcast
{
    use Dispatchable, SerializesModels;

    public int $tripId;
    public int $passengerId;
    public int $driverId;
    public ?string $driverName;
    public ?string $vehiclePlate;

    public function __construct(MotorcycleTrip $trip, ?string $driverName = null, ?string $vehiclePlate = null)
    {
        $this->tripId = (int) $trip->id;
        $this->passengerId = (int) $trip->passenger_id;
        $this->driverId = (int) $trip->driver_id;
        $this->driverName = $driverName;
        $this->vehiclePlate = $vehiclePlate;
    }

    public function broadcastOn(): array
    {
        return [
            new Channel("trip.{$this->tripId}"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'DriverAssigned';
    }

    public function broadcastWith(): array
    {
        return [
            'trip_id' => $this->tripId,
            'passenger_id' => $this->passengerId,
            'driver_id' => $this->driverId,
            'driver_name' => $this->driverName,
            'vehicle_plate' => $this->vehiclePlate,
            'timestamp' => now()->toIso8601String(),
        ];
    }
}
