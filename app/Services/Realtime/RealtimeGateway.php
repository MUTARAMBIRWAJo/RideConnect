<?php

namespace App\Services\Realtime;

use App\Services\SupabaseClient;
use Illuminate\Support\Facades\Log;

class RealtimeGateway
{
    public function __construct(private readonly SupabaseClient $supabaseClient)
    {
    }

    public function broadcast(string $channel, string $event, array $payload): void
    {
        try {
            $this->supabaseClient->broadcast($channel, $event, $payload);
        } catch (\Throwable $throwable) {
            Log::error('Supabase realtime broadcast failed', [
                'channel' => $channel,
                'event' => $event,
                'error' => $throwable->getMessage(),
            ]);
        }
    }

    public function notifyDriver(int $driverId, array $payload): array
    {
        $this->broadcast("driver:{$driverId}", 'driver.notification', $payload);

        return [
            'channel' => "driver:{$driverId}",
            'recipient_id' => $driverId,
            'payload' => $payload,
            'dispatched' => true,
        ];
    }

    public function notifyPassenger(int $passengerId, array $payload): array
    {
        $this->broadcast("passenger:{$passengerId}", 'passenger.notification', $payload);

        return [
            'channel' => "passenger:{$passengerId}",
            'recipient_id' => $passengerId,
            'payload' => $payload,
            'dispatched' => true,
        ];
    }

    public function broadcastTripUpdate(int $tripId, array $payload): array
    {
        $this->broadcast("trip:{$tripId}", 'trip.update', $payload);

        return [
            'channel' => "trip:{$tripId}",
            'trip_id' => $tripId,
            'payload' => $payload,
            'dispatched' => true,
        ];
    }
}
