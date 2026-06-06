<?php

namespace App\Events\Domain;

use App\Models\TripRequest;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DriverAcceptedTrip
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public TripRequest $tripRequest,
        public int $driverId,
        public ?string $notes = null,
    ) {}

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel("trip-request.{$this->tripRequest->id}");
    }

    public function broadcastAs(): string
    {
        return 'driver.accepted';
    }
}
