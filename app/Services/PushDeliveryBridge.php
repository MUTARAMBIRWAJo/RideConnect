<?php

namespace App\Services;

use App\Models\Notification;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PushDeliveryBridge
{
    public function deliverUserNotification(User $user, Notification $notification): void
    {
        $tokens = $user->mobileDeviceTokens()->get();

        foreach ($tokens as $token) {
            try {
                if ($token->platform === 'fcm') {
                    $this->sendFcm($token->device_token, $notification);
                }

                if ($token->platform === 'apns') {
                    $this->sendApns($token->device_token, $notification);
                }
            } catch (\Throwable $e) {
                Log::warning('Push delivery failed', [
                    'user_id' => $user->id,
                    'notification_id' => $notification->id,
                    'platform' => $token->platform,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    private function sendFcm(string $deviceToken, Notification $notification): void
    {
        $serverKey = (string) config('services.push.fcm_server_key');
        if ($serverKey === '') {
            return;
        }

        $payload = [
            'to' => $deviceToken,
            'notification' => [
                'title' => $notification->title,
                'body' => $notification->message,
                'sound' => 'default',
            ],
            'data' => [
                'notification_id' => (string) $notification->id,
                'type' => $notification->type,
                'payload' => $notification->data,
            ],
            'priority' => 'high',
        ];

        Http::withHeaders([
            'Authorization' => 'key='.$serverKey,
            'Content-Type' => 'application/json',
        ])->post('https://fcm.googleapis.com/fcm/send', $payload);
    }

    private function sendApns(string $deviceToken, Notification $notification): void
    {
        $keyId = (string) config('services.push.apns_key_id');
        $teamId = (string) config('services.push.apns_team_id');
        $bundleId = (string) config('services.push.apns_bundle_id');
        $privateKey = (string) config('services.push.apns_private_key');

        if ($keyId === '' || $teamId === '' || $bundleId === '' || $privateKey === '') {
            return;
        }

        $jwt = $this->buildApnsJwt($keyId, $teamId, $privateKey);
        if ($jwt === null) {
            return;
        }

        $host = (bool) config('services.push.apns_use_sandbox', false)
            ? 'https://api.sandbox.push.apple.com'
            : 'https://api.push.apple.com';

        $payload = [
            'aps' => [
                'alert' => [
                    'title' => $notification->title,
                    'body' => $notification->message,
                ],
                'sound' => 'default',
            ],
            'type' => $notification->type,
            'notification_id' => (string) $notification->id,
            'payload' => $notification->data,
        ];

        Http::withHeaders([
            'authorization' => 'bearer '.$jwt,
            'apns-topic' => $bundleId,
            'apns-push-type' => 'alert',
            'apns-priority' => '10',
            'content-type' => 'application/json',
        ])->withBody(json_encode($payload), 'application/json')
            ->post("{$host}/3/device/{$deviceToken}");
    }

    private function buildApnsJwt(string $keyId, string $teamId, string $privateKey): ?string
    {
        $header = $this->base64UrlEncode(json_encode([
            'alg' => 'ES256',
            'kid' => $keyId,
        ]));

        $payload = $this->base64UrlEncode(json_encode([
            'iss' => $teamId,
            'iat' => time(),
        ]));

        $privateKeyPem = str_replace('\\n', "\n", $privateKey);
        $signatureInput = $header.'.'.$payload;

        $signature = '';
        $signed = openssl_sign($signatureInput, $signature, $privateKeyPem, 'sha256');
        if (! $signed) {
            Log::warning('Unable to sign APNs JWT with provided private key.');

            return null;
        }

        return $signatureInput.'.'.$this->base64UrlEncode($signature);
    }

    private function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}
