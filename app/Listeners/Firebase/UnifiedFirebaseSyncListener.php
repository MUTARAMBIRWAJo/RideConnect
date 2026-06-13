<?php

namespace App\Listeners\Firebase;

use App\Events\Domain\TripCompleted;
use App\Events\Domain\TripMatched;
use App\Events\Domain\TripStarted;
use App\Events\Domain\PaymentVerified;
use App\Events\MotorcycleTripCompleted;
use App\Events\MotorcycleDriverArrived;
use App\Events\MotorcycleTripStarted;
use App\Events\DriverLocationUpdated;
use App\Services\Firebase\FirebaseSyncService;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Unified Firebase Sync Listener
 * 
 * This is the SINGLE listener for all Firebase sync operations.
 * All events flow through this listener to FirebaseSyncService::syncEvent()
 * 
 * This replaces:
 * - SyncTripEventsToFirebase
 * - SyncPaymentEventsToFirebase
 * - SyncRatingEventsToFirebase
 */
class UnifiedFirebaseSyncListener
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
                $event instanceof PaymentVerified => $this->handlePaymentVerified($event),
                $event instanceof MotorcycleTripStarted => $this->handleMotorcycleTripStarted($event),
                $event instanceof MotorcycleDriverArrived => $this->handleMotorcycleDriverArrived($event),
                $event instanceof MotorcycleTripCompleted => $this->handleMotorcycleTripCompleted($event),
                $event instanceof DriverLocationUpdated => $this->handleDriverLocationUpdated($event),
                default => $this->handleReviewCreated($event),
            };
        } catch (Throwable $e) {
            Log::error('Firebase sync failed for event', [
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

        $this->firebaseSyncService->syncEvent('TripCompleted', [
            'trip_id' => $trip->id,
            'passenger_id' => $trip->passenger_id,
            'driver_id' => $trip->driver_id,
        ]);

        Log::info('Firebase sync: Trip completed', ['trip_id' => $trip->id]);
    }

    private function handlePaymentVerified(PaymentVerified $event): void
    {
        $this->firebaseSyncService->syncEvent('PaymentCompleted', [
            'trip_id' => $event->tripId ?? 0,
            'payment_id' => $event->paymentId,
            'status' => 'completed',
            'verified_at' => now()->toIso8601String(),
        ]);

        Log::info('Firebase sync: Payment verified', ['payment_id' => $event->paymentId]);
    }

    private function handleMotorcycleTripStarted(MotorcycleTripStarted $event): void
    {
        $trip = $event->trip;

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

        $this->firebaseSyncService->syncEvent('TripCompleted', [
            'trip_id' => $trip->id,
            'passenger_id' => $trip->passenger_id,
            'driver_id' => $trip->driver_id,
            'fare' => $trip->actual_fare ?? $trip->estimated_fare,
        ]);

        Log::info('Firebase sync: Motorcycle trip completed', ['trip_id' => $trip->id]);
    }

    private function handleDriverLocationUpdated(DriverLocationUpdated $event): void
    {
        $this->firebaseSyncService->syncEvent('DriverLocationUpdated', [
            'driver_id' => $event->driverId,
            'trip_id' => $event->tripId,
            'latitude' => $event->latitude,
            'longitude' => $event->longitude,
            'accuracy' => $event->accuracy ?? 0,
        ]);

        Log::debug('Firebase sync: Driver location updated', [
            'driver_id' => $event->driverId,
        ]);
    }

    private function handleReviewCreated(object $event): void
    {
        // Handle Review model eloquent events
        if (method_exists($event, 'review') && $event->review instanceof \App\Models\Review) {
            $review = $event->review;
            
            $this->firebaseSyncService->syncEvent('RatingSubmitted', [
                'driver_id' => $review->driver_id,
                'trip_id' => $review->trip_id,
                'passenger_id' => $review->user_id,
                'rating' => $review->rating,
                'review' => $review->comment,
                'categories' => [],
            ]);

            Log::info('Firebase sync: Rating created', ['review_id' => $review->id]);
        }
    }
}
