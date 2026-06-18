<?php

namespace App\Events\V3;

use App\Models\V3\TripV3;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TripLifecycleEventV3 implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public TripV3 $trip,
        public string $eventName,
        public array $payload,
        public ?int $driverUserId = null,
    ) {}

    public function broadcastOn(): array
    {
        $channels = [
            new PrivateChannel('trip.v3.'.$this->trip->id),
            new PrivateChannel('passenger.'.$this->trip->user_id),
        ];

        if ($this->driverUserId) {
            $channels[] = new PrivateChannel('driver.'.$this->driverUserId);
        }

        return $channels;
    }

    public function broadcastAs(): string
    {
        return $this->eventName;
    }

    public function broadcastWith(): array
    {
        return $this->payload;
    }
}
