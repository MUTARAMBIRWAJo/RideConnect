<?php

namespace App\Services\Notifications;

use App\Models\DeviceToken;
use App\Models\User;
use App\Jobs\SendNotificationJob;

class PassengerNotificationService
{
    /**
     * Send push notification to all registered tokens of a passenger.
     */
    public function send(int $passengerId, string $title, string $body, array $data = []): void
    {
        $tokens = DeviceToken::where('tokenable_type', User::class)
            ->where('tokenable_id', $passengerId)
            ->pluck('fcm_token');

        foreach ($tokens as $token) {
            dispatch(new SendNotificationJob(
                recipientType: User::class,
                recipientId: $passengerId,
                fcmToken: $token,
                title: $title,
                body: $body,
                data: $data
            ));
        }
    }
}
