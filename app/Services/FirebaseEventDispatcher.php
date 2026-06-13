<?php

namespace App\Services;

use App\Models\Trip;
use App\Models\MotorcycleTrip;
use App\Models\Driver;
use App\Models\User;
use Illuminate\Support\Facades\Log;

/**
 * DEPRECATED: This service is being replaced by FirebaseSyncService
 * 
 * All Firestore writes should now go through FirebaseSyncService::syncEvent()
 * This service is kept for backward compatibility during migration
 * 
 * @deprecated Use App\Services\Firebase\FirebaseSyncService instead
 */
class FirebaseEventDispatcher
{
    public function __construct(
        private readonly FirebaseSync $firebaseSync,
        private readonly FirebaseRealtimeService $firebaseRealtime,
    ) {
        Log::warning('[FirebaseEventDispatcher] DEPRECATED - Use FirebaseSyncService instead');
    }

    /**
     * Dispatch trip creation event to Firebase
     */
    public function dispatchTripCreated(Trip $trip): void
    {
        try {
            $this->firebaseSync->syncTripCreation($trip);
            $this->firebaseRealtime->pushTripEvent($trip->id, 'trip_created', [
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
     */
    public function dispatchDriverAssigned(int $tripId, int $driverId): void
    {
        try {
            $this->firebaseRealtime->pushTripEvent($tripId, 'driver_assigned', [
                'driver_id' => $driverId,
                'assigned_at' => now()->toIso8601String(),
            ]);
            
            $this->firebaseSync->syncDriverStatus($driverId, 'on_trip');
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
     */
    public function dispatchDriverAccepted(int $tripId, int $driverId): void
    {
        try {
            $this->firebaseRealtime->pushTripEvent($tripId, 'driver_accepted', [
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
     */
    public function dispatchDriverRejected(int $tripId, int $driverId, string $reason): void
    {
        try {
            $this->firebaseRealtime->pushTripEvent($tripId, 'driver_rejected', [
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
     */
    public function dispatchDriverArrived(int $tripId, int $driverId): void
    {
        try {
            $this->firebaseSync->syncTripStatusUpdate($tripId, 'driver_arrived');
            $this->firebaseRealtime->pushTripEvent($tripId, 'driver_arrived', [
                'driver_id' => $driverId,
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
     */
    public function dispatchTripStarted(int $tripId): void
    {
        try {
            $this->firebaseSync->syncTripStatusUpdate($tripId, 'in_progress');
            $this->firebaseRealtime->pushTripEvent($tripId, 'trip_started', [
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
     */
    public function dispatchTripCompleted(int $tripId, array $paymentData = []): void
    {
        try {
            $this->firebaseSync->syncTripCompletion($tripId, $paymentData);
            $this->firebaseRealtime->pushTripEvent($tripId, 'trip_completed', [
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
     */
    public function dispatchPaymentCompleted(int $tripId, int $paymentId): void
    {
        try {
            $this->firebaseRealtime->pushTripEvent($tripId, 'payment_completed', [
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
     */
    public function dispatchRatingSubmitted(int $driverId, array $ratingData): void
    {
        try {
            $this->firebaseSync->syncRatingCreation($driverId, $ratingData);
            $this->firebaseRealtime->pushTripEvent($ratingData['trip_id'] ?? 0, 'rating_submitted', [
                'driver_id' => $driverId,
                'rating' => $ratingData['rating'] ?? 0,
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
     */
    public function dispatchUserUpdated(User $user): void
    {
        try {
            $this->firebaseSync->syncUserProfileUpdate($user);
        } catch (\Throwable $e) {
            Log::error('Failed to dispatch user updated event', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Dispatch driver profile update to Firebase
     */
    public function dispatchDriverUpdated(Driver $driver): void
    {
        try {
            $this->firebaseSync->syncDriverProfileCreation($driver);
        } catch (\Throwable $e) {
            Log::error('Failed to dispatch driver updated event', [
                'driver_id' => $driver->user_id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Dispatch driver location update to Firebase
     */
    public function dispatchDriverLocationUpdated(int $driverId, float $latitude, float $longitude, float $accuracy = 0): void
    {
        try {
            $this->firebaseSync->syncDriverLocation($driverId, $latitude, $longitude, $accuracy);
        } catch (\Throwable $e) {
            Log::error('Failed to dispatch driver location updated event', [
                'driver_id' => $driverId,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
