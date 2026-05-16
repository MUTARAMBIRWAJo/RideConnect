<?php

namespace App\Listeners\Domain;

use App\Events\Domain\TripStarted;
use App\Services\Realtime\RealtimeGateway;

class TripStartedListener
{
    public function __construct(private readonly RealtimeGateway $realtimeGateway) {}

    public function handle(TripStarted $event): void
    {
        // Legacy listener no-op: Supabase broadcasting is handled by BroadcastTripEvents.
    }
}
