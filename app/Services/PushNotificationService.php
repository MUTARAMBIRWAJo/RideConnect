<?php

namespace App\Services;

use App\Models\User;
use App\Models\MobileDeviceToken;
use Illuminate\Support\Facades\Log;
use Kreait\Firebase\Messaging;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification as FirebaseNotification;
use Kreait\Firebase\Exception\MessagingException;

class PushNotificationService
{
    public function __construct(
        private readonly Messaging $messaging,
        private readonly DeviceTokenService $deviceTokenService,
    ) {}

    /**
     * Send notification to a specific user
     */
    public function sendToUser(
        int $userId,
        string $title,
        string $body,
        array $data = [],
        ?string $imageUrl = null
    ): array {
        $tokens = $this->deviceTokenService->getUserTokens($userId);
        
        if ($tokens->isEmpty()) {
            Log::info('No active tokens found for user', ['user_id' => $userId]);
            return ['sent' => 0, 'failed' => 0, 'invalid_tokens' => []];
        }

        $notification = FirebaseNotification::create($title, $body)
            ->withImageUrl($imageUrl ?? '');

        $message = CloudMessage::new()
            ->withNotification($notification)
            ->withData($data);

        return $this->sendToTokens($tokens->pluck('token')->toArray(), $message);
    }

    /**
     * Send notification to multiple users
     */
    public function sendToUsers(
        array $userIds,
        string $title,
        string $body,
        array $data = [],
        ?string $imageUrl = null
    ): array {
        $tokens = MobileDeviceToken::whereIn('user_id', $userIds)
            ->where('active', true)
            ->where('last_used_at', '>', now()->subDays(30))
            ->pluck('token')
            ->toArray();

        if (empty($tokens)) {
            Log::info('No active tokens found for users', ['user_ids' => $userIds]);
            return ['sent' => 0, 'failed' => 0, 'invalid_tokens' => []];
        }

        $notification = FirebaseNotification::create($title, $body)
            ->withImageUrl($imageUrl ?? '');

        $message = CloudMessage::new()
            ->withNotification($notification)
            ->withData($data);

        return $this->sendToTokens($tokens, $message);
    }

    /**
     * Send notification to a topic
     */
    public function sendToTopic(
        string $topic,
        string $title,
        string $body,
        array $data = [],
        ?string $imageUrl = null
    ): bool {
        try {
            $notification = FirebaseNotification::create($title, $body)
                ->withImageUrl($imageUrl ?? '');

            $message = CloudMessage::new()
                ->withNotification($notification)
                ->withData($data);

            $this->messaging->send($message, ['topic' => $topic]);

            Log::info('Notification sent to topic', ['topic' => $topic]);
            return true;
        } catch (MessagingException $e) {
            Log::error('Failed to send notification to topic', [
                'topic' => $topic,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Send data-only notification (silent push)
     */
    public function sendDataMessage(int $userId, array $data): array
    {
        $tokens = $this->deviceTokenService->getUserTokens($userId);
        
        if ($tokens->isEmpty()) {
            return ['sent' => 0, 'failed' => 0, 'invalid_tokens' => []];
        }

        $message = CloudMessage::new()->withData($data);

        return $this->sendToTokens($tokens->pluck('token')->toArray(), $message);
    }

    /**
     * Send notification to specific tokens
     */
    private function sendToTokens(array $tokens, CloudMessage $message): array
    {
        $result = [
            'sent' => 0,
            'failed' => 0,
            'invalid_tokens' => [],
        ];

        try {
            // Send in batches of 500 (Firebase limit)
            $chunks = array_chunk($tokens, 500);

            foreach ($chunks as $chunk) {
                $sendReport = $this->messaging->sendMulticast($message, $chunk);

                $result['sent'] += $sendReport->successes()->count();
                $result['failed'] += $sendReport->failures()->count();

                // Handle invalid tokens
                foreach ($sendReport->invalidTokens() as $invalidToken) {
                    $result['invalid_tokens'][] = $invalidToken;
                    $this->deviceTokenService->removeToken($invalidToken);
                }
            }

            Log::info('Push notification sent', [
                'sent' => $result['sent'],
                'failed' => $result['failed'],
                'invalid_tokens' => count($result['invalid_tokens']),
            ]);
        } catch (MessagingException $e) {
            Log::error('Failed to send push notification', [
                'error' => $e->getMessage(),
            ]);
            $result['failed'] = count($tokens);
        }

        return $result;
    }

    /**
     * Send trip-specific notification to passenger
     */
    public function sendTripNotificationToPassenger(
        int $passengerId,
        string $eventType,
        array $tripData
    ): array {
        $messages = [
            'driver_assigned' => [
                'title' => 'Driver Assigned',
                'body' => 'Your driver has been assigned and is on the way',
            ],
            'driver_arrived' => [
                'title' => 'Driver Arrived',
                'body' => 'Your driver has arrived at your pickup location',
            ],
            'trip_started' => [
                'title' => 'Trip Started',
                'body' => 'Your trip has started',
            ],
            'trip_completed' => [
                'title' => 'Trip Completed',
                'body' => 'Your trip has been completed successfully',
            ],
            'trip_cancelled' => [
                'title' => 'Trip Cancelled',
                'body' => 'Your trip has been cancelled',
            ],
        ];

        $message = $messages[$eventType] ?? [
            'title' => 'Trip Update',
            'body' => 'Your trip status has been updated',
        ];

        return $this->sendToUser(
            $passengerId,
            $message['title'],
            $message['body'],
            array_merge(['type' => 'trip_update', 'event' => $eventType], $tripData)
        );
    }

    /**
     * Send trip-specific notification to driver
     */
    public function sendTripNotificationToDriver(
        int $driverId,
        string $eventType,
        array $tripData
    ): array {
        $messages = [
            'new_request' => [
                'title' => 'New Ride Request',
                'body' => 'You have a new ride request',
            ],
            'passenger_cancelled' => [
                'title' => 'Passenger Cancelled',
                'body' => 'The passenger has cancelled the trip',
            ],
            'payment_confirmed' => [
                'title' => 'Payment Confirmed',
                'body' => 'Payment for the trip has been confirmed',
            ],
        ];

        $message = $messages[$eventType] ?? [
            'title' => 'Trip Update',
            'body' => 'Trip status has been updated',
        ];

        return $this->sendToUser(
            $driverId,
            $message['title'],
            $message['body'],
            array_merge(['type' => 'trip_update', 'event' => $eventType], $tripData)
        );
    }
}
