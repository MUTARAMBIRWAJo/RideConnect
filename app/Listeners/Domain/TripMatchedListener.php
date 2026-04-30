<?php

namespace App\Listeners\Domain;

use App\Events\Domain\TripMatched;
use App\Models\Trip;
use App\Services\Realtime\RealtimeGateway;
use Illuminate\Support\Facades\Log;
use Throwable;

class TripMatchedListener
{
    public function __construct(private readonly RealtimeGateway $realtimeGateway)
    {
    }

    public function handle(TripMatched $event): void
    {
        try {
            $trip = Trip::query()->find($event->tripId);

            $this->realtimeGateway->broadcastTripUpdate($event->tripId, [
                'type' => 'trip_matched',
                'trip_id' => $event->tripId,
                'driver_id' => $event->driverId,
            ]);

            if ($trip?->passenger_id) {
                $this->realtimeGateway->notifyPassenger((int) $trip->passenger_id, [
                    'type' => 'trip_matched',
                    'trip_id' => $event->tripId,
                    'driver_id' => $event->driverId,
                ]);
            }

            Log::info('Trip matched event received', [
                'trip_id' => $event->tripId,
                'driver_id' => $event->driverId,
            ]);
        } catch (Throwable $throwable) {
            Log::error('TripMatchedListener failed', [
                'trip_id' => $event->tripId,
                'error' => $throwable->getMessage(),
            ]);
        }
    }
}
