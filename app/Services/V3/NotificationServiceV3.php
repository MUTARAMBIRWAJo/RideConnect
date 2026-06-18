<?php

namespace App\Services\V3;

use Illuminate\Support\Facades\Log;

class NotificationServiceV3
{
    public function sendToDriver(int $driverId, array $payload): void
    {
        // Notification is handled by Supabase Edge Functions / DB Triggers natively.
        Log::info("Supabase Notification Triggered for Driver [{$driverId}]", $payload);

        // Insert into trip_events_v3 so driver apps subscribed to Realtime get it
        if (isset($payload['trip_id'])) {
            \Illuminate\Support\Facades\DB::table('trip_events_v3')->insert([
                'id' => (string) \Illuminate\Support\Str::uuid(),
                'trip_id' => $payload['trip_id'],
                'event_type' => $payload['type'] ?? 'NEW_TRIP_REQUEST',
                'payload' => json_encode(array_merge($payload, ['target_user_type' => 'driver', 'target_user_id' => $driverId])),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function sendToPassenger(int $passengerId, array $payload): void
    {
        // Notification is handled by Supabase Edge Functions / DB Triggers natively.
        Log::info("Supabase Notification Triggered for Passenger [{$passengerId}]", $payload);

        // Insert into trip_events_v3 so passenger apps subscribed to Realtime get it
        if (isset($payload['trip_id'])) {
            \Illuminate\Support\Facades\DB::table('trip_events_v3')->insert([
                'id' => (string) \Illuminate\Support\Str::uuid(),
                'trip_id' => $payload['trip_id'],
                'event_type' => $payload['type'] ?? 'PASSENGER_NOTIFICATION',
                'payload' => json_encode(array_merge($payload, ['target_user_type' => 'passenger', 'target_user_id' => $passengerId])),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}
