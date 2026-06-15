<?php

namespace App\Services\Notifications;

use App\Models\DeviceToken;
use App\Models\Driver;
use App\Jobs\SendNotificationJob;

class DriverNotificationService
{
    /**
     * Send push notification to all registered tokens of a driver.
     */
    public function send(int $driverId, string $title, string $body, array $data = []): void
    {
        $tokens = DeviceToken::where('tokenable_type', Driver::class)
            ->where('tokenable_id', $driverId)
            ->pluck('fcm_token');

        foreach ($tokens as $token) {
            dispatch(new SendNotificationJob(
                recipientType: Driver::class,
                recipientId: $driverId,
                fcmToken: $token,
                title: $title,
                body: $body,
                data: $data
            ));
        }
    }
}
