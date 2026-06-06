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
 * Fired when a trip is assigned to a driver.
 */
class TripAssignedToDriver implements ShouldBroadcast
{
    use Dispatchable, InteractsWithBroadcasting, SerializesModels;

    public function __construct(
        public TripRequest $tripRequest,
        public int $driverId,
        public string $pickupLocation,
        public string $dropoffLocation,
        public float $estimatedFare,
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("driver.{$this->driverId}"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'trip.assigned';
    }

    public function broadcastWith(): array
    {
        return [
            'trip_id' => $this->tripRequest->id,
            'pickup_location' => $this->pickupLocation,
            'dropoff_location' => $this->dropoffLocation,
            'estimated_fare' => $this->estimatedFare,
            'status' => 'ASSIGNED',
        ];
    }
}
