<?php

namespace App\Services;

use App\Models\Notification;
use App\Models\User;
use App\Services\Realtime\RealtimeGateway;
use Illuminate\Support\Facades\Log;

/**
 * NotificationService handles push notifications and in-app notifications.
 *
 * Supports both push notifications (abstracted) and in-app notifications.
 */
class NotificationService
{
    public function __construct(
        private readonly RealtimeGateway $realtimeGateway,
    ) {}

    /**
     * Send push notification to user.
     */
    public function sendPushNotification(int $userId, string $title, string $body, array $data = []): void
    {
        // Placeholder: Implement with FCM, APNs, or your push provider
        // e.g., Firebase Cloud Messaging, OneSignal, etc.

        \Log::info("Push notification to user {$userId}: {$title} - {$body}", $data);
    }

    /**
     * Send in-app notification to user.
     * Creates a record in the user_notifications table and broadcasts via real-time gateway.
     */
    public function sendInAppNotification(int $userId, string $type, string $title, string $message, array $data = []): void
    {
        try {
            // Create notification record in database
            $notification = Notification::create([
                'user_id' => $userId,
                'type' => $type,
                'title' => $title,
                'message' => $message,
                'data' => $data,
                'is_read' => false,
            ]);

            // Broadcast notification via real-time gateway
            $payload = [
                'id' => $notification->id,
                'type' => $type,
                'title' => $title,
                'message' => $message,
                'data' => $data,
                'created_at' => $notification->created_at->toIso8601String(),
            ];

            // Try to determine if user is driver or passenger and notify
            $user = User::find($userId);
            if ($user && $user->driver) {
                $this->realtimeGateway->notifyDriver($user->driver->id, $payload);
            } elseif ($user && $user->mobile_user_id) {
                $this->realtimeGateway->notifyPassenger($user->mobile_user_id, $payload);
            }

            Log::info('In-app notification created', [
                'notification_id' => $notification->id,
                'user_id' => $userId,
                'type' => $type,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to send in-app notification', [
                'user_id' => $userId,
                'type' => $type,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Send booking created notification.
     */
    public function notifyBookingCreated(int $userId, int $bookingId): void
    {
        $this->sendInAppNotification(
            $userId,
            'booking_created',
            'Booking Confirmed',
            'Your ride booking has been created successfully.',
            ['booking_id' => $bookingId]
        );
    }

    /**
     * Send trip matched notification.
     */
    public function notifyTripMatched(int $userId, int $tripId): void
    {
        $this->sendInAppNotification(
            $userId,
            'trip_matched',
            'Driver Found',
            'A driver has been assigned to your trip.',
            ['trip_id' => $tripId]
        );

        $this->sendPushNotification(
            $userId,
            'Driver Found',
            'A driver has been assigned to your trip.'
        );
    }

    /**
     * Send driver accepted notification.
     */
    public function notifyDriverAccepted(int $passengerUserId, int $tripId): void
    {
        $this->sendInAppNotification(
            $passengerUserId,
            'driver_accepted',
            'Driver Accepted',
            'Your driver has accepted the trip and is on the way.',
            ['trip_id' => $tripId]
        );

        $this->sendPushNotification(
            $passengerUserId,
            'Driver Accepted',
            'Your driver has accepted the trip and is on the way.'
        );
    }

    /**
     * Send trip started notification.
     */
    public function notifyTripStarted(int $passengerUserId, int $tripId): void
    {
        $this->sendInAppNotification(
            $passengerUserId,
            'trip_started',
            'Trip Started',
            'Your trip has started. Safe travels!',
            ['trip_id' => $tripId]
        );

        $this->sendPushNotification(
            $passengerUserId,
            'Trip Started',
            'Your trip has started. Safe travels!'
        );
    }

    /**
     * Send trip completed notification.
     */
    public function notifyTripCompleted(int $userId, int $tripId): void
    {
        $this->sendInAppNotification(
            $userId,
            'trip_completed',
            'Trip Completed',
            'Your trip has been completed successfully.',
            ['trip_id' => $tripId]
        );

        $this->sendPushNotification(
            $userId,
            'Trip Completed',
            'Your trip has been completed successfully.'
        );
    }
}
