<?php

namespace App\Services\Notifications;

use App\Models\DeviceToken;
use App\Models\User;
use App\Jobs\SendNotificationJob;

class AdminBroadcastService
{
    /**
     * Send push notification broadcast to all admin and superadmin users.
     */
    public function sendToAdmins(string $title, string $body, array $data = []): void
    {
        // Query users with admin / super_admin role column
        $adminIds = User::whereIn('role', ['admin', 'super_admin'])->pluck('id');

        $tokens = DeviceToken::where('tokenable_type', User::class)
            ->whereIn('tokenable_id', $adminIds)
            ->get();

        foreach ($tokens as $token) {
            dispatch(new SendNotificationJob(
                recipientType: User::class,
                recipientId: $token->tokenable_id,
                fcmToken: $token->fcm_token,
                title: $title,
                body: $body,
                data: $data
            ));
        }
    }
}
