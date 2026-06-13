<?php

namespace App\Services;

use App\Services\Firebase\FirebaseSyncService;
use Illuminate\Support\Facades\Log;

/**
 * FirebaseSync Service - COMPATIBILITY WRAPPER
 *
 * DEPRECATED: This service is now a thin wrapper delegating to FirebaseSyncService
 * All Firestore writes now go through FirebaseSyncService
 *
 * @deprecated Use App\Services\Firebase\FirebaseSyncService instead
 */
class FirebaseSync
{
    public function __construct(
        private readonly FirebaseSyncService $firebaseSyncService,
    ) {
        Log::warning('[FirebaseSync] DEPRECATED - Use FirebaseSyncService instead');
    }

    /**
     * Check if Firebase is enabled and ready
     */
    public function isEnabled(): bool
    {
        return $this->firebaseSyncService->isEnabled();
    }

    // ==================== USER SYNC ====================

    /**
     * Sync user creation from Supabase to Firebase
     * @deprecated Use FirebaseSyncService::syncEvent('UserCreated', ...)
     */
    public function syncUserCreation($user): bool
    {
        return $this->firebaseSyncService->syncEvent('UserCreated', [
            'user_id' => $user->id,
        ]);
    }

    /**
     * Sync user profile update
     * @deprecated Use FirebaseSyncService directly
     */
    public function syncUserProfileUpdate($user): bool
    {
        // Delegate to syncEvent with UserUpdated type
        return $this->firebaseSyncService->syncEvent('UserUpdated', [
            'user_id' => $user->id,
            'email' => $user->email,
            'name' => $user->name,
            'phone' => $user->phone,
        ]);
    }

    /**
     * Sync user status (online/offline)
     * @deprecated Use FirebaseSyncService directly
     */
    public function syncUserStatus(string $userId, bool $isOnline): bool
    {
        return $this->firebaseSyncService->syncEvent('UserStatusUpdated', [
            'user_id' => $userId,
            'is_online' => $isOnline,
        ]);
    }

    // ==================== DRIVER SYNC ====================

    /**
     * Sync driver profile creation
     * @deprecated Use FirebaseSyncService::syncEvent('DriverCreated', ...)
     */
    public function syncDriverProfileCreation($driver): bool
    {
        return $this->firebaseSyncService->syncEvent('DriverCreated', [
            'driver_id' => $driver->user_id,
        ]);
    }

    /**
     * Sync driver status change
     * @deprecated Use FirebaseSyncService::syncEvent('DriverStatusUpdated', ...)
     */
    public function syncDriverStatus(string $driverId, string $status): bool
    {
        return $this->firebaseSyncService->syncEvent('DriverStatusUpdated', [
            'driver_id' => $driverId,
            'status' => $status,
        ]);
    }

    /**
     * Sync driver location update
     * @deprecated Use FirebaseSyncService::syncDriverLocation()
     */
    public function syncDriverLocation(string $driverId, float $latitude, float $longitude, float $accuracy = 0): bool
    {
        return $this->firebaseSyncService->syncDriverLocation($driverId, $latitude, $longitude, $accuracy);
    }

    // ==================== TRIP SYNC ====================

    /**
     * Sync trip creation
     * @deprecated Use FirebaseSyncService::syncEvent('TripCreated', ...)
     */
    public function syncTripCreation($trip): bool
    {
        return $this->firebaseSyncService->syncEvent('TripCreated', [
            'trip_id' => $trip->id,
        ]);
    }

    /**
     * Sync trip status update
     * @deprecated Use FirebaseSyncService::syncEvent() with appropriate event type
     */
    public function syncTripStatusUpdate(string $tripId, string $status): bool
    {
        $eventMap = [
            'accepted' => 'DriverAssigned',
            'driver_arriving' => 'DriverAssigned',
            'arrived' => 'DriverAssigned',
            'in_progress' => 'TripStarted',
            'completed' => 'TripCompleted',
            'cancelled' => 'TripCancelled',
        ];

        $eventType = $eventMap[$status] ?? 'TripStatusUpdated';
        return $this->firebaseSyncService->syncEvent($eventType, [
            'trip_id' => $tripId,
            'status' => $status,
        ]);
    }

    /**
     * Sync trip completion
     * @deprecated Use FirebaseSyncService::syncEvent('TripCompleted', ...)
     */
    public function syncTripCompletion(string $tripId, array $paymentData): bool
    {
        return $this->firebaseSyncService->syncEvent('TripCompleted', [
            'trip_id' => $tripId,
            'payment_data' => $paymentData,
        ]);
    }

    // ==================== RATING SYNC ====================

    /**
     * Sync rating creation
     * @deprecated Use FirebaseSyncService::syncEvent('RatingSubmitted', ...)
     */
    public function syncRatingCreation(string $driverId, array $ratingData): bool
    {
        return $this->firebaseSyncService->syncEvent('RatingSubmitted', [
            'driver_id' => $driverId,
            'rating_data' => $ratingData,
        ]);
    }

    // ==================== BATCH OPERATIONS ====================

    /**
     * Batch sync multiple documents
     * @deprecated Use FirebaseSyncService directly
     */
    public function batchSync(array $operations): bool
    {
        Log::warning('[FirebaseSync] batchSync called - delegating to FirebaseSyncService');
        // FirebaseSyncService doesn't have a direct batchSync method
        // This would need to be implemented or operations should be converted to individual syncEvent calls
        foreach ($operations as $op) {
            $this->firebaseSyncService->syncEvent('BatchOperation', $op);
        }
        return true;
    }

    // ==================== HEALTH CHECK ====================

    /**
     * Check Firebase connection health
     * @deprecated Use FirebaseSyncService::healthCheck()
     */
    public function healthCheck(): array
    {
        return $this->firebaseSyncService->healthCheck();
    }
}
