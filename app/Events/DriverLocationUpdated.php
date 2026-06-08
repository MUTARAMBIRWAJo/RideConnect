<?php

namespace App\Events;

use App\Models\Driver;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * DriverLocationUpdated - Broadcast event for real-time driver location updates
 *
 * Fired when a driver updates their location during an active trip
 * Passengers can subscribe to this event to track driver in real-time
 */
class DriverLocationUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public int $driverId;
    public int $tripId;
    public float $lat;
    public float $lng;
    public ?float $speed;
    public ?int $heading;
    public ?float $accuracy;
    public string $timestamp;

    public function __construct(
        int $driverId,
        int $tripId,
        float $lat,
        float $lng,
        ?float $speed = null,
        ?int $heading = null,
        ?float $accuracy = null
    ) {
        $this->driverId = $driverId;
        $this->tripId = $tripId;
        $this->lat = $lat;
        $this->lng = $lng;
        $this->speed = $speed;
        $this->heading = $heading;
        $this->accuracy = $accuracy;
        $this->timestamp = now()->toIso8601String();
    }

    /**
     * Get the channels the event should broadcast on
     *
     * Broadcast to:
     * - Private channel for passenger tracking
     * - Private channel for driver (if needed for admin)
     */
    public function broadcastOn(): array
    {
        return [
            // Passenger can listen on this channel
            new PrivateChannel("trip.{$this->tripId}"),
            // Admin/support could listen on driver channel
            new PrivateChannel("driver.{$this->driverId}"),
        ];
    }

    /**
     * The event's broadcast name
     */
    public function broadcastAs(): string
    {
        return 'location.updated';
    }

    /**
     * Data to broadcast
     */
    public function broadcastWith(): array
    {
        return [
            'driver_id' => $this->driverId,
            'trip_id' => $this->tripId,
            'lat' => $this->lat,
            'lng' => $this->lng,
            'speed' => $this->speed,
            'heading' => $this->heading,
            'accuracy' => $this->accuracy,
            'timestamp' => $this->timestamp,
        ];
    }
}
