<?php

namespace App\Events;

use App\Models\MotorcycleTrip;
use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TripRequestUpdated implements ShouldBroadcast
{
    use Dispatchable, SerializesModels;

    public int $tripId;
    public string $status;
    public ?string $matchingStatus;

    public function __construct(MotorcycleTrip $trip)
    {
        $this->tripId = (int) $trip->id;
        $this->status = (string) $trip->status;
        $this->matchingStatus = $trip->matching_status ? (string) $trip->matching_status : null;
    }

    public function broadcastOn(): array
    {
        return [
            new Channel("trip.{$this->tripId}"),
            new Channel("user.{$this->tripId}"), // TODO: wire passenger_id when available
        ];
    }

    public function broadcastAs(): string
    {
        return 'TripRequestUpdated';
    }

    public function broadcastWith(): array
    {
        return [
            'trip_id' => $this->tripId,
            'status' => $this->status,
            'matching_status' => $this->matchingStatus,
            'timestamp' => now()->toIso8601String(),
        ];
    }
}
