<?php

namespace App\Events;

use App\Models\MotorcycleTrip;
use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DriverAccepted implements ShouldBroadcast
{
    use Dispatchable, SerializesModels;

    public int $tripId;
    public int $passengerId;
    public int $driverId;
    public ?string $driverName;
    public ?string $driverPhone;
    public ?string $vehiclePlate;
    public int $estimatedArrival;

    public function __construct(
        MotorcycleTrip $trip,
        int $driverId,
        ?string $driverName = null,
        ?string $driverPhone = null,
        ?string $vehiclePlate = null,
        int $estimatedArrival = 5
    ) {
        $this->tripId = (int) $trip->id;
        $this->passengerId = (int) $trip->passenger_id;
        $this->driverId = $driverId;
        $this->driverName = $driverName;
        $this->driverPhone = $driverPhone;
        $this->vehiclePlate = $vehiclePlate;
        $this->estimatedArrival = $estimatedArrival;
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
        return 'DriverAccepted';
    }

    public function broadcastWith(): array
    {
        return [
            'trip_id' => $this->tripId,
            'passenger_id' => $this->passengerId,
            'driver_id' => $this->driverId,
            'driver_name' => $this->driverName,
            'driver_phone' => $this->driverPhone,
            'vehicle_plate' => $this->vehiclePlate,
            'estimated_arrival' => $this->estimatedArrival,
            'timestamp' => now()->toIso8601String(),
        ];
    }
}
