<?php

namespace App\Events;

use App\Models\MotorcycleTrip;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Base event for all motorcycle trip lifecycle updates.
 *
 * Channels:
 *   - Private: trip.{tripId}   (passenger + assigned driver)
 *   - Private: driver.{driverId} (assigned driver only)
 *   - Private: user.{userId} (passenger only)
 */
class MotorcycleTripLifecycleEvent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public string $eventType;
    public int $tripId;
    public int $passengerId;
    public ?int $driverId;
    public string $status;
    public ?string $matchingStatus;
    public ?array $payload;

    public function __construct(
        MotorcycleTrip $trip,
        string $eventType,
        ?int $driverId = null,
        ?array $payload = null,
    ) {
        $this->eventType     = $eventType;
        $this->tripId        = (int) $trip->id;
        $this->passengerId   = (int) $trip->passenger_id;
        $this->driverId      = $driverId;
        $this->status        = (string) $trip->status;
        $this->matchingStatus = (string) ($trip->matching_status ?? '');
        $this->payload       = $payload ?? [];
    }

    /**
     * Channels this event broadcasts to.
     *
     * Flutter subscribes:
     *   private-trip.{tripId}        — passenger sees everything
     *   private-driver.{driverId}    — driver sees their own trips
     *   private-user.{passengerId}   — passenger personal updates
     */
    public function broadcastOn(): array
    {
        $channels = [
            new PrivateChannel("trip.{$this->tripId}"),
        ];

        if ($this->driverId) {
            $channels[] = new PrivateChannel("driver.{$this->driverId}");
        }
        $channels[] = new PrivateChannel("user.{$this->passengerId}");

        return $channels;
    }

    /**
     * Event name on the wire — Flutter listens for these exact strings.
     */
    public function broadcastAs(): string
    {
        return $this->eventType;
    }

    /**
     * Payload shape Flutter consumes.
     */
    public function broadcastWith(): array
    {
        return [
            'trip_id'        => $this->tripId,
            'status'         => $this->status,
            'matching_status'=> $this->matchingStatus,
            'driver_id'      => $this->driverId,
            'event'          => $this->eventType,
            'payload'        => $this->payload,
            'timestamp'      => now()->toIso8601String(),
        ];
    }
}
