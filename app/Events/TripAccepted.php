<?php

namespace App\Events;

use App\Models\TripRequest;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TripAccepted implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public TripRequest $tripRequest,
        public int $driverId,
        public array $driverInfo = []
    ) {}

    public function broadcastOn(): array
    {
        return [
            new Channel("passenger.{$this->tripRequest->passenger_id}"),
            new Channel("driver.{$this->driverId}"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'trip.accepted';
    }

    public function broadcastWith(): array
    {
        return [
            'trip_id' => $this->tripRequest->id,
            'status' => 'PASSENGER_WAITING',
            'driver_info' => $this->driverInfo,
            'passenger_waiting' => true,
            'estimated_pickup_minutes' => $this->tripRequest->trip_duration_minutes,
        ];
    }
}
