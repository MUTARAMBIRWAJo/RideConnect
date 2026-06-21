<?php

namespace App\Services\V3;

use App\Models\Driver;
use Illuminate\Support\Facades\Log;

class NotificationServiceV3
{
    public function __construct()
    {
    }

    public function sendToDriver(int $driverId, array $payload): void
    {
        $driver = Driver::with('user')->find($driverId);
        
        if (!$driver || !$driver->user_id) {
            Log::warning("Could not send notification to driver [{$driverId}]: No user attached.");
            return;
        }

        // Save notification to the database
        \App\Models\Notification::create([
            'user_id' => $driver->user_id,
            'type' => $payload['type'] ?? 'NEW_TRIP_REQUEST',
            'title' => $payload['title'] ?? 'New Trip Request',
            'message' => $payload['message'] ?? ($payload['message'] ?? 'New trip request. Accept or reject.'),
            'data' => $payload,
            'is_read' => false,
        ]);

        broadcast(new \App\Events\NewTripRequestEvent($driver->user_id, $payload));

        Log::info("Laravel WebSocket Triggered for Driver [{$driverId}]", $payload);
    }

    public function sendToPassenger(int $passengerId, array $payload): void
    {
        // Save notification to the database
        \App\Models\Notification::create([
            'user_id' => $passengerId,
            'type' => $payload['type'] ?? 'TRIP_UPDATE',
            'title' => $payload['type'] === 'TRIP_ACCEPTED' ? 'Trip Accepted' : 'Trip Rejected',
            'message' => $payload['message'] ?? 'Your trip request has been updated.',
            'data' => $payload,
            'is_read' => false,
        ]);

        // Broadcast via Laravel Native WebSockets (Reverb)
        broadcast(new \App\Events\PassengerTripUpdateEvent($passengerId, $payload));

        Log::info("Laravel WebSocket Triggered for Passenger [{$passengerId}]", $payload);
    }
}
