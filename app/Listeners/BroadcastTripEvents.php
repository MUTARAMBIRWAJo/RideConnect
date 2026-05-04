<?php

namespace App\Listeners;

use App\Events\Domain\TripCompleted;
use App\Events\Domain\TripMatched;
use App\Events\Domain\TripStarted;
use App\Models\Trip;
use App\Services\Realtime\RealtimeGateway;
use Illuminate\Support\Facades\Log;
use Throwable;

class BroadcastTripEvents
{
    public function __construct(private readonly RealtimeGateway $realtimeGateway)
    {
    }

    public function handle(object $event): void
    {
        try {
            if ($event instanceof TripMatched) {
                $this->handleTripMatched($event);
                return;
            }

            if ($event instanceof TripStarted) {
                $this->handleTripStarted($event);
                return;
            }

            if ($event instanceof TripCompleted) {
                $this->handleTripCompleted($event);
                return;
            }
        } catch (Throwable $throwable) {
            Log::error('BroadcastTripEvents failed', [
                'event' => get_class($event),
                'error' => $throwable->getMessage(),
            ]);
        }
    }

    private function handleTripMatched(TripMatched $event): void
    {
        $trip = Trip::find($event->tripId);
        $pickup = $trip?->pickup_location ?? 'Unknown pickup location';

        $this->realtimeGateway->broadcast(
            "driver:{$event->driverId}",
            'trip.request',
            [
                'trip_id' => $event->tripId,
                'pickup' => $pickup,
            ]
        );
    }

    private function handleTripStarted(TripStarted $event): void
    {
        $this->realtimeGateway->broadcast(
            "trip:{$event->tripId}",
            'trip.started',
            [
                'trip_id' => $event->tripId,
            ]
        );
    }

    private function handleTripCompleted(TripCompleted $event): void
    {
        $trip = Trip::find($event->tripId);

        $this->realtimeGateway->broadcast(
            "trip:{$event->tripId}",
            'trip.completed',
            [
                'trip_id' => $event->tripId,
            ]
        );

        if ($trip?->passenger_id) {
            $this->realtimeGateway->broadcast(
                "passenger:{$trip->passenger_id}",
                'trip.completed',
                [
                    'trip_id' => $event->tripId,
                ]
            );
        }
    }
}
