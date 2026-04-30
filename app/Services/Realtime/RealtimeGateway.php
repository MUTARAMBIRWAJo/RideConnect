<?php

namespace App\Services\Realtime;

class RealtimeGateway
{
    /**
     * Placeholder abstraction. Integration bindings (Firebase/WebSocket/etc.)
     * are intentionally deferred.
     */
    public function notifyDriver(int $driverId, array $payload): array
    {
        return [
            'channel' => 'driver',
            'recipient_id' => $driverId,
            'payload' => $payload,
            'dispatched' => false,
        ];
    }

    public function notifyPassenger(int $passengerId, array $payload): array
    {
        return [
            'channel' => 'passenger',
            'recipient_id' => $passengerId,
            'payload' => $payload,
            'dispatched' => false,
        ];
    }

    public function broadcastTripUpdate(int $tripId, array $payload): array
    {
        return [
            'channel' => 'trip',
            'trip_id' => $tripId,
            'payload' => $payload,
            'dispatched' => false,
        ];
    }
}
