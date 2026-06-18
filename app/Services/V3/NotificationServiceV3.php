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

        $this->mobileNotificationService->sendToUserId(
            $driver->user_id,
            $payload['type'] ?? 'DRIVER_NOTIFICATION',
            $title,
            $message,
            $payload
        );

        Log::info("Laravel Push Notification Triggered for Driver [{$driverId}]", $payload);
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

        // Assuming $passengerId is the mobile_users.id or users.id. 
        // We can pass it to the mobile notification service.
        $this->mobileNotificationService->sendToUserId(
            $passengerId,
            $payload['type'] ?? 'PASSENGER_NOTIFICATION',
            $title,
            $message,
            $payload
        );

        Log::info("Laravel Push Notification Triggered for Passenger [{$passengerId}]", $payload);
    }
}
