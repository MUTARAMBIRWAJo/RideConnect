<?php

namespace App\Services\V3;

use App\Events\V3\TripLifecycleEventV3;
use App\Models\Driver;
use App\Models\V3\TripV3;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TripLifecycleNotifierV3
{
    public function dispatch(TripV3 $trip, string $eventName, array $payload, ?Driver $driver = null): void
    {
        DB::table('trip_events_v3')->insert([
            'id' => (string) Str::uuid(),
            'trip_id' => $trip->id,
            'event_type' => $eventName,
            'payload' => json_encode($payload),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        broadcast(new TripLifecycleEventV3(
            $trip,
            $eventName,
            $payload,
            $driver?->user_id,
        ));
    }
}
