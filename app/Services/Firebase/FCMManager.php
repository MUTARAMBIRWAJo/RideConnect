<?php

namespace App\Services\Firebase;

use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification;
use Illuminate\Support\Facades\Log;

class FCMManager
{
    public function __construct(protected readonly FirebaseManager $firebaseManager) {}

    /**
     * Send push notification to a specific device token.
     * Returns the message ID string or null on failure.
     */
    public function sendToToken(string $token, string $title, string $body, array $data = []): ?string
    {
        $messaging = $this->firebaseManager->messaging();
        if ($messaging === null) {
            Log::warning('[FCMManager] Messaging client unavailable. Skipped token push notification.');
            return null;
        }

        try {
            $notification = Notification::create($title, $body);
            $message = CloudMessage::new()
                ->withToken($token)
                ->withNotification($notification)
                ->withData($data);

            $result = $messaging->send($message);
            return $result['name'] ?? 'success';
        } catch (\Throwable $e) {
            Log::error('[FCMManager] Push notification failed to token', [
                'token' => substr($token, 0, 15) . '...',
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Send push notification to a topic.
     * Returns the message ID string or null on failure.
     */
    public function sendToTopic(string $topic, string $title, string $body, array $data = []): ?string
    {
        $messaging = $this->firebaseManager->messaging();
        if ($messaging === null) {
            Log::warning('[FCMManager] Messaging client unavailable. Skipped topic push notification.');
            return null;
        }

        try {
            $notification = Notification::create($title, $body);
            $message = CloudMessage::new()
                ->withTopic($topic)
                ->withNotification($notification)
                ->withData($data);

            $result = $messaging->send($message);
            return $result['name'] ?? 'success';
        } catch (\Throwable $e) {
            Log::error("[FCMManager] Push notification failed to topic: {$topic}", [
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Subscribe multiple device tokens to a topic.
     */
    public function subscribeToTopic(string $topic, array $tokens): void
    {
        $messaging = $this->firebaseManager->messaging();
        if ($messaging === null) {
            return;
        }

        try {
            $messaging->subscribeToTopic($topic, $tokens);
        } catch (\Throwable $e) {
            Log::error("[FCMManager] Failed subscribing tokens to topic: {$topic}", [
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Unsubscribe multiple device tokens from a topic.
     */
    public function unsubscribeFromTopic(string $topic, array $tokens): void
    {
        $messaging = $this->firebaseManager->messaging();
        if ($messaging === null) {
            return;
        }

        try {
            $messaging->unsubscribeFromTopic($topic, $tokens);
        } catch (\Throwable $e) {
            Log::error("[FCMManager] Failed unsubscribing tokens from topic: {$topic}", [
                'error' => $e->getMessage()
            ]);
        }
    }
}
