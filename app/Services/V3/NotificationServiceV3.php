<?php

namespace App\Services\V3;

use App\Models\Driver;
use App\Services\MobileNotificationService;
use Illuminate\Support\Facades\Log;

class NotificationServiceV3
{
    private MobileNotificationService $mobileNotificationService;

    public function __construct(MobileNotificationService $mobileNotificationService)
    {
        $this->mobileNotificationService = $mobileNotificationService;
    }

    public function sendToDriver(int $driverId, array $payload): void
    {
        $driver = Driver::with('user')->find($driverId);
        
        if (!$driver || !$driver->user_id) {
            Log::warning("Could not send push notification to driver [{$driverId}]: No user attached.");
            return;
        }

        $title = $payload['type'] === 'NEW_TRIP_REQUEST' ? 'New Trip Request' : 'Trip Update';
        $message = $payload['message'] ?? 'You have a new update for your trip.';

        broadcast(new \App\Events\NewTripRequestEvent($driver->user_id, $payload));

        $this->mobileNotificationService->sendToUserId(
            $driver->user_id,
            'trip_update',
            $title,
            $message,
            $payload
        );

        Log::info("Laravel WebSocket & Mobile Push Triggered for Driver [{$driverId}]", $payload);
    }

    public function sendToPassenger(int $passengerId, array $payload): void
    {
        $title = 'Trip Update';
        if ($payload['type'] === 'TRIP_ACCEPTED') {
            $title = 'Driver Found!';
        } elseif ($payload['type'] === 'TRIP_REJECTED') {
            $title = 'Finding another driver...';
        }

        $message = $payload['message'] ?? 'You have a new update for your trip.';

        // Broadcast via Laravel Native WebSockets (Reverb)
        broadcast(new \App\Events\PassengerTripUpdateEvent($passengerId, $payload));

        $this->mobileNotificationService->sendToUserId(
            $passengerId,
            'trip_update',
            $title,
            $message,
            $payload
        );

        Log::info("Laravel WebSocket & Mobile Push Triggered for Passenger [{$passengerId}]", $payload);
    }
}
