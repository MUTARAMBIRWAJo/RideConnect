<?php

namespace App\Events;

use App\Models\TripRequest;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithBroadcasting;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired when driver accepts a trip - notify passenger.
 */
class TripAcceptedByDriver implements ShouldBroadcast
{
    use Dispatchable, InteractsWithBroadcasting, SerializesModels;

    public function __construct(
        public TripRequest $tripRequest,
        public int $passengerId,
        public array $driverInfo,
        public array $vehicleInfo,
        public int $etaMinutes,
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("passenger.{$this->passengerId}"),
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
            'message' => 'Driver is on the way',
            'driver' => $this->driverInfo,
            'vehicle' => $this->vehicleInfo,
            'eta_minutes' => $this->etaMinutes,
            'status' => 'PASSENGER_WAITING',
        ];
    }
}
