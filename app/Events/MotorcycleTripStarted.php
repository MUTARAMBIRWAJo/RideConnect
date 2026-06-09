<?php

namespace App\Events;

use App\Models\MotorcycleTrip;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MotorcycleTripStarted extends MotorcycleTripLifecycleEvent
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public static function dispatch(MotorcycleTrip $trip, ?int $driverId = null, ?array $payload = null): static
    {
        return event(new static($trip, 'TripStarted', $driverId, $payload));
    }
}
