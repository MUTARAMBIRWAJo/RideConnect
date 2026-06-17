<?php

namespace App\Services\V3;

use Illuminate\Support\Facades\Log;

class NotificationServiceV3
{
    public function sendToDriver(int $driverId, array $payload): void
    {
        // Notification is handled by Supabase Edge Functions / DB Triggers natively.
        Log::info("Supabase Notification Triggered for Driver [{$driverId}]", $payload);
    }

    public function sendToPassenger(int $passengerId, array $payload): void
    {
        // Notification is handled by Supabase Edge Functions / DB Triggers natively.
        Log::info("Supabase Notification Triggered for Passenger [{$passengerId}]", $payload);
    }
}
