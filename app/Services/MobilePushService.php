<?php

namespace App\Services;

use App\Models\MobileDeviceToken;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MobilePushService
{
    public function sendToMobileUser(int $mobileUserId, string $title, string $body, array $data): void
    {
        $serverKey = (string) config('services.push.fcm_server_key');
        if ($serverKey === '') {
            return;
        }

        MobileDeviceToken::query()
            ->where('user_id', $mobileUserId)
            ->pluck('device_token')
            ->filter()
            ->each(function (string $token) use ($serverKey, $title, $body, $data): void {
                try {
                    Http::withHeaders([
                        'Authorization' => 'key='.$serverKey,
                        'Content-Type' => 'application/json',
                    ])->post('https://fcm.googleapis.com/fcm/send', [
                        'to' => $token,
                        'notification' => [
                            'title' => $title,
                            'body' => $body,
                            'sound' => 'default',
                        ],
                        'data' => array_map(fn ($value) => is_scalar($value) || $value === null ? (string) $value : json_encode($value), $data),
                        'priority' => 'high',
                    ]);
                } catch (\Throwable $e) {
                    Log::warning('Mobile FCM push failed', [
                        'error' => $e->getMessage(),
                    ]);
                }
            });
    }
}
