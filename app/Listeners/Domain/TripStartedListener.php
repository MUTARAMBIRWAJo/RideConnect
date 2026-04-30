<?php

namespace App\Listeners\Domain;

use App\Events\Domain\TripStarted;
use App\Models\Trip;
use App\Services\Realtime\RealtimeGateway;
use Illuminate\Support\Facades\Log;
use Throwable;

class TripStartedListener
{
    public function __construct(private readonly RealtimeGateway $realtimeGateway)
    {
    }

    public function handle(TripStarted $event): void
    {
        try {
            $trip = Trip::query()->find($event->tripId);

            $this->realtimeGateway->broadcastTripUpdate($event->tripId, [
                'type' => 'trip_started',
                'trip_id' => $event->tripId,
            ]);

            if ($trip?->passenger_id) {
                $this->realtimeGateway->notifyPassenger((int) $trip->passenger_id, [
                    'type' => 'trip_started',
                    'trip_id' => $event->tripId,
                ]);
            }

            Log::info('Trip started event received', [
                'trip_id' => $event->tripId,
            ]);
        } catch (Throwable $throwable) {
            Log::error('TripStartedListener failed', [
                'trip_id' => $event->tripId,
                'error' => $throwable->getMessage(),
            ]);
        }
    }
}
