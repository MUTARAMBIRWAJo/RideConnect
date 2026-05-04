<?php

namespace App\Listeners\Domain;

use App\Events\Domain\TripMatched;
use App\Models\Trip;
use App\Services\NotificationService;
use App\Services\Realtime\RealtimeGateway;
use Illuminate\Support\Facades\Log;
use Throwable;

class TripMatchedListener
{
    public function __construct(
        private readonly RealtimeGateway $realtimeGateway,
        private readonly NotificationService $notificationService,
    ) {}

    public function handle(TripMatched $event): void
    {
        // Legacy listener no-op: Supabase broadcasting is handled by BroadcastTripEvents.
    }
}
