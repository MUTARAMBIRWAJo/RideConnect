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

        broadcast(new \App\Events\NewTripRequestEvent($driver->user_id, $payload));

        Log::info("Laravel WebSocket Triggered for Driver [{$driverId}]", $payload);
    }

    public function sendToPassenger(int $passengerId, array $payload): void
    {
        // Broadcast via Laravel Native WebSockets (Reverb)
        broadcast(new \App\Events\PassengerTripUpdateEvent($passengerId, $payload));

        Log::info("Laravel WebSocket Triggered for Passenger [{$passengerId}]", $payload);
    }
}
