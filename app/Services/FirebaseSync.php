<?php

namespace App\Services;

use Kreait\Firebase\Factory;
use Kreait\Firebase\Firestore;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Exception;

/**
 * FirebaseSync Service
 *
 * Synchronizes data from Supabase PostgreSQL to Firebase Firestore
 * Direction: Supabase → Firebase (one-way write)
 *
 * Supabase is the source of truth, Firebase is the real-time cache
 */
class FirebaseSync
{
    private ?Firestore $firestore = null;
    private bool $enabled = false;
    private int $maxRetries;
    private int $retryDelay;

    public function __construct()
    {
        $this->maxRetries = config('firebase.sync.max_retries', 3);
        $this->retryDelay = config('firebase.sync.retry_delay', 5);

        $this->initialize();
    }

    /**
     * Initialize Firebase connection
     */
    private function initialize(): void
    {
        if (!config('firebase.enabled')) {
            Log::debug('[Firebase] Firestore sync disabled in configuration');
            return;
        }

        try {
            $projectId = config('firebase.project_id');
            $credentialsPath = config('firebase.credentials');

            if (!$projectId) {
                throw new Exception('Firebase project ID not configured');
            }

            if (!$credentialsPath || !file_exists($credentialsPath)) {
                throw new Exception("Firebase credentials file not found: {$credentialsPath}");
            }

            $factory = (new Factory)
                ->withServiceAccount($credentialsPath)
                ->withProjectId($projectId);

            $firestoreDb = config('firebase.firestore_database', '(default)');
            $this->firestore = $factory->createFirestore()->database($firestoreDb);

            $this->enabled = true;

            Log::info('[Firebase] Firestore initialized successfully', [
                'project_id' => $projectId,
                'firestore_db' => $firestoreDb,
            ]);
        } catch (Exception $e) {
            Log::warning('[Firebase] Initialization failed: ' . $e->getMessage());
            $this->enabled = false;
        }
    }

    /**
     * Check if Firebase is enabled and ready
     */
    public function isEnabled(): bool
    {
        return $this->enabled && $this->firestore !== null;
    }

    // ==================== USER SYNC ====================

    /**
     * Sync user creation from Supabase to Firebase
     */
    public function syncUserCreation($user): bool
    {
        if (!$this->isEnabled()) {
            return false;
        }

        try {
            $this->firestore
                ->collection('users')
                ->document((string) $user->id)
                ->set([
                    'email' => $user->email,
                    'name' => $user->name,
                    'phone' => $user->phone,
                    'role' => $user->role->value,
                    'is_online' => false,
                    'last_seen' => now(),
                    'rating' => 0.0,
                    'completed_trips' => 0,
                    'cancelled_trips' => 0,
                    'metadata' => [
                        'created_at' => now(),
                        'updated_at' => now(),
                        'firebase_token' => null,
                        'app_version' => '1.0.0',
                    ],
                ], ['merge' => true]);

            if (config('firebase.logging.log_success')) {
                Log::info('[Firebase] User synced successfully', ['user_id' => $user->id]);
            }

            return true;
        } catch (Exception $e) {
            Log::error('[Firebase] User sync failed', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Sync user profile update
     */
    public function syncUserProfileUpdate($user): bool
    {
        if (!$this->isEnabled()) {
            return false;
        }

        try {
            $this->firestore
                ->collection('users')
                ->document((string) $user->id)
                ->update([
                    ['path' => 'email', 'value' => $user->email],
                    ['path' => 'name', 'value' => $user->name],
                    ['path' => 'phone', 'value' => $user->phone],
                    ['path' => 'metadata.updated_at', 'value' => now()],
                ]);

            if (config('firebase.logging.log_success')) {
                Log::info('[Firebase] User profile updated', ['user_id' => $user->id]);
            }

            return true;
        } catch (Exception $e) {
            Log::error('[Firebase] User profile update failed', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Sync user status (online/offline)
     */
    public function syncUserStatus(string $userId, bool $isOnline): bool
    {
        if (!$this->isEnabled()) {
            return false;
        }

        try {
            $this->firestore
                ->collection('users')
                ->document($userId)
                ->update([
                    ['path' => 'is_online', 'value' => $isOnline],
                    ['path' => 'last_seen', 'value' => now()],
                ]);

            return true;
        } catch (Exception $e) {
            Log::error('[Firebase] User status update failed', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    // ==================== DRIVER SYNC ====================

    /**
     * Sync driver profile creation
     */
    public function syncDriverProfileCreation($driver): bool
    {
        if (!$this->isEnabled()) {
            return false;
        }

        try {
            $this->firestore
                ->collection('drivers')
                ->document((string) $driver->user_id)
                ->set([
                    'user_id' => $driver->user_id,
                    'status' => 'offline',
                    'current_location' => [
                        'latitude' => 0,
                        'longitude' => 0,
                        'accuracy' => 0,
                        'updated_at' => now(),
                    ],
                    'current_trip_id' => null,
                    'vehicle' => [
                        'type' => $driver->vehicle_type ?? 'economy',
                        'license_plate' => $driver->license_plate ?? '',
                        'color' => $driver->vehicle_color ?? '',
                        'model' => $driver->vehicle_model ?? '',
                    ],
                    'service_types' => ['private_car'],
                    'response_time' => 0,
                    'acceptance_rate' => 0,
                    'cancellation_rate' => 0,
                    'average_rating' => 0.0,
                    'total_earnings' => 0,
                    'available_capacity' => $driver->capacity ?? 1,
                    'metadata' => [
                        'last_location_update' => now(),
                        'shift_start' => null,
                        'shift_end' => null,
                        'offline_reason' => null,
                    ],
                ]);

            if (config('firebase.logging.log_success')) {
                Log::info('[Firebase] Driver profile synced', ['driver_id' => $driver->user_id]);
            }

            return true;
        } catch (Exception $e) {
            Log::error('[Firebase] Driver profile sync failed', [
                'driver_id' => $driver->user_id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Sync driver status change
     */
    public function syncDriverStatus(string $driverId, string $status): bool
    {
        if (!$this->isEnabled()) {
            return false;
        }

        try {
            $update = [
                ['path' => 'status', 'value' => $status],
                ['path' => 'metadata.last_location_update', 'value' => now()],
            ];

            if ($status === 'offline') {
                $update[] = ['path' => 'metadata.shift_end', 'value' => now()];
            } elseif ($status === 'online') {
                $update[] = ['path' => 'metadata.shift_start', 'value' => now()];
            }

            $this->firestore
                ->collection('drivers')
                ->document($driverId)
                ->update($update);

            return true;
        } catch (Exception $e) {
            Log::error('[Firebase] Driver status update failed', [
                'driver_id' => $driverId,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Sync driver location update
     */
    public function syncDriverLocation(string $driverId, float $latitude, float $longitude, float $accuracy = 0): bool
    {
        if (!$this->isEnabled()) {
            return false;
        }

        try {
            $this->firestore
                ->collection('drivers')
                ->document($driverId)
                ->update([
                    ['path' => 'current_location.latitude', 'value' => $latitude],
                    ['path' => 'current_location.longitude', 'value' => $longitude],
                    ['path' => 'current_location.accuracy', 'value' => $accuracy],
                    ['path' => 'current_location.updated_at', 'value' => now()],
                ]);

            return true;
        } catch (Exception $e) {
            Log::error('[Firebase] Driver location sync failed', [
                'driver_id' => $driverId,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    // ==================== TRIP SYNC ====================

    /**
     * Sync trip creation
     */
    public function syncTripCreation($trip): bool
    {
        if (!$this->isEnabled()) {
            return false;
        }

        try {
            $this->firestore
                ->collection('active_trips')
                ->document((string) $trip->id)
                ->set([
                    'passenger_id' => (string) $trip->passenger_id,
                    'driver_id' => $trip->driver_id ? (string) $trip->driver_id : null,
                    'status' => $trip->status ?? 'requested',
                    'ride_type' => $trip->ride_type ?? 'private_car',
                    'pickup' => [
                        'latitude' => $trip->pickup_latitude,
                        'longitude' => $trip->pickup_longitude,
                        'address' => $trip->pickup_address ?? '',
                        'timestamp' => now(),
                    ],
                    'dropoff' => [
                        'latitude' => $trip->dropoff_latitude,
                        'longitude' => $trip->dropoff_longitude,
                        'address' => $trip->dropoff_address ?? '',
                        'timestamp' => now(),
                    ],
                    'distance_km' => $trip->distance_km ?? 0,
                    'estimated_duration_seconds' => $trip->estimated_duration ?? 0,
                    'estimated_fare' => $trip->estimated_fare ?? 0,
                    'currency' => 'RWF',
                    'driver_location' => [
                        'latitude' => 0,
                        'longitude' => 0,
                        'timestamp' => now(),
                        'distance_to_pickup' => 0,
                    ],
                    'route' => [
                        'polyline' => '',
                        'waypoints' => [],
                        'updated_at' => now(),
                    ],
                    'passenger_location_history' => [[
                        'latitude' => $trip->pickup_latitude,
                        'longitude' => $trip->pickup_longitude,
                        'timestamp' => now(),
                    ]],
                    'driver_location_history' => [],
                    'events' => [[
                        'type' => 'requested',
                        'timestamp' => now(),
                        'metadata' => [],
                    ]],
                    'timeline' => [
                        'requested_at' => now(),
                        'accepted_at' => null,
                        'driver_arrived_at' => null,
                        'started_at' => null,
                        'completed_at' => null,
                        'cancelled_at' => null,
                    ],
                    'payment' => [
                        'method' => 'upi',
                        'status' => 'pending',
                        'amount' => 0,
                        'transaction_id' => '',
                    ],
                    'rating' => [
                        'passenger_rating' => null,
                        'driver_rating' => null,
                        'passenger_review' => null,
                        'driver_review' => null,
                    ],
                    'cancellation' => [
                        'reason' => null,
                        'cancelled_by' => null,
                        'refund_amount' => null,
                    ],
                    'metadata' => [
                        'promotion_code' => null,
                        'discount_amount' => 0,
                        'notes' => '',
                    ],
                ]);

            if (config('firebase.logging.log_success')) {
                Log::info('[Firebase] Trip synced', ['trip_id' => $trip->id]);
            }

            return true;
        } catch (Exception $e) {
            Log::error('[Firebase] Trip sync failed', [
                'trip_id' => $trip->id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Sync trip status update
     */
    public function syncTripStatusUpdate(string $tripId, string $status): bool
    {
        if (!$this->isEnabled()) {
            return false;
        }

        try {
            $update = [
                ['path' => 'status', 'value' => $status],
            ];

            // Map status to timeline field
            $statusMap = [
                'accepted' => 'timeline.accepted_at',
                'driver_arriving' => 'timeline.driver_arrived_at',
                'arrived' => 'timeline.driver_arrived_at',
                'in_progress' => 'timeline.started_at',
                'completed' => 'timeline.completed_at',
                'cancelled' => 'timeline.cancelled_at',
            ];

            if (isset($statusMap[$status])) {
                $update[] = [
                    'path' => $statusMap[$status],
                    'value' => now(),
                ];
            }

            $this->firestore
                ->collection('active_trips')
                ->document($tripId)
                ->update($update);

            return true;
        } catch (Exception $e) {
            Log::error('[Firebase] Trip status update failed', [
                'trip_id' => $tripId,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Sync trip completion
     */
    public function syncTripCompletion(string $tripId, array $paymentData): bool
    {
        if (!$this->isEnabled()) {
            return false;
        }

        try {
            $this->firestore
                ->collection('active_trips')
                ->document($tripId)
                ->update([
                    ['path' => 'status', 'value' => 'completed'],
                    ['path' => 'timeline.completed_at', 'value' => now()],
                    ['path' => 'payment.status', 'value' => $paymentData['status'] ?? 'pending'],
                    ['path' => 'payment.amount', 'value' => $paymentData['amount'] ?? 0],
                    ['path' => 'payment.transaction_id', 'value' => $paymentData['transaction_id'] ?? ''],
                ]);

            return true;
        } catch (Exception $e) {
            Log::error('[Firebase] Trip completion sync failed', [
                'trip_id' => $tripId,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    // ==================== RATING SYNC ====================

    /**
     * Sync rating creation
     */
    public function syncRatingCreation(string $driverId, array $ratingData): bool
    {
        if (!$this->isEnabled()) {
            return false;
        }

        try {
            $this->firestore
                ->collection('driver_ratings')
                ->add([
                    'driver_id' => $driverId,
                    'trip_id' => $ratingData['trip_id'] ?? '',
                    'passenger_id' => $ratingData['passenger_id'] ?? '',
                    'rating' => $ratingData['rating'] ?? 0,
                    'review' => $ratingData['review'] ?? '',
                    'categories' => $ratingData['categories'] ?? [],
                    'created_at' => now(),
                    'anonymous' => false,
                ]);

            // Update driver average rating
            $this->updateDriverAverageRating($driverId);

            return true;
        } catch (Exception $e) {
            Log::error('[Firebase] Rating sync failed', [
                'driver_id' => $driverId,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Update driver average rating from all ratings
     */
    private function updateDriverAverageRating(string $driverId): void
    {
        try {
            $ratings = $this->firestore
                ->collection('driver_ratings')
                ->where('driver_id', '==', $driverId)
                ->documents();

            $total = 0;
            $count = 0;

            foreach ($ratings as $doc) {
                $total += (float) ($doc['rating'] ?? 0);
                $count++;
            }

            if ($count === 0) {
                return;
            }

            $average = $total / $count;

            $this->firestore
                ->collection('drivers')
                ->document($driverId)
                ->update([
                    ['path' => 'average_rating', 'value' => $average],
                ]);

            $this->firestore
                ->collection('users')
                ->document($driverId)
                ->update([
                    ['path' => 'rating', 'value' => $average],
                ]);
        } catch (Exception $e) {
            Log::error('[Firebase] Update average rating failed', [
                'driver_id' => $driverId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    // ==================== BATCH OPERATIONS ====================

    /**
     * Batch sync multiple documents
     */
    public function batchSync(array $operations): bool
    {
        if (!$this->isEnabled()) {
            return false;
        }

        try {
            $batch = $this->firestore->batch();
            $count = 0;

            foreach ($operations as $op) {
                $docRef = $this->firestore
                    ->collection($op['collection'])
                    ->document($op['id']);

                if ($op['type'] === 'set') {
                    $batch->set($docRef, $op['data'], ['merge' => true]);
                } elseif ($op['type'] === 'update') {
                    $batch->update($docRef, $op['data']);
                } elseif ($op['type'] === 'delete') {
                    $batch->delete($docRef);
                }

                $count++;

                // Commit in batches of 500
                if ($count >= config('firebase.sync.batch_size', 500)) {
                    $batch->commit();
                    $batch = $this->firestore->batch();
                    $count = 0;
                }
            }

            // Commit remaining
            if ($count > 0) {
                $batch->commit();
            }

            Log::info('[Firebase] Batch sync completed', ['operations' => count($operations)]);

            return true;
        } catch (Exception $e) {
            Log::error('[Firebase] Batch sync failed', [
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    // ==================== HEALTH CHECK ====================

    /**
     * Check Firebase connection health
     */
    public function healthCheck(): array
    {
        if (!$this->isEnabled()) {
            return [
                'status' => 'disconnected',
                'message' => 'Firebase not configured or enabled',
            ];
        }

        try {
            // Try to read a single document
            $this->firestore
                ->collection('users')
                ->limit(1)
                ->documents()
                ->current();

            return [
                'status' => 'connected',
                'message' => 'Firebase Firestore connection healthy',
            ];
        } catch (Exception $e) {
            return [
                'status' => 'error',
                'message' => 'Firebase connection failed: ' . $e->getMessage(),
            ];
        }
    }
}
