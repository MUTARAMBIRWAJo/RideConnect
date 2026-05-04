<?php

namespace App\Services;

use App\Models\Trip;

/**
 * RealtimeGateway handles real-time communication abstraction.
 *
 * This is a placeholder implementation for real-time features.
 * In production, this would integrate with WebSockets, Pusher, or similar.
 */
class RealtimeGateway
{
    /**
     * Send event to specific user.
     */
    public function sendToUser(int $userId, string $event, array $data): void
    {
        // Placeholder: Implement with your real-time provider
        // e.g., Pusher, Socket.io, Laravel Broadcasting, etc.

        // For now, just log the event
        \Log::info("Realtime event to user {$userId}: {$event}", $data);
    }

    /**
     * Send event to multiple users.
     */
    public function sendToUsers(array $userIds, string $event, array $data): void
    {
        foreach ($userIds as $userId) {
            $this->sendToUser($userId, $event, $data);
        }
    }

    /**
     * Broadcast trip update to passenger and driver.
     */
    public function broadcastTripUpdate(Trip $trip, string $event, array $data = []): void
    {
        $users = [];

        if ($trip->passenger && $trip->passenger->user_id) {
            $users[] = $trip->passenger->user_id;
        }

        if ($trip->driver && $trip->driver->user_id) {
            $users[] = $trip->driver->user_id;
        }

        $this->sendToUsers($users, $event, array_merge([
            'trip_id' => $trip->id,
            'trip_state' => $trip->status,
        ], $data));
    }
}