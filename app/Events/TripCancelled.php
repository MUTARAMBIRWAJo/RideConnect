<?php

namespace App\Events;

use App\Models\MotorcycleTrip;
use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TripCancelled implements ShouldBroadcast
{
    use Dispatchable, SerializesModels;

    public int $tripId;
    public int $passengerId;
    public ?int $driverId;
    public string $cancelledBy; // 'passenger' | 'driver'
    public ?string $reason;

    public function __construct(MotorcycleTrip $trip, string $cancelledBy, ?int $driverId = null, ?string $reason = null)
    {
        $this->tripId = (int) $trip->id;
        $this->passengerId = (int) $trip->passenger_id;
        $this->driverId = $driverId;
        $this->cancelledBy = $cancelledBy;
        $this->reason = $reason;
    }

    public function broadcastOn(): array
    {
        $channels = [
            new Channel("trip.{$this->tripId}"),
            new Channel("user.{$this->passengerId}"),
        ];
        if ($this->driverId) {
            $channels[] = new Channel("driver.{$this->driverId}");
        }
        return $channels;
    }

    public function broadcastAs(): string
    {
        return 'TripCancelled';
    }

    public function broadcastWith(): array
    {
        return [
            'trip_id' => $this->tripId,
            'passenger_id' => $this->passengerId,
            'driver_id' => $this->driverId,
            'cancelled_by' => $this->cancelledBy,
            'reason' => $this->reason,
            'timestamp' => now()->toIso8601String(),
        ];
    }
}
