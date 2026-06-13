<?php

namespace App\Services;

use App\Events\Domain\TripCompleted;
use App\Events\Domain\TripMatched;
use App\Events\Domain\TripStarted;
use App\Events\MotorcycleTripCompleted;
use App\Events\MotorcycleDriverArrived;
use App\Events\MotorcycleTripStarted;
use Illuminate\Support\Facades\Log;

class NotificationDispatcher
{
    public function __construct(
        private readonly PushNotificationService $pushNotificationService,
        private readonly \App\Services\Firebase\FirebaseSyncService $firebaseSyncService,
    ) {}

    /**
     * Dispatch notification based on event type
     */
    public function dispatch(object $event): void
    {
        try {
            match (true) {
                $event instanceof TripMatched => $this->handleTripMatched($event),
                $event instanceof TripStarted => $this->handleTripStarted($event),
                $event instanceof TripCompleted => $this->handleTripCompleted($event),
                $event instanceof MotorcycleTripStarted => $this->handleMotorcycleTripStarted($event),
                $event instanceof MotorcycleDriverArrived => $this->handleMotorcycleDriverArrived($event),
                $event instanceof MotorcycleTripCompleted => $this->handleMotorcycleTripCompleted($event),
                default => null,
            };
        } catch (\Throwable $e) {
            Log::error('Notification dispatch failed', [
                'event' => get_class($event),
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function handleTripMatched(TripMatched $event): void
    {
        $trip = \App\Models\Trip::find($event->tripId);
        if (!$trip || !$trip->passenger_id) {
            return;
        }

        // Send push notification to passenger
        $this->pushNotificationService->sendTripNotificationToPassenger(
            $trip->passenger_id,
            'driver_assigned',
            [
                'trip_id' => $trip->id,
                'driver_id' => $event->driverId,
            ]
        );

        // Send in-app notification via FirebaseSyncService
        $this->firebaseSyncService->syncEvent('DriverAssigned', [
            'trip_id' => $trip->id,
            'driver_id' => $event->driverId,
            'passenger_id' => $trip->passenger_id,
        ]);

        Log::info('Notifications dispatched for trip matched', ['trip_id' => $trip->id]);
    }

    private function handleTripStarted(TripStarted $event): void
    {
        $trip = \App\Models\Trip::find($event->tripId);
        if (!$trip || !$trip->passenger_id) {
            return;
        }

        // Send push notification to passenger
        $this->pushNotificationService->sendTripNotificationToPassenger(
            $trip->passenger_id,
            'trip_started',
            ['trip_id' => $trip->id]
        );

        // Send in-app notification via FirebaseSyncService
        $this->firebaseSyncService->syncEvent('TripStarted', [
            'trip_id' => $trip->id,
            'passenger_id' => $trip->passenger_id,
            'driver_id' => $trip->driver_id,
        ]);

        Log::info('Notifications dispatched for trip started', ['trip_id' => $trip->id]);
    }

    private function handleTripCompleted(TripCompleted $event): void
    {
        $trip = \App\Models\Trip::find($event->tripId);
        if (!$trip) {
            return;
        }

        // Send push notification to passenger
        if ($trip->passenger_id) {
            $this->pushNotificationService->sendTripNotificationToPassenger(
                $trip->passenger_id,
                'trip_completed',
                ['trip_id' => $trip->id]
            );

            $this->firebaseSyncService->syncEvent('TripCompleted', [
                'trip_id' => $trip->id,
                'passenger_id' => $trip->passenger_id,
                'driver_id' => $trip->driver_id,
            ]);
        }

        // Send push notification to driver
        if ($trip->driver_id) {
            $this->pushNotificationService->sendTripNotificationToDriver(
                $trip->driver_id,
                'payment_confirmed',
                ['trip_id' => $trip->id]
            );

            $this->firebaseSyncService->syncEvent('PaymentCompleted', [
                'trip_id' => $trip->id,
                'driver_id' => $trip->driver_id,
                'passenger_id' => $trip->passenger_id,
            ]);
        }

        Log::info('Notifications dispatched for trip completed', ['trip_id' => $trip->id]);
    }

    private function handleMotorcycleTripStarted(MotorcycleTripStarted $event): void
    {
        $trip = $event->trip;

        // Send push notification to passenger
        $this->pushNotificationService->sendTripNotificationToPassenger(
            $trip->passenger_id,
            'trip_started',
            ['trip_id' => $trip->id]
        );

        Log::info('Notifications dispatched for motorcycle trip started', ['trip_id' => $trip->id]);
    }

    private function handleMotorcycleDriverArrived(MotorcycleDriverArrived $event): void
    {
        $trip = $event->trip;

        // Send push notification to passenger
        $this->pushNotificationService->sendTripNotificationToPassenger(
            $trip->passenger_id,
            'driver_arrived',
            ['trip_id' => $trip->id]
        );

        Log::info('Notifications dispatched for driver arrived', ['trip_id' => $trip->id]);
    }

    private function handleMotorcycleTripCompleted(MotorcycleTripCompleted $event): void
    {
        $trip = $event->trip;

        // Send push notification to passenger
        $this->pushNotificationService->sendTripNotificationToPassenger(
            $trip->passenger_id,
            'trip_completed',
            ['trip_id' => $trip->id]
        );

        // Send push notification to driver
        $this->pushNotificationService->sendTripNotificationToDriver(
            $trip->driver_id,
            'payment_confirmed',
            ['trip_id' => $trip->id]
        );

        Log::info('Notifications dispatched for motorcycle trip completed', ['trip_id' => $trip->id]);
    }

    /**
     * Send custom notification
     */
    public function sendCustomNotification(
        int $userId,
        string $title,
        string $body,
        array $data = []
    ): array {
        return $this->pushNotificationService->sendToUser(
            $userId,
            $title,
            $body,
            $data
        );
    }
}
