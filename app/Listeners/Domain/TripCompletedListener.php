<?php

namespace App\Listeners\Domain;

use App\Events\Domain\TripCompleted;
use App\Services\Realtime\RealtimeGateway;

class TripCompletedListener
{
    public function __construct(private readonly RealtimeGateway $realtimeGateway) {}

    public function handle(TripCompleted $event): void
    {
        // Legacy listener no-op: Supabase broadcasting is handled by BroadcastTripEvents.
    }
}
