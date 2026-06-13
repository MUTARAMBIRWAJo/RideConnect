<?php

namespace App\Listeners\Firebase;

use App\Events\Domain\TripCompleted;
use App\Events\Domain\TripMatched;
use App\Events\Domain\TripStarted;
use App\Events\MotorcycleTripCompleted;
use App\Events\MotorcycleDriverArrived;
use App\Events\MotorcycleTripStarted;
use App\Services\Firebase\FirebaseSyncService;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * DEPRECATED: This listener is being refactored to use FirebaseSyncService
 * 
 * All Firestore writes now go through FirebaseSyncService::syncEvent()
 * This is a transitional listener that will be removed after full migration
 */
class SyncTripEventsToFirebase
{
    public function __construct(
        private readonly FirebaseSyncService $firebaseSyncService,
    ) {}

    public function handle(object $event): void
    {
        try {
            if (!$this->firebaseSyncService->isEnabled()) {
                return;
            }

            match (true) {
                $event instanceof TripMatched => $this->handleTripMatched($event),
                $event instanceof TripStarted => $this->handleTripStarted($event),
                $event instanceof TripCompleted => $this->handleTripCompleted($event),
                $event instanceof MotorcycleTripStarted => $this->handleMotorcycleTripStarted($event),
                $event instanceof MotorcycleDriverArrived => $this->handleMotorcycleDriverArrived($event),
                $event instanceof MotorcycleTripCompleted => $this->handleMotorcycleTripCompleted($event),
                default => null,
            };
        } catch (Throwable $e) {
            Log::error('Firebase sync failed for trip event', [
                'event' => get_class($event),
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function handleTripMatched(TripMatched $event): void
    {
        $trip = \App\Models\Trip::find($event->tripId);
        if (!$trip) {
            return;
        }

        // Use central FirebaseSyncService
        $this->firebaseSyncService->syncEvent('DriverAssigned', [
            'trip_id' => $trip->id,
            'driver_id' => $event->driverId,
            'passenger_id' => $trip->passenger_id,
        ]);

        Log::info('Firebase sync: Trip matched', ['trip_id' => $trip->id]);
    }

    private function handleTripStarted(TripStarted $event): void
    {
        $trip = \App\Models\Trip::find($event->tripId);
        if (!$trip) {
            return;
        }

        // Use central FirebaseSyncService
        $this->firebaseSyncService->syncEvent('TripStarted', [
            'trip_id' => $trip->id,
            'passenger_id' => $trip->passenger_id,
            'driver_id' => $trip->driver_id,
        ]);

        Log::info('Firebase sync: Trip started', ['trip_id' => $trip->id]);
    }

    private function handleTripCompleted(TripCompleted $event): void
    {
        $trip = \App\Models\Trip::find($event->tripId);
        if (!$trip) {
            return;
        }

        // Use central FirebaseSyncService
        $this->firebaseSyncService->syncEvent('TripCompleted', [
            'trip_id' => $trip->id,
            'passenger_id' => $trip->passenger_id,
            'driver_id' => $trip->driver_id,
        ]);

        Log::info('Firebase sync: Trip completed', ['trip_id' => $trip->id]);
    }

    private function handleMotorcycleTripStarted(MotorcycleTripStarted $event): void
    {
        $trip = $event->trip;

        // Use central FirebaseSyncService
        $this->firebaseSyncService->syncEvent('TripStarted', [
            'trip_id' => $trip->id,
            'passenger_id' => $trip->passenger_id,
            'driver_id' => $trip->driver_id,
        ]);

        Log::info('Firebase sync: Motorcycle trip started', ['trip_id' => $trip->id]);
    }

    private function handleMotorcycleDriverArrived(MotorcycleDriverArrived $event): void
    {
        $trip = $event->trip;

        // Use central FirebaseSyncService - map to driver_arrived event
        $this->firebaseSyncService->syncEvent('DriverAssigned', [
            'trip_id' => $trip->id,
            'driver_id' => $trip->driver_id,
            'passenger_id' => $trip->passenger_id,
            'event_type' => 'driver_arrived',
        ]);

        Log::info('Firebase sync: Driver arrived', ['trip_id' => $trip->id]);
    }

    private function handleMotorcycleTripCompleted(MotorcycleTripCompleted $event): void
    {
        $trip = $event->trip;

        // Use central FirebaseSyncService
        $this->firebaseSyncService->syncEvent('TripCompleted', [
            'trip_id' => $trip->id,
            'passenger_id' => $trip->passenger_id,
            'driver_id' => $trip->driver_id,
            'fare' => $trip->actual_fare ?? $trip->estimated_fare,
        ]);

        Log::info('Firebase sync: Motorcycle trip completed', ['trip_id' => $trip->id]);
    }
}
