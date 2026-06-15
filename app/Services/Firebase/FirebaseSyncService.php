<?php

namespace App\Services\Firebase;

use Kreait\Firebase\Factory;
use Illuminate\Support\Facades\Log;
use App\Models\User;
use App\Models\Driver;
use App\Models\Trip;
use App\Models\Payment;
use App\Models\Review;
use Exception;

/**
 * FirebaseSyncService - RTDB-ONLY ORCHESTRATOR FOR REAL-TIME STATE
 *
 * CRITICAL ARCHITECTURE RULE:
 * Firestore is permanently disabled. All real-time writes go to Firebase
 * Realtime Database (RTDB) using the RTDB-only architecture.
 *
 * Supabase is the source of truth.
 * RTDB is the real-time cache layer.
 */
class FirebaseSyncService
{
    /** @var \Kreait\Firebase\Contract\Database|null RTDB client for real-time state */
    private $rtdb = null;
    /** @var null Firestore is permanently disabled — stub to prevent fatal errors on legacy calls */
    private $firestore = null;
    private bool $enabled = false;
    private bool $initialized = false;

    public function __construct(
        private readonly FirebaseHealthService $healthService
    ) {
        // Lazy initialization — do NOT connect to RTDB here.
    }

    /**
     * Lazy-initialize Firebase RTDB connection on first use.
     * Safe to call multiple times (idempotent).
     */
    private function ensureInitialized(): void
    {
        if ($this->initialized) {
            return;
        }
        $this->initialized = true;

        if (!$this->healthService->isEnabled()) {
            Log::debug('[FirebaseSyncService] Firebase sync disabled in configuration');
            return;
        }

        try {
            $credentialsPath = config('firebase.credentials');
            $databaseUrl     = config('firebase.database_url');

            if (!$credentialsPath || !file_exists($credentialsPath)) {
                Log::warning('[FirebaseSyncService] Firebase credentials not found. Disabling sync.');
                return;
            }

            $factory = (new Factory)->withServiceAccount($credentialsPath);

            if ($databaseUrl) {
                $factory = $factory->withDatabaseUri($databaseUrl);
            }

            $this->rtdb    = $factory->createDatabase();
            $this->enabled = true;

            Log::info('[FirebaseSyncService] RTDB initialized successfully (Firestore disabled)');
        } catch (Exception $e) {
            Log::warning('[FirebaseSyncService] RTDB initialization failed: ' . $e->getMessage());
            $this->enabled = false;
        }
    }

    /**
     * Check if Firebase RTDB is enabled and ready
     */
    public function isEnabled(): bool
    {
        $this->ensureInitialized();
        return $this->enabled && $this->rtdb !== null;
    }

    /**
     * Bootstrap schema — no-op in RTDB-only architecture.
     * RTDB does not require explicit collection creation.
     */
    public function bootstrapSchema(): array
    {
        Log::info('[FirebaseSyncService] bootstrapSchema called — no-op (RTDB-only architecture; Firestore disabled)');
        return [
            'success' => true,
            'message' => 'Schema bootstrap not required — RTDB-only architecture (Firestore disabled)',
        ];
    }

    /**
     * Sync Supabase to Firebase — pushes active trips and driver state to RTDB.
     */
    public function syncSupabaseToFirestore(): array
    {
        if (!$this->isEnabled()) {
            return ['success' => false, 'message' => 'Firebase RTDB not enabled'];
        }

        try {
            $results = [];
            $results['active_trips'] = $this->syncActiveTripsToRtdb();
            Log::info('[FirebaseSyncService] Supabase → RTDB sync completed', $results);
            return [
                'success' => true,
                'message' => 'Supabase to RTDB sync completed',
                'results' => $results,
            ];
        } catch (Exception $e) {
            Log::error('[FirebaseSyncService] Supabase → RTDB sync failed', ['error' => $e->getMessage()]);
            return ['success' => false, 'message' => 'Sync failed: ' . $e->getMessage()];
        }
    }

    /**
     * Push all active trips to RTDB under active_trips/{trip_id}
     */
    private function syncActiveTripsToRtdb(): array
    {
        $trips = Trip::whereIn('status', ['REQUESTED', 'MATCHING', 'DRIVER_FOUND', 'ASSIGNED', 'ACCEPTED', 'ARRIVED', 'STARTED'])->get();
        $synced = 0;
        $failed = 0;

        foreach ($trips as $trip) {
            try {
                $this->rtdb->getReference('active_trips/' . $trip->id)->set([
                    'trip_id'     => $trip->id,
                    'passenger_id' => (string) $trip->passenger_id,
                    'driver_id'   => $trip->driver_id ? (string) $trip->driver_id : null,
                    'status'      => strtolower($trip->status ?? 'requested'),
                    'ride_type'   => $trip->ride_type ?? 'private_car',
                    'pickup'      => [
                        'latitude'  => $trip->pickup_latitude,
                        'longitude' => $trip->pickup_longitude,
                        'address'   => $trip->pickup_address ?? '',
                    ],
                    'dropoff'     => [
                        'latitude'  => $trip->dropoff_latitude,
                        'longitude' => $trip->dropoff_longitude,
                        'address'   => $trip->dropoff_address ?? '',
                    ],
                    'estimated_fare' => $trip->estimated_fare ?? 0,
                    'currency'       => 'RWF',
                    'updated_at'     => now()->toIso8601String(),
                ]);
                $synced++;
            } catch (Exception $e) {
                Log::warning("[FirebaseSyncService] Failed to sync trip {$trip->id} to RTDB", ['error' => $e->getMessage()]);
                $failed++;
            }
        }

        return ['total' => $trips->count(), 'synced' => $synced, 'failed' => $failed];
    }

    /**
     * Sync users from Supabase to Firestore
     */
    private function syncUsers(): array
    {
        $users = User::all();
        $synced = 0;
        $failed = 0;

        foreach ($users as $user) {
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
                        'last_seen' => $user->updated_at ?? now(),
                        'rating' => 0.0,
                        'completed_trips' => 0,
                        'cancelled_trips' => 0,
                        'metadata' => [
                            'created_at' => $user->created_at ?? now(),
                            'updated_at' => $user->updated_at ?? now(),
                            'firebase_token' => null,
                            'app_version' => '1.0.0',
                        ],
                    ], ['merge' => true]);

                $synced++;
            } catch (Exception $e) {
                Log::warning("[FirebaseSyncService] Failed to sync user {$user->id}", [
                    'error' => $e->getMessage(),
                ]);
                $failed++;
            }
        }

        return [
            'total' => $users->count(),
            'synced' => $synced,
            'failed' => $failed,
        ];
    }

    /**
     * Sync drivers from Supabase to Firestore
     */
    private function syncDrivers(): array
    {
        $drivers = Driver::all();
        $synced = 0;
        $failed = 0;

        foreach ($drivers as $driver) {
            try {
                $this->firestore
                    ->collection('drivers')
                    ->document((string) $driver->user_id)
                    ->set([
                        'user_id' => $driver->user_id,
                        'status' => 'offline',
                        'current_location' => [
                            'latitude' => $driver->last_location_lat ?? 0,
                            'longitude' => $driver->last_location_lng ?? 0,
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
                    ], ['merge' => true]);

                $synced++;
            } catch (Exception $e) {
                Log::warning("[FirebaseSyncService] Failed to sync driver {$driver->user_id}", [
                    'error' => $e->getMessage(),
                ]);
                $failed++;
            }
        }

        return [
            'total' => $drivers->count(),
            'synced' => $synced,
            'failed' => $failed,
        ];
    }

    /**
     * Sync active trips from Supabase to Firestore
     */
    private function syncActiveTrips(): array
    {
        $trips = Trip::whereIn('status', ['requested', 'matched', 'driver_assigned', 'in_progress'])->get();
        $synced = 0;
        $failed = 0;

        foreach ($trips as $trip) {
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
                        'passenger_location_history' => [],
                        'driver_location_history' => [],
                        'events' => [],
                        'timeline' => [
                            'requested_at' => $trip->created_at ?? now(),
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
                    ], ['merge' => true]);

                $synced++;
            } catch (Exception $e) {
                Log::warning("[FirebaseSyncService] Failed to sync trip {$trip->id}", [
                    'error' => $e->getMessage(),
                ]);
                $failed++;
            }
        }

        return [
            'total' => $trips->count(),
            'synced' => $synced,
            'failed' => $failed,
        ];
    }

    /**
     * Sync payments from Supabase to Firestore
     */
    private function syncPayments(): array
    {
        $payments = Payment::where('status', 'COMPLETED')->get();
        $synced = 0;
        $failed = 0;

        foreach ($payments as $payment) {
            try {
                // Update trip payment info
                if ($payment->trip_id) {
                    $this->firestore
                        ->collection('active_trips')
                        ->document((string) $payment->trip_id)
                        ->update([
                            ['path' => 'payment.status', 'value' => 'completed'],
                            ['path' => 'payment.amount', 'value' => $payment->amount],
                            ['path' => 'payment.transaction_id', 'value' => $payment->transaction_id ?? ''],
                        ]);
                }

                $synced++;
            } catch (Exception $e) {
                Log::warning("[FirebaseSyncService] Failed to sync payment {$payment->id}", [
                    'error' => $e->getMessage(),
                ]);
                $failed++;
            }
        }

        return [
            'total' => $payments->count(),
            'synced' => $synced,
            'failed' => $failed,
        ];
    }

    /**
     * Sync event to Firestore
     * 
     * This is the MAIN entry point for all event-driven syncs
     * Replaces ALL existing sync services
     * 
     * Supported event types:
     * - DriverAssigned
     * - TripStarted
     * - TripCompleted
     * - PaymentCompleted
     * - RatingSubmitted
     * - DriverLocationUpdated
     * - UserCreated
     * - DriverCreated
     */
    public function syncEvent(string $eventType, array $payload): bool
    {
        if (!$this->isEnabled()) {
            return false;
        }

        try {
            return match ($eventType) {
                'DriverAssigned' => $this->handleDriverAssigned($payload),
                'TripStarted' => $this->handleTripStarted($payload),
                'TripCompleted' => $this->handleTripCompleted($payload),
                'PaymentCompleted' => $this->handlePaymentCompleted($payload),
                'RatingSubmitted' => $this->handleRatingSubmitted($payload),
                'DriverLocationUpdated' => $this->handleDriverLocationUpdated($payload),
                'UserCreated' => $this->handleUserCreated($payload),
                'DriverCreated' => $this->handleDriverCreated($payload),
                'TripCancelled' => $this->handleTripCancelled($payload),
                default => $this->handleUnknownEvent($eventType, $payload),
            };
        } catch (Exception $e) {
            Log::error('[FirebaseSyncService] Event sync failed', [
                'event_type' => $eventType,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Handle DriverAssigned event — writes to RTDB
     */
    private function handleDriverAssigned(array $payload): bool
    {
        $tripId   = $payload['trip_id'] ?? null;
        $driverId = $payload['driver_id'] ?? null;

        if (!$tripId || !$driverId) {
            Log::warning('[FirebaseSyncService] DriverAssigned missing required fields', $payload);
            return false;
        }

        try {
            // Update active_trips/{trip_id} in RTDB
            $this->rtdb->getReference('active_trips/' . $tripId)->update([
                'driver_id'          => (string) $driverId,
                'status'             => 'accepted',
                'timeline_accepted_at' => now()->toIso8601String(),
                'updated_at'         => now()->toIso8601String(),
            ]);

            // Update drivers_online/{driver_id} status in RTDB
            $this->rtdb->getReference('drivers_online/' . $driverId)->update([
                'status'         => 'on_trip',
                'current_trip_id' => (string) $tripId,
                'updated_at'     => now()->toIso8601String(),
            ]);

            // Send FCM notification to passenger
            $this->sendNotification(
                $payload['passenger_id'] ?? null,
                'trip',
                'Driver Assigned',
                'Your driver has been assigned',
                ['trip_id' => (string) $tripId, 'driver_id' => (string) $driverId]
            );

            Log::info('[FirebaseSyncService] DriverAssigned synced to RTDB', [
                'trip_id'   => $tripId,
                'driver_id' => $driverId,
            ]);

            return true;
        } catch (Exception $e) {
            Log::error('[FirebaseSyncService] DriverAssigned RTDB sync failed', [
                'trip_id' => $tripId,
                'error'   => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Handle TripStarted event — writes to RTDB
     */
    private function handleTripStarted(array $payload): bool
    {
        $tripId = $payload['trip_id'] ?? null;

        if (!$tripId) {
            Log::warning('[FirebaseSyncService] TripStarted missing required fields', $payload);
            return false;
        }

        try {
            // Update active_trips/{trip_id} in RTDB
            $this->rtdb->getReference('active_trips/' . $tripId)->update([
                'status'             => 'started',
                'timeline_started_at' => now()->toIso8601String(),
                'updated_at'         => now()->toIso8601String(),
            ]);

            // Send FCM notification to passenger
            $this->sendNotification(
                $payload['passenger_id'] ?? null,
                'trip',
                'Trip Started',
                'Your trip has started',
                ['trip_id' => (string) $tripId]
            );

            Log::info('[FirebaseSyncService] TripStarted synced to RTDB', ['trip_id' => $tripId]);

            return true;
        } catch (Exception $e) {
            Log::error('[FirebaseSyncService] TripStarted RTDB sync failed', [
                'trip_id' => $tripId,
                'error'   => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Handle TripCompleted event — writes to RTDB
     */
    private function handleTripCompleted(array $payload): bool
    {
        $tripId   = $payload['trip_id'] ?? null;
        $driverId = $payload['driver_id'] ?? null;

        if (!$tripId) {
            Log::warning('[FirebaseSyncService] TripCompleted missing required fields', $payload);
            return false;
        }

        try {
            // Update active_trips/{trip_id} in RTDB
            $this->rtdb->getReference('active_trips/' . $tripId)->update([
                'status'               => 'completed',
                'timeline_completed_at' => now()->toIso8601String(),
                'updated_at'           => now()->toIso8601String(),
            ]);

            // Release driver in drivers_online/{driver_id}
            if ($driverId) {
                $this->rtdb->getReference('drivers_online/' . $driverId)->update([
                    'status'          => 'online',
                    'current_trip_id' => null,
                    'updated_at'      => now()->toIso8601String(),
                ]);
            }

            // Send FCM notification to passenger
            $this->sendNotification(
                $payload['passenger_id'] ?? null,
                'trip',
                'Trip Completed',
                'Your trip has been completed',
                ['trip_id' => (string) $tripId]
            );

            // Send FCM notification to driver
            if ($driverId) {
                $this->sendNotification(
                    $driverId,
                    'trip',
                    'Trip Completed',
                    'Trip completed successfully',
                    ['trip_id' => (string) $tripId]
                );
            }

            Log::info('[FirebaseSyncService] TripCompleted synced to RTDB', ['trip_id' => $tripId]);

            return true;
        } catch (Exception $e) {
            Log::error('[FirebaseSyncService] TripCompleted RTDB sync failed', [
                'trip_id' => $tripId,
                'error'   => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Handle PaymentCompleted event — writes to RTDB
     */
    private function handlePaymentCompleted(array $payload): bool
    {
        $tripId        = $payload['trip_id'] ?? null;
        $paymentId     = $payload['payment_id'] ?? null;
        $amount        = $payload['amount'] ?? 0;
        $transactionId = $payload['transaction_id'] ?? '';

        if (!$tripId) {
            Log::warning('[FirebaseSyncService] PaymentCompleted missing required fields', $payload);
            return false;
        }

        try {
            // Update payment status in active_trips RTDB node
            $this->rtdb->getReference('active_trips/' . $tripId)->update([
                'payment_status'         => 'completed',
                'payment_amount'         => $amount,
                'payment_transaction_id' => $transactionId,
                'updated_at'             => now()->toIso8601String(),
            ]);

            // Send FCM notification to passenger
            $this->sendNotification(
                $payload['passenger_id'] ?? null,
                'payment',
                'Payment Confirmed',
                'Your payment has been confirmed',
                ['trip_id' => (string) $tripId, 'payment_id' => (string) $paymentId, 'amount' => $amount]
            );

            // Send FCM notification to driver
            $this->sendNotification(
                $payload['driver_id'] ?? null,
                'payment',
                'Payment Received',
                'Payment received successfully',
                ['trip_id' => (string) $tripId, 'payment_id' => (string) $paymentId, 'amount' => $amount]
            );

            Log::info('[FirebaseSyncService] PaymentCompleted synced to RTDB', [
                'trip_id'    => $tripId,
                'payment_id' => $paymentId,
            ]);

            return true;
        } catch (Exception $e) {
            Log::error('[FirebaseSyncService] PaymentCompleted RTDB sync failed', [
                'trip_id' => $tripId,
                'error'   => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Handle RatingSubmitted event — updates RTDB driver node and sends FCM
     */
    private function handleRatingSubmitted(array $payload): bool
    {
        $driverId = $payload['driver_id'] ?? null;
        $rating   = $payload['rating'] ?? 0;
        $tripId   = $payload['trip_id'] ?? null;

        if (!$driverId) {
            Log::warning('[FirebaseSyncService] RatingSubmitted missing required fields', $payload);
            return false;
        }

        try {
            // Update driver's rating in drivers_online/{driver_id} RTDB node
            $this->rtdb->getReference('drivers_online/' . $driverId)->update([
                'last_rating' => $rating,
                'updated_at'  => now()->toIso8601String(),
            ]);

            Log::info('[FirebaseSyncService] RatingSubmitted synced to RTDB', [
                'driver_id' => $driverId,
                'rating'    => $rating,
            ]);

            return true;
        } catch (Exception $e) {
            Log::error('[FirebaseSyncService] RatingSubmitted RTDB sync failed', [
                'driver_id' => $driverId,
                'error'     => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Handle DriverLocationUpdated event
     */
    private function handleDriverLocationUpdated(array $payload): bool
    {
        return $this->syncDriverLocation(
            $payload['driver_id'],
            $payload['latitude'],
            $payload['longitude'],
            $payload['accuracy'] ?? 0,
            $payload['trip_id'] ?? null
        );
    }

    /**
     * Handle UserCreated event
     */
    private function handleUserCreated(array $payload): bool
    {
        $userId = $payload['user_id'] ?? null;
        $user = User::find($userId);

        if (!$user) {
            Log::warning('[FirebaseSyncService] UserCreated user not found', ['user_id' => $userId]);
            return false;
        }

        try {
            // Write basic user presence info to RTDB presence/{user_id}
            $this->rtdb->getReference('presence/' . $user->id)->set([
                'user_id'    => (string) $user->id,
                'name'       => $user->name,
                'role'       => $user->role->value ?? 'passenger',
                'online'     => false,
                'last_seen'  => now()->toIso8601String(),
                'updated_at' => now()->toIso8601String(),
            ]);

            Log::info('[FirebaseSyncService] UserCreated synced to RTDB presence', ['user_id' => $userId]);

            return true;
        } catch (Exception $e) {
            Log::error('[FirebaseSyncService] UserCreated RTDB sync failed', [
                'user_id' => $userId,
                'error'   => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Handle DriverCreated event
     */
    private function handleDriverCreated(array $payload): bool
    {
        $driverId = $payload['driver_id'] ?? null;
        $driver = Driver::find($driverId);

        if (!$driver) {
            Log::warning('[FirebaseSyncService] DriverCreated driver not found', ['driver_id' => $driverId]);
            return false;
        }

        try {
            // Write driver online state to RTDB drivers_online/{driver_user_id}
            $this->rtdb->getReference('drivers_online/' . $driver->user_id)->set([
                'driver_id'        => $driver->id,
                'user_id'          => (string) $driver->user_id,
                'status'           => 'offline',
                'current_lat'      => $driver->last_location_lat ?? 0,
                'current_lng'      => $driver->last_location_lng ?? 0,
                'vehicle_type'     => $driver->vehicle_type ?? 'economy',
                'current_trip_id'  => null,
                'updated_at'       => now()->toIso8601String(),
            ]);

            Log::info('[FirebaseSyncService] DriverCreated synced to RTDB', ['driver_id' => $driverId]);

            return true;
        } catch (Exception $e) {
            Log::error('[FirebaseSyncService] DriverCreated RTDB sync failed', [
                'driver_id' => $driverId,
                'error'     => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Handle TripCancelled event
     */
    private function handleTripCancelled(array $payload): bool
    {
        $tripId = $payload['trip_id'] ?? null;
        $driverId = $payload['driver_id'] ?? null;
        $cancelledBy = $payload['cancelled_by'] ?? null;
        $reason = $payload['reason'] ?? '';

        if (!$tripId) {
            Log::warning('[FirebaseSyncService] TripCancelled missing required fields', $payload);
            return false;
        }

        try {
            // Update active_trips/{trip_id} in RTDB
            $this->rtdb->getReference('active_trips/' . $tripId)->update([
                'status'                  => 'cancelled',
                'timeline_cancelled_at'   => now()->toIso8601String(),
                'cancellation_reason'     => $reason,
                'cancelled_by'            => $cancelledBy,
                'updated_at'              => now()->toIso8601String(),
            ]);

            // Release driver in drivers_online/{driver_id}
            if ($driverId) {
                $this->rtdb->getReference('drivers_online/' . $driverId)->update([
                    'status'          => 'online',
                    'current_trip_id' => null,
                    'updated_at'      => now()->toIso8601String(),
                ]);
            }

            Log::info('[FirebaseSyncService] TripCancelled synced to RTDB', ['trip_id' => $tripId]);

            return true;
        } catch (Exception $e) {
            Log::error('[FirebaseSyncService] TripCancelled RTDB sync failed', [
                'trip_id' => $tripId,
                'error'   => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Handle unknown event
     */
    private function handleUnknownEvent(string $eventType, array $payload): bool
    {
        Log::warning('[FirebaseSyncService] Unknown event type', [
            'event_type' => $eventType,
            'payload' => $payload,
        ]);
        return false;
    }

    /**
     * Sync driver location to RTDB.
     *
     * RTDB paths:
     * - driver_locations/{driver_id} — current location snapshot
     * - active_trips/{trip_id}       — driver_location sub-node (if on trip)
     */
    public function syncDriverLocation(string $driverId, float $latitude, float $longitude, float $accuracy = 0, ?int $tripId = null): bool
    {
        if (!$this->isEnabled()) {
            return false;
        }

        try {
            // Update driver_locations/{driver_id} in RTDB
            $this->rtdb->getReference('driver_locations/' . $driverId)->set([
                'driver_id'  => (string) $driverId,
                'trip_id'    => $tripId ? (string) $tripId : null,
                'latitude'   => $latitude,
                'longitude'  => $longitude,
                'accuracy'   => $accuracy,
                'is_online'  => true,
                'updated_at' => now()->toIso8601String(),
            ]);

            // Also update drivers_online/{driver_id} current location
            $this->rtdb->getReference('drivers_online/' . $driverId)->update([
                'current_lat' => $latitude,
                'current_lng' => $longitude,
                'updated_at'  => now()->toIso8601String(),
            ]);

            // Update active_trips/{trip_id} driver_location if on active trip
            if ($tripId) {
                $this->rtdb->getReference('active_trips/' . $tripId)->update([
                    'driver_lat'        => $latitude,
                    'driver_lng'        => $longitude,
                    'driver_updated_at' => now()->toIso8601String(),
                ]);

                // Sync trip tracking
                $this->syncTripTracking($tripId, $driverId, $latitude, $longitude);
            }

            return true;
        } catch (Exception $e) {
            Log::error('[FirebaseSyncService] Driver location RTDB sync failed', [
                'driver_id' => $driverId,
                'error'     => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Sync trip tracking data to RTDB.
     *
     * RTDB path: trip_tracking/{trip_id}
     */
    public function syncTripTracking(int $tripId, string $driverId, float $latitude, float $longitude, ?float $eta = null, ?float $distanceRemaining = null): bool
    {
        if (!$this->isEnabled()) {
            return false;
        }

        try {
            $this->rtdb->getReference('trip_tracking/' . $tripId)->update([
                'driver_id'         => (string) $driverId,
                'driver_lat'        => $latitude,
                'driver_lng'        => $longitude,
                'eta'               => $eta,
                'distance_remaining' => $distanceRemaining,
                'updated_at'        => now()->toIso8601String(),
            ]);

            return true;
        } catch (Exception $e) {
            Log::error('[FirebaseSyncService] Trip tracking RTDB sync failed', [
                'trip_id' => $tripId,
                'error'   => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Sync payment event
     * 
     * Write payment state into active_trips.payment
     * Log into trip_events
     * Trigger notification sync
     */
    public function syncPaymentEvent(array $paymentData): bool
    {
        if (!$this->isEnabled()) {
            return false;
        }

        $tripId = $paymentData['trip_id'] ?? null;
        $status = $paymentData['status'] ?? 'pending';
        $amount = $paymentData['amount'] ?? 0;
        $transactionId = $paymentData['transaction_id'] ?? '';

        if (!$tripId) {
            Log::warning('[FirebaseSyncService] Payment event missing trip_id', $paymentData);
            return false;
        }

        try {
            // Update active_trips/{trip_id} payment status in RTDB
            $this->rtdb->getReference('active_trips/' . $tripId)->update([
                'payment_status'         => $status,
                'payment_amount'         => $amount,
                'payment_transaction_id' => $transactionId,
                'updated_at'             => now()->toIso8601String(),
            ]);

            // Trigger notification if completed
            if ($status === 'completed') {
                $this->syncEvent('PaymentCompleted', $paymentData);
            }

            Log::info('[FirebaseSyncService] Payment event synced to RTDB', [
                'trip_id' => $tripId,
                'status'  => $status,
            ]);

            return true;
        } catch (Exception $e) {
            Log::error('[FirebaseSyncService] Payment event RTDB sync failed', [
                'trip_id' => $tripId,
                'error'   => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Update driver average rating from Postgres instead of Firestore.
     * Firestore is disabled; rating data lives in Supabase.
     */
    private function updateDriverAverageRating(string $driverId): void
    {
        try {
            $driver = Driver::find($driverId);
            if ($driver && $driver->average_rating !== null) {
                // Update RTDB driver node with the Postgres-sourced rating
                $this->rtdb->getReference('drivers_online/' . $driverId)->update([
                    'average_rating' => (float) $driver->average_rating,
                    'updated_at'     => now()->toIso8601String(),
                ]);
            }
        } catch (Exception $e) {
            Log::error('[FirebaseSyncService] RTDB rating update failed', [
                'driver_id' => $driverId,
                'error'     => $e->getMessage(),
            ]);
        }
    }

    /**
     * Send notification: writes to RTDB notification_queue and dispatches FCM.
     * Firestore is disabled; notifications go to RTDB notification_queue/{user_id}.
     */
    private function sendNotification(?int $userId, string $type, string $title, string $body, array $data = []): void
    {
        if (!$userId) {
            return;
        }

        try {
            // Push notification to RTDB notification_queue/{user_id}/{timestamp}
            $this->rtdb->getReference('notification_queue/' . $userId . '/' . now()->timestamp)->set([
                'user_id'    => (int) $userId,
                'type'       => $type,
                'title'      => $title,
                'body'       => $body,
                'data'       => $data,
                'read'       => false,
                'created_at' => now()->toIso8601String(),
            ]);

            // Send FCM push notification via Postgres device tokens
            $this->sendFcmNotification($userId, $title, $body, $data);
        } catch (Exception $e) {
            Log::warning('[FirebaseSyncService] Notification send failed', [
                'user_id' => $userId,
                'error'   => $e->getMessage(),
            ]);
        }
    }

    /**
     * Send FCM push notification.
     * Device tokens are read from Postgres (device_tokens table) instead of Firestore.
     */
    private function sendFcmNotification(int $userId, string $title, string $body, array $data = []): void
    {
        try {
            // Read active device tokens from Postgres via the DeviceToken model
            $tokenRecords = \App\Models\DeviceToken::where('tokenable_id', $userId)
                ->where('is_active', true)
                ->pluck('token')
                ->filter()
                ->values()
                ->toArray();

            if (empty($tokenRecords)) {
                return;
            }

            // Create FCM messaging client from factory
            $credentialsPath = config('firebase.credentials');
            if (!$credentialsPath || !file_exists($credentialsPath)) {
                return;
            }

            $messaging = (new \Kreait\Firebase\Factory)
                ->withServiceAccount($credentialsPath)
                ->createMessaging();

            $message = \Kreait\Firebase\Messaging\CloudMessage::new()
                ->withNotification(['title' => $title, 'body' => $body])
                ->withData(array_map('strval', $data));

            $messaging->sendMulticast($message, $tokenRecords);

            Log::debug('[FirebaseSyncService] FCM notification sent', [
                'user_id'      => $userId,
                'tokens_count' => count($tokenRecords),
            ]);
        } catch (Exception $e) {
            Log::warning('[FirebaseSyncService] FCM notification failed', [
                'user_id' => $userId,
                'error'   => $e->getMessage(),
            ]);
        }
    }

    /**
     * Health check — reports RTDB status (Firestore is permanently disabled)
     */
    public function healthCheck(): array
    {
        if (!$this->isEnabled()) {
            return [
                'status'   => 'disconnected',
                'message'  => 'Firebase RTDB not configured or enabled',
                'firestore' => 'disabled',
            ];
        }

        return [
            'status'    => 'connected',
            'message'   => 'Firebase RTDB connection active (Firestore permanently disabled)',
            'firestore' => 'disabled',
        ];
    }

    // ==================== ADDITIONAL SYNC METHODS ====================

    /**
     * Sync user to RTDB presence — Firestore permanently disabled.
     */
    public function syncUser(int $userId): bool
    {
        if (!$this->isEnabled()) {
            return false;
        }

        $user = User::find($userId);
        if (!$user) {
            Log::warning('[FirebaseSyncService] User not found', ['user_id' => $userId]);
            return false;
        }

        try {
            $this->rtdb->getReference('presence/' . $userId)->update([
                'user_id'    => (string) $userId,
                'name'       => $user->name,
                'role'       => $user->role->value ?? 'passenger',
                'online'     => false,
                'last_seen'  => ($user->updated_at ?? now())->toIso8601String(),
                'updated_at' => now()->toIso8601String(),
            ]);

            Log::info('[FirebaseSyncService] User synced to RTDB presence', ['user_id' => $userId]);
            return true;
        } catch (Exception $e) {
            Log::error('[FirebaseSyncService] User RTDB sync failed', [
                'user_id' => $userId,
                'error'   => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Sync driver to Firestore
     * Idempotent, retry-safe, queue-safe
     */
    public function syncDriver(int $driverId): bool
    {
        if (!$this->isEnabled()) {
            return false;
        }

        $driver = Driver::find($driverId);
        if (!$driver) {
            Log::warning('[FirebaseSyncService] Driver not found', ['driver_id' => $driverId]);
            return false;
        }

        try {
            // Write driver state to RTDB drivers_online/{driver_user_id}
            $this->rtdb->getReference('drivers_online/' . $driver->user_id)->set([
                'driver_id'       => $driver->id,
                'user_id'         => (string) $driver->user_id,
                'status'          => 'offline',
                'current_lat'     => $driver->last_location_lat ?? 0,
                'current_lng'     => $driver->last_location_lng ?? 0,
                'vehicle_type'    => $driver->vehicle_type ?? 'economy',
                'average_rating'  => (float) ($driver->average_rating ?? 0),
                'current_trip_id' => null,
                'updated_at'      => now()->toIso8601String(),
            ]);

            Log::info('[FirebaseSyncService] Driver synced to RTDB', ['driver_id' => $driverId]);
            return true;
        } catch (Exception $e) {
            Log::error('[FirebaseSyncService] Driver RTDB sync failed', [
                'driver_id' => $driverId,
                'error'     => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Sync trip to RTDB active_trips — Firestore permanently disabled.
     */
    public function syncTrip(int $tripId): bool
    {
        if (!$this->isEnabled()) {
            return false;
        }

        $trip = Trip::find($tripId);
        if (!$trip) {
            Log::warning('[FirebaseSyncService] Trip not found', ['trip_id' => $tripId]);
            return false;
        }

        try {
            $this->rtdb->getReference('active_trips/' . $tripId)->set([
                'trip_id'          => $trip->id,
                'passenger_id'     => (string) $trip->passenger_id,
                'driver_id'        => $trip->driver_id ? (string) $trip->driver_id : null,
                'status'           => strtolower($trip->status ?? 'requested'),
                'ride_type'        => $trip->ride_type ?? 'private_car',
                'pickup_lat'       => $trip->pickup_latitude,
                'pickup_lng'       => $trip->pickup_longitude,
                'pickup_address'   => $trip->pickup_address ?? '',
                'dropoff_lat'      => $trip->dropoff_latitude,
                'dropoff_lng'      => $trip->dropoff_longitude,
                'dropoff_address'  => $trip->dropoff_address ?? '',
                'estimated_fare'   => $trip->estimated_fare ?? 0,
                'currency'         => 'RWF',
                'payment_status'   => 'pending',
                'created_at'       => ($trip->created_at ?? now())->toIso8601String(),
                'updated_at'       => now()->toIso8601String(),
            ]);

            Log::info('[FirebaseSyncService] Trip synced to RTDB', ['trip_id' => $tripId]);
            return true;
        } catch (Exception $e) {
            Log::error('[FirebaseSyncService] Trip RTDB sync failed', [
                'trip_id' => $tripId,
                'error'   => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Sync trip event to Firestore
     * Idempotent, retry-safe, queue-safe
     * Alias for syncEvent for clarity
     */
    public function syncTripEvent(string $tripId, string $event, array $payload = []): bool
    {
        return $this->syncEvent($event, array_merge($payload, ['trip_id' => $tripId]));
    }

    /**
     * Sync chat room to RTDB chats/{room_id} — Firestore permanently disabled.
     */
    public function syncChatRoom(string $roomId, array $data): bool
    {
        if (!$this->isEnabled()) {
            return false;
        }

        try {
            $this->rtdb->getReference('chats/' . $roomId)->update([
                'trip_id'      => $data['trip_id'] ?? null,
                'participants' => $data['participants'] ?? [],
                'type'         => $data['type'] ?? 'trip_chat',
                'updated_at'   => now()->toIso8601String(),
            ]);

            Log::info('[FirebaseSyncService] Chat room synced to RTDB', ['room_id' => $roomId]);
            return true;
        } catch (Exception $e) {
            Log::error('[FirebaseSyncService] Chat room RTDB sync failed', [
                'room_id' => $roomId,
                'error'   => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Sync chat message to RTDB chats/{room_id}/messages — Firestore permanently disabled.
     */
    public function syncChatMessage(string $roomId, array $data): bool
    {
        if (!$this->isEnabled()) {
            return false;
        }

        try {
            $msgKey = now()->timestamp . '_' . ($data['sender_id'] ?? 'unknown');
            $this->rtdb->getReference('chats/' . $roomId . '/messages/' . $msgKey)->set([
                'room_id'      => $roomId,
                'sender_id'    => $data['sender_id'] ?? '',
                'message'      => $data['message'] ?? '',
                'message_type' => $data['message_type'] ?? 'text',
                'timestamp'    => now()->toIso8601String(),
                'read_by'      => $data['read_by'] ?? [],
            ]);

            // Update last message timestamp on the room
            $this->rtdb->getReference('chats/' . $roomId)->update([
                'last_message_at' => now()->toIso8601String(),
                'updated_at'      => now()->toIso8601String(),
            ]);

            Log::info('[FirebaseSyncService] Chat message synced to RTDB', ['room_id' => $roomId]);
            return true;
        } catch (Exception $e) {
            Log::error('[FirebaseSyncService] Chat message RTDB sync failed', [
                'room_id' => $roomId,
                'error'   => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Sync presence to RTDB presence/{user_id} — Firestore permanently disabled.
     */
    public function syncPresence(int $userId, bool $online, array $location = null): bool
    {
        if (!$this->isEnabled()) {
            return false;
        }

        try {
            $data = [
                'user_id'    => (string) $userId,
                'online'     => $online,
                'last_seen'  => now()->toIso8601String(),
                'updated_at' => now()->toIso8601String(),
            ];

            if ($location) {
                $data['lat'] = $location['latitude'] ?? $location['lat'] ?? null;
                $data['lng'] = $location['longitude'] ?? $location['lng'] ?? null;
            }

            $this->rtdb->getReference('presence/' . $userId)->update($data);

            Log::info('[FirebaseSyncService] Presence synced to RTDB', ['user_id' => $userId, 'online' => $online]);
            return true;
        } catch (Exception $e) {
            Log::error('[FirebaseSyncService] Presence RTDB sync failed', [
                'user_id' => $userId,
                'error'   => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Sync device token — device tokens are stored in Postgres, not Firestore.
     * This method is a no-op; use the DeviceToken model directly.
     */
    public function syncDeviceToken(int $userId, string $token, string $platform = 'android'): bool
    {
        // Device tokens are managed in Postgres (device_tokens table).
        // Firestore is permanently disabled. Nothing to sync to Firebase.
        Log::debug('[FirebaseSyncService] syncDeviceToken called (no-op — tokens managed in Postgres)', [
            'user_id' => $userId,
        ]);
        return true;
    }

    /**
     * Remove device token — tokens are managed in Postgres, not Firestore.
     * This method is a no-op; use the DeviceToken model directly.
     */
    public function removeDeviceToken(string $token): bool
    {
        // Device tokens are managed in Postgres (device_tokens table).
        // Firestore is permanently disabled. Nothing to remove from Firebase.
        Log::debug('[FirebaseSyncService] removeDeviceToken called (no-op — tokens managed in Postgres)');
        return true;
    }

    /**
     * Sync notification to RTDB notification_queue — Firestore permanently disabled.
     */
    public function syncNotification(int $userId, string $type, string $title, string $body, array $data = []): bool
    {
        if (!$this->isEnabled()) {
            return false;
        }

        try {
            $this->rtdb->getReference('notification_queue/' . $userId . '/' . now()->timestamp)->set([
                'user_id'    => (int) $userId,
                'type'       => $type,
                'title'      => $title,
                'body'       => $body,
                'data'       => $data,
                'read'       => false,
                'created_at' => now()->toIso8601String(),
            ]);

            // Also fire FCM
            $this->sendFcmNotification($userId, $title, $body, $data);

            Log::info('[FirebaseSyncService] Notification synced to RTDB', ['user_id' => $userId]);
            return true;
        } catch (Exception $e) {
            Log::error('[FirebaseSyncService] Notification RTDB sync failed', [
                'user_id' => $userId,
                'error'   => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Sync payment — payments are stored in Postgres (source of truth).
     * This method logs a payment completion event to RTDB notification_queue.
     */
    public function syncPayment(int $paymentId): bool
    {
        $payment = Payment::find($paymentId);
        if (!$payment) {
            Log::warning('[FirebaseSyncService] Payment not found', ['payment_id' => $paymentId]);
            return false;
        }

        // Payments are stored in Postgres. If there's a trip, update RTDB payment status.
        if ($payment->trip_id && $this->isEnabled()) {
            try {
                $this->rtdb->getReference('active_trips/' . $payment->trip_id)->update([
                    'payment_status'         => strtolower($payment->status ?? 'pending'),
                    'payment_amount'         => (float) $payment->amount,
                    'payment_transaction_id' => $payment->transaction_id ?? '',
                    'updated_at'             => now()->toIso8601String(),
                ]);
            } catch (Exception $e) {
                Log::warning('[FirebaseSyncService] Payment RTDB update failed', ['error' => $e->getMessage()]);
            }
        }

        Log::info('[FirebaseSyncService] Payment synced (Postgres is source of truth)', ['payment_id' => $paymentId]);
        return true;
    }

    /**
     * Sync rating — ratings are stored in Postgres (source of truth).
     * This method updates the driver's RTDB state with their new rating.
     */
    public function syncRating(int $ratingId): bool
    {
        $rating = Review::find($ratingId);
        if (!$rating) {
            Log::warning('[FirebaseSyncService] Rating not found', ['rating_id' => $ratingId]);
            return false;
        }

        // Update driver average rating in RTDB from Postgres data
        if ($rating->driver_id) {
            $this->updateDriverAverageRating((string) $rating->driver_id);
        }

        Log::info('[FirebaseSyncService] Rating synced (Postgres is source of truth)', ['rating_id' => $ratingId]);
        return true;
    }
}
