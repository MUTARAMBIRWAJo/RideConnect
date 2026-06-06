<?php

namespace App\Events;

use App\Models\TripRequest;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TripAssigned implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public TripRequest $tripRequest,
        public int $driverId,
        public string $vehicleInfo = ''
    ) {}

    public function broadcastOn(): array
    {
        return [
            new Channel("driver.{$this->driverId}"),
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
            'status' => 'ASSIGNED',
            'pickup_location' => $this->tripRequest->pickup_location,
            'dropoff_location' => $this->tripRequest->dropoff_location,
            'pickup_lat' => $this->tripRequest->pickup_lat,
            'pickup_lng' => $this->tripRequest->pickup_lng,
            'dropoff_lat' => $this->tripRequest->dropoff_lat,
            'dropoff_lng' => $this->tripRequest->dropoff_lng,
            'estimated_fare' => $this->tripRequest->estimated_fare,
            'trip_distance_km' => $this->tripRequest->trip_distance_km,
            'vehicle_info' => $this->vehicleInfo,
        ];
    }
}
