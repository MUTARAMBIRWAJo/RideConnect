<?php

namespace App\Services;

use App\Models\Trip;
use App\Models\MotorcycleTrip;
use App\Models\Driver;
use App\Models\User;
use App\Services\Firebase\FirebaseSyncService;
use Illuminate\Support\Facades\Log;

/**
 * FirebaseEventDispatcher - COMPATIBILITY WRAPPER
 *
 * DEPRECATED: This service is now a thin wrapper delegating to FirebaseSyncService
 * All Firestore writes now go through FirebaseSyncService::syncEvent()
 *
 * @deprecated Use App\Services\Firebase\FirebaseSyncService instead
 */
class FirebaseEventDispatcher
{
    public function __construct(
        private readonly FirebaseSyncService $firebaseSyncService,
    ) {
        Log::warning('[FirebaseEventDispatcher] DEPRECATED - Use FirebaseSyncService instead');
    }

    /**
     * Dispatch trip creation event to Firebase
     * @deprecated Use FirebaseSyncService::syncEvent('TripCreated', ...)
     */
    public function dispatchTripCreated(Trip $trip): void
    {
        try {
            $this->firebaseSyncService->syncEvent('TripCreated', [
                'trip_id' => $trip->id,
                'created_at' => now()->toIso8601String(),
            ]);
        } catch (\Throwable $e) {
            Log::error('Failed to dispatch trip created event', [
                'trip_id' => $trip->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Dispatch driver assignment event to Firebase
     * @deprecated Use FirebaseSyncService::syncEvent('DriverAssigned', ...)
     */
    public function dispatchDriverAssigned(int $tripId, int $driverId): void
    {
        try {
            $this->firebaseSyncService->syncEvent('DriverAssigned', [
                'trip_id' => $tripId,
                'driver_id' => $driverId,
                'assigned_at' => now()->toIso8601String(),
            ]);
        } catch (\Throwable $e) {
            Log::error('Failed to dispatch driver assigned event', [
                'trip_id' => $tripId,
                'driver_id' => $driverId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Dispatch driver accepted event to Firebase
     * @deprecated Use FirebaseSyncService::syncEvent('DriverAccepted', ...)
     */
    public function dispatchDriverAccepted(int $tripId, int $driverId): void
    {
        try {
            $this->firebaseSyncService->syncEvent('DriverAccepted', [
                'trip_id' => $tripId,
                'driver_id' => $driverId,
                'accepted_at' => now()->toIso8601String(),
            ]);
        } catch (\Throwable $e) {
            Log::error('Failed to dispatch driver accepted event', [
                'trip_id' => $tripId,
                'driver_id' => $driverId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Dispatch driver rejected event to Firebase
     * @deprecated Use FirebaseSyncService::syncEvent('DriverRejected', ...)
     */
    public function dispatchDriverRejected(int $tripId, int $driverId, string $reason): void
    {
        try {
            $this->firebaseSyncService->syncEvent('DriverRejected', [
                'trip_id' => $tripId,
                'driver_id' => $driverId,
                'reason' => $reason,
                'rejected_at' => now()->toIso8601String(),
            ]);
        } catch (\Throwable $e) {
            Log::error('Failed to dispatch driver rejected event', [
                'trip_id' => $tripId,
                'driver_id' => $driverId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Dispatch driver arrived event to Firebase
     * @deprecated Use FirebaseSyncService::syncEvent('DriverAssigned', ...) with event_type
     */
    public function dispatchDriverArrived(int $tripId, int $driverId): void
    {
        try {
            $this->firebaseSyncService->syncEvent('DriverAssigned', [
                'trip_id' => $tripId,
                'driver_id' => $driverId,
                'event_type' => 'driver_arrived',
                'arrived_at' => now()->toIso8601String(),
            ]);
        } catch (\Throwable $e) {
            Log::error('Failed to dispatch driver arrived event', [
                'trip_id' => $tripId,
                'driver_id' => $driverId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Dispatch trip started event to Firebase
     * @deprecated Use FirebaseSyncService::syncEvent('TripStarted', ...)
     */
    public function dispatchTripStarted(int $tripId): void
    {
        try {
            $this->firebaseSyncService->syncEvent('TripStarted', [
                'trip_id' => $tripId,
                'started_at' => now()->toIso8601String(),
            ]);
        } catch (\Throwable $e) {
            Log::error('Failed to dispatch trip started event', [
                'trip_id' => $tripId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Dispatch trip completed event to Firebase
     * @deprecated Use FirebaseSyncService::syncEvent('TripCompleted', ...)
     */
    public function dispatchTripCompleted(int $tripId, array $paymentData = []): void
    {
        try {
            $this->firebaseSyncService->syncEvent('TripCompleted', [
                'trip_id' => $tripId,
                'completed_at' => now()->toIso8601String(),
                'payment_data' => $paymentData,
            ]);
        } catch (\Throwable $e) {
            Log::error('Failed to dispatch trip completed event', [
                'trip_id' => $tripId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Dispatch payment completed event to Firebase
     * @deprecated Use FirebaseSyncService::syncEvent('PaymentCompleted', ...)
     */
    public function dispatchPaymentCompleted(int $tripId, int $paymentId): void
    {
        try {
            $this->firebaseSyncService->syncEvent('PaymentCompleted', [
                'trip_id' => $tripId,
                'payment_id' => $paymentId,
                'completed_at' => now()->toIso8601String(),
            ]);
        } catch (\Throwable $e) {
            Log::error('Failed to dispatch payment completed event', [
                'trip_id' => $tripId,
                'payment_id' => $paymentId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Dispatch rating submitted event to Firebase
     * @deprecated Use FirebaseSyncService::syncEvent('RatingSubmitted', ...)
     */
    public function dispatchRatingSubmitted(int $driverId, array $ratingData): void
    {
        try {
            $this->firebaseSyncService->syncEvent('RatingSubmitted', [
                'driver_id' => $driverId,
                'rating_data' => $ratingData,
                'submitted_at' => now()->toIso8601String(),
            ]);
        } catch (\Throwable $e) {
            Log::error('Failed to dispatch rating submitted event', [
                'driver_id' => $driverId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Dispatch user profile update to Firebase
     * @deprecated Use FirebaseSyncService::syncEvent('UserUpdated', ...)
     */
    public function dispatchUserUpdated(User $user): void
    {
        try {
            $this->firebaseSyncService->syncEvent('UserUpdated', [
                'user_id' => $user->id,
                'email' => $user->email,
                'name' => $user->name,
                'phone' => $user->phone,
            ]);
        } catch (\Throwable $e) {
            Log::error('Failed to dispatch user updated event', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Dispatch driver profile update to Firebase
     * @deprecated Use FirebaseSyncService::syncEvent('DriverCreated', ...)
     */
    public function dispatchDriverUpdated(Driver $driver): void
    {
        try {
            $this->firebaseSyncService->syncEvent('DriverCreated', [
                'driver_id' => $driver->user_id,
            ]);
        } catch (\Throwable $e) {
            Log::error('Failed to dispatch driver updated event', [
                'driver_id' => $driver->user_id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Dispatch driver location update to Firebase
     * @deprecated Use FirebaseSyncService::syncDriverLocation()
     */
    public function dispatchDriverLocationUpdated(int $driverId, float $latitude, float $longitude, float $accuracy = 0): void
    {
        try {
            $this->firebaseSyncService->syncDriverLocation($driverId, $latitude, $longitude, $accuracy);
        } catch (\Throwable $e) {
            Log::error('Failed to dispatch driver location updated event', [
                'driver_id' => $driverId,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
