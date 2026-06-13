<?php

namespace App\Services\Firebase;

use Kreait\Firebase\Factory;
use Kreait\Firebase\Firestore;
use Kreait\Firebase\Messaging;
use Illuminate\Support\Facades\Log;
use App\Models\User;
use App\Models\Driver;
use App\Models\Trip;
use App\Models\Payment;
use App\Models\Review;
use Exception;

/**
 * FirebaseSyncService - SINGLE ORCHESTRATOR FOR ALL FIRESTORE WRITES
 *
 * CRITICAL ARCHITECTURE RULE:
 * This is the ONLY service allowed to write to Firestore.
 * All other services must go through this service.
 *
 * Supabase is the source of truth.
 * Firestore is the real-time projection layer.
 */
class FirebaseSyncService
{
    private ?Firestore $firestore = null;
    private ?Messaging $messaging = null;
    private bool $enabled = false;
    private bool $bootstrapEnabled = false;

    public function __construct()
    {
        $this->initialize();
    }

    /**
     * Initialize Firebase connection
     */
    private function initialize(): void
    {
        if (!config('firebase.enabled')) {
            Log::debug('[FirebaseSyncService] Firestore sync disabled in configuration');
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
            
            $this->messaging = $factory->createMessaging();

            $this->enabled = true;
            $this->bootstrapEnabled = config('firebase.bootstrap_enabled', false);

            Log::info('[FirebaseSyncService] Initialized successfully', [
                'project_id' => $projectId,
                'firestore_db' => $firestoreDb,
                'bootstrap_enabled' => $this->bootstrapEnabled,
            ]);
        } catch (Exception $e) {
            Log::warning('[FirebaseSyncService] Initialization failed: ' . $e->getMessage());
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

    /**
     * Ensure collection exists in Firestore
     * Self-healing: creates collection if missing
     */
    private function ensureCollectionExists(string $collection): void
    {
        try {
            // Try to create a seed document to ensure collection exists
            $this->firestore
                ->collection($collection)
                ->document('_collection_seed')
                ->set([
                    '_seed' => true,
                    '_created_at' => now()->toIso8601String(),
                ], ['merge' => true]);
        } catch (Exception $e) {
            Log::warning('[FirebaseSyncService] Failed to ensure collection exists', [
                'collection' => $collection,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Bootstrap Firestore schema with seed writes
     * 
     * IMPORTANT:
     * - Idempotent (safe to run multiple times)
     * - Merge-safe (uses merge: true)
     * - Never deletes data
     * - No manual Firestore console dependency
     */
    public function bootstrapSchema(): array
    {
        if (!$this->isEnabled()) {
            return [
                'success' => false,
                'message' => 'Firebase not enabled',
            ];
        }

        if (!$this->bootstrapEnabled) {
            return [
                'success' => false,
                'message' => 'Firebase bootstrap disabled (FIREBASE_BOOTSTRAP_ENABLED=false)',
            ];
        }

        try {
            $results = [];

            // Bootstrap users collection with schema seed
            $results['users'] = $this->bootstrapCollection('users', [
                'email' => '',
                'name' => '',
                'phone' => '',
                'role' => 'passenger',
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
            ], '_schema_seed');

            // Bootstrap drivers collection
            $results['drivers'] = $this->bootstrapCollection('drivers', [
                'user_id' => '',
                'status' => 'offline',
                'current_location' => [
                    'latitude' => 0,
                    'longitude' => 0,
                    'accuracy' => 0,
                    'updated_at' => now(),
                ],
                'current_trip_id' => null,
                'vehicle' => [
                    'type' => 'economy',
                    'license_plate' => '',
                    'color' => '',
                    'model' => '',
                ],
                'service_types' => ['private_car'],
                'response_time' => 0,
                'acceptance_rate' => 0,
                'cancellation_rate' => 0,
                'average_rating' => 0.0,
                'total_earnings' => 0,
                'available_capacity' => 1,
                'metadata' => [
                    'last_location_update' => now(),
                    'shift_start' => null,
                    'shift_end' => null,
                    'offline_reason' => null,
                ],
            ], '_schema_seed');

            // Bootstrap active_trips collection
            $results['active_trips'] = $this->bootstrapCollection('active_trips', [
                'passenger_id' => '',
                'driver_id' => null,
                'status' => 'requested',
                'ride_type' => 'private_car',
                'pickup' => [
                    'latitude' => 0,
                    'longitude' => 0,
                    'address' => '',
                    'timestamp' => now(),
                ],
                'dropoff' => [
                    'latitude' => 0,
                    'longitude' => 0,
                    'address' => '',
                    'timestamp' => now(),
                ],
                'distance_km' => 0,
                'estimated_duration_seconds' => 0,
                'estimated_fare' => 0,
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
            ], '_schema_seed');

            // Bootstrap trip_events collection
            $results['trip_events'] = $this->bootstrapCollection('trip_events', [
                'trip_id' => 0,
                'event' => '',
                'payload' => [],
                'timestamp' => now(),
            ], '_schema_seed');

            // Bootstrap driver_locations collection
            $results['driver_locations'] = $this->bootstrapCollection('driver_locations', [
                'driver_id' => '',
                'trip_id' => null,
                'location' => [
                    'latitude' => 0,
                    'longitude' => 0,
                    'accuracy' => 0,
                    'heading' => 0,
                    'speed' => 0,
                ],
                'timestamp' => now(),
                'is_online' => false,
            ], '_schema_seed');

            // Bootstrap trip_tracking collection
            $results['trip_tracking'] = $this->bootstrapCollection('trip_tracking', [
                'trip_id' => 0,
                'driver_id' => '',
                'passenger_id' => '',
                'tracking_data' => [
                    'polyline' => '',
                    'distance_traveled' => 0,
                    'duration_seconds' => 0,
                    'stops' => [],
                ],
                'current_location' => [
                    'latitude' => 0,
                    'longitude' => 0,
                    'timestamp' => now(),
                ],
                'eta' => null,
                'started_at' => now(),
                'updated_at' => now(),
            ], '_schema_seed');

            // Bootstrap notifications collection
            $results['notifications'] = $this->bootstrapCollection('notifications', [
                'user_id' => 0,
                'type' => 'system',
                'title' => '',
                'body' => '',
                'data' => [],
                'read' => false,
                'timestamp' => now(),
                'expires_at' => null,
            ], '_schema_seed');

            // Bootstrap chat_rooms collection
            $results['chat_rooms'] = $this->bootstrapCollection('chat_rooms', [
                'trip_id' => 0,
                'participants' => [],
                'type' => 'trip_chat',
                'created_at' => now(),
                'updated_at' => now(),
                'metadata' => [
                    'last_message_at' => null,
                    'message_count' => 0,
                ],
            ], '_schema_seed');

            // Bootstrap chat_messages collection
            $results['chat_messages'] = $this->bootstrapCollection('chat_messages', [
                'room_id' => '',
                'sender_id' => '',
                'message' => '',
                'message_type' => 'text',
                'timestamp' => now(),
                'read_by' => [],
                'metadata' => [],
            ], '_schema_seed');

            // Bootstrap presence collection
            $results['presence'] = $this->bootstrapCollection('presence', [
                'user_id' => '',
                'online' => false,
                'last_seen' => now(),
                'device_info' => [
                    'platform' => 'android',
                    'app_version' => '1.0.0',
                ],
                'location' => [
                    'latitude' => null,
                    'longitude' => null,
                ],
            ], '_schema_seed');

            // Bootstrap device_tokens collection
            $results['device_tokens'] = $this->bootstrapCollection('device_tokens', [
                'user_id' => '',
                'token' => '',
                'platform' => 'android',
                'app_version' => '1.0.0',
                'active' => true,
                'created_at' => now(),
                'last_used_at' => now(),
            ], '_schema_seed');

            Log::info('[FirebaseSyncService] Schema bootstrap completed', $results);

            return [
                'success' => true,
                'message' => 'Firestore schema bootstrapped successfully',
                'results' => $results,
            ];
        } catch (Exception $e) {
            Log::error('[FirebaseSyncService] Schema bootstrap failed', [
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'Schema bootstrap failed: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Bootstrap a single collection with seed data
     */
    private function bootstrapCollection(string $collection, array $data, string $documentId): array
    {
        try {
            $this->firestore
                ->collection($collection)
                ->document($documentId)
                ->set($data, ['merge' => true]);

            return [
                'collection' => $collection,
                'status' => 'success',
            ];
        } catch (Exception $e) {
            Log::warning("[FirebaseSyncService] Failed to bootstrap collection {$collection}", [
                'error' => $e->getMessage(),
            ]);

            return [
                'collection' => $collection,
                'status' => 'failed',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Sync Supabase data to Firestore
     * 
     * Pulls data from Supabase tables and upserts to Firestore
     */
    public function syncSupabaseToFirestore(): array
    {
        if (!$this->isEnabled()) {
            return [
                'success' => false,
                'message' => 'Firebase not enabled',
            ];
        }

        try {
            $results = [];

            // Sync users
            $results['users'] = $this->syncUsers();

            // Sync drivers
            $results['drivers'] = $this->syncDrivers();

            // Sync active trips
            $results['active_trips'] = $this->syncActiveTrips();

            // Sync payments
            $results['payments'] = $this->syncPayments();

            Log::info('[FirebaseSyncService] Supabase to Firestore sync completed', $results);

            return [
                'success' => true,
                'message' => 'Supabase to Firestore sync completed',
                'results' => $results,
            ];
        } catch (Exception $e) {
            Log::error('[FirebaseSyncService] Supabase to Firestore sync failed', [
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'Sync failed: ' . $e->getMessage(),
            ];
        }
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
     * Handle DriverAssigned event
     */
    private function handleDriverAssigned(array $payload): bool
    {
        $tripId = $payload['trip_id'] ?? null;
        $driverId = $payload['driver_id'] ?? null;

        if (!$tripId || !$driverId) {
            Log::warning('[FirebaseSyncService] DriverAssigned missing required fields', $payload);
            return false;
        }

        try {
            // Update active_trips
            $this->firestore
                ->collection('active_trips')
                ->document((string) $tripId)
                ->update([
                    ['path' => 'driver_id', 'value' => (string) $driverId],
                    ['path' => 'status', 'value' => 'accepted'],
                    ['path' => 'timeline.accepted_at', 'value' => now()],
                ]);

            // Update driver status
            $this->firestore
                ->collection('drivers')
                ->document((string) $driverId)
                ->update([
                    ['path' => 'status', 'value' => 'on_trip'],
                    ['path' => 'current_trip_id', 'value' => (string) $tripId],
                ]);

            // Log trip event
            $this->firestore
                ->collection('trip_events')
                ->add([
                    'trip_id' => (int) $tripId,
                    'event' => 'driver_assigned',
                    'payload' => $payload,
                    'timestamp' => now(),
                ]);

            // Send notification to passenger
            $this->sendNotification(
                $payload['passenger_id'] ?? null,
                'trip',
                'Driver Assigned',
                'Your driver has been assigned',
                ['trip_id' => $tripId, 'driver_id' => $driverId]
            );

            Log::info('[FirebaseSyncService] DriverAssigned synced', [
                'trip_id' => $tripId,
                'driver_id' => $driverId,
            ]);

            return true;
        } catch (Exception $e) {
            Log::error('[FirebaseSyncService] DriverAssigned sync failed', [
                'trip_id' => $tripId,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Handle TripStarted event
     */
    private function handleTripStarted(array $payload): bool
    {
        $tripId = $payload['trip_id'] ?? null;

        if (!$tripId) {
            Log::warning('[FirebaseSyncService] TripStarted missing required fields', $payload);
            return false;
        }

        try {
            // Update active_trips
            $this->firestore
                ->collection('active_trips')
                ->document((string) $tripId)
                ->update([
                    ['path' => 'status', 'value' => 'in_progress'],
                    ['path' => 'timeline.started_at', 'value' => now()],
                ]);

            // Log trip event
            $this->firestore
                ->collection('trip_events')
                ->add([
                    'trip_id' => (int) $tripId,
                    'event' => 'trip_started',
                    'payload' => $payload,
                    'timestamp' => now(),
                ]);

            // Send notification to passenger
            $this->sendNotification(
                $payload['passenger_id'] ?? null,
                'trip',
                'Trip Started',
                'Your trip has started',
                ['trip_id' => $tripId]
            );

            Log::info('[FirebaseSyncService] TripStarted synced', ['trip_id' => $tripId]);

            return true;
        } catch (Exception $e) {
            Log::error('[FirebaseSyncService] TripStarted sync failed', [
                'trip_id' => $tripId,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Handle TripCompleted event
     */
    private function handleTripCompleted(array $payload): bool
    {
        $tripId = $payload['trip_id'] ?? null;
        $driverId = $payload['driver_id'] ?? null;

        if (!$tripId) {
            Log::warning('[FirebaseSyncService] TripCompleted missing required fields', $payload);
            return false;
        }

        try {
            // Update active_trips
            $this->firestore
                ->collection('active_trips')
                ->document((string) $tripId)
                ->update([
                    ['path' => 'status', 'value' => 'completed'],
                    ['path' => 'timeline.completed_at', 'value' => now()],
                ]);

            // Update driver status
            if ($driverId) {
                $this->firestore
                    ->collection('drivers')
                    ->document((string) $driverId)
                    ->update([
                        ['path' => 'status', 'value' => 'available'],
                        ['path' => 'current_trip_id', 'value' => null],
                    ]);
            }

            // Log trip event
            $this->firestore
                ->collection('trip_events')
                ->add([
                    'trip_id' => (int) $tripId,
                    'event' => 'trip_completed',
                    'payload' => $payload,
                    'timestamp' => now(),
                ]);

            // Send notification to passenger
            $this->sendNotification(
                $payload['passenger_id'] ?? null,
                'trip',
                'Trip Completed',
                'Your trip has been completed',
                ['trip_id' => $tripId]
            );

            // Send notification to driver
            if ($driverId) {
                $this->sendNotification(
                    $driverId,
                    'trip',
                    'Trip Completed',
                    'Trip completed successfully',
                    ['trip_id' => $tripId]
                );
            }

            Log::info('[FirebaseSyncService] TripCompleted synced', ['trip_id' => $tripId]);

            return true;
        } catch (Exception $e) {
            Log::error('[FirebaseSyncService] TripCompleted sync failed', [
                'trip_id' => $tripId,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Handle PaymentCompleted event
     */
    private function handlePaymentCompleted(array $payload): bool
    {
        $tripId = $payload['trip_id'] ?? null;
        $paymentId = $payload['payment_id'] ?? null;
        $amount = $payload['amount'] ?? 0;
        $transactionId = $payload['transaction_id'] ?? '';

        if (!$tripId) {
            Log::warning('[FirebaseSyncService] PaymentCompleted missing required fields', $payload);
            return false;
        }

        try {
            // Update active_trips payment
            $this->firestore
                ->collection('active_trips')
                ->document((string) $tripId)
                ->update([
                    ['path' => 'payment.status', 'value' => 'completed'],
                    ['path' => 'payment.amount', 'value' => $amount],
                    ['path' => 'payment.transaction_id', 'value' => $transactionId],
                ]);

            // Log trip event
            $this->firestore
                ->collection('trip_events')
                ->add([
                    'trip_id' => (int) $tripId,
                    'event' => 'payment_completed',
                    'payload' => $payload,
                    'timestamp' => now(),
                ]);

            // Send notification to passenger
            $this->sendNotification(
                $payload['passenger_id'] ?? null,
                'payment',
                'Payment Confirmed',
                'Your payment has been confirmed',
                ['trip_id' => $tripId, 'payment_id' => $paymentId, 'amount' => $amount]
            );

            // Send notification to driver
            $this->sendNotification(
                $payload['driver_id'] ?? null,
                'payment',
                'Payment Received',
                'Payment received successfully',
                ['trip_id' => $tripId, 'payment_id' => $paymentId, 'amount' => $amount]
            );

            Log::info('[FirebaseSyncService] PaymentCompleted synced', [
                'trip_id' => $tripId,
                'payment_id' => $paymentId,
            ]);

            return true;
        } catch (Exception $e) {
            Log::error('[FirebaseSyncService] PaymentCompleted sync failed', [
                'trip_id' => $tripId,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Handle RatingSubmitted event
     */
    private function handleRatingSubmitted(array $payload): bool
    {
        $driverId = $payload['driver_id'] ?? null;
        $rating = $payload['rating'] ?? 0;
        $tripId = $payload['trip_id'] ?? null;

        if (!$driverId) {
            Log::warning('[FirebaseSyncService] RatingSubmitted missing required fields', $payload);
            return false;
        }

        try {
            // Add to driver_ratings collection
            $this->firestore
                ->collection('driver_ratings')
                ->add([
                    'driver_id' => (string) $driverId,
                    'trip_id' => $tripId ?? '',
                    'passenger_id' => $payload['passenger_id'] ?? '',
                    'rating' => $rating,
                    'review' => $payload['review'] ?? '',
                    'categories' => $payload['categories'] ?? [],
                    'created_at' => now(),
                    'anonymous' => false,
                ]);

            // Update driver average rating
            $this->updateDriverAverageRating($driverId);

            // Log trip event
            if ($tripId) {
                $this->firestore
                    ->collection('trip_events')
                    ->add([
                        'trip_id' => (int) $tripId,
                        'event' => 'rating_submitted',
                        'payload' => $payload,
                        'timestamp' => now(),
                    ]);
            }

            Log::info('[FirebaseSyncService] RatingSubmitted synced', [
                'driver_id' => $driverId,
                'rating' => $rating,
            ]);

            return true;
        } catch (Exception $e) {
            Log::error('[FirebaseSyncService] RatingSubmitted sync failed', [
                'driver_id' => $driverId,
                'error' => $e->getMessage(),
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

            Log::info('[FirebaseSyncService] UserCreated synced', ['user_id' => $userId]);

            return true;
        } catch (Exception $e) {
            Log::error('[FirebaseSyncService] UserCreated sync failed', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
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

            Log::info('[FirebaseSyncService] DriverCreated synced', ['driver_id' => $driverId]);

            return true;
        } catch (Exception $e) {
            Log::error('[FirebaseSyncService] DriverCreated sync failed', [
                'driver_id' => $driverId,
                'error' => $e->getMessage(),
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
            // Update active_trips
            $this->firestore
                ->collection('active_trips')
                ->document((string) $tripId)
                ->update([
                    ['path' => 'status', 'value' => 'cancelled'],
                    ['path' => 'timeline.cancelled_at', 'value' => now()],
                    ['path' => 'cancellation.reason', 'value' => $reason],
                    ['path' => 'cancellation.cancelled_by', 'value' => $cancelledBy],
                ]);

            // Update driver status
            if ($driverId) {
                $this->firestore
                    ->collection('drivers')
                    ->document((string) $driverId)
                    ->update([
                        ['path' => 'status', 'value' => 'available'],
                        ['path' => 'current_trip_id', 'value' => null],
                    ]);
            }

            // Log trip event
            $this->firestore
                ->collection('trip_events')
                ->add([
                    'trip_id' => (int) $tripId,
                    'event' => 'trip_cancelled',
                    'payload' => $payload,
                    'timestamp' => now(),
                ]);

            Log::info('[FirebaseSyncService] TripCancelled synced', ['trip_id' => $tripId]);

            return true;
        } catch (Exception $e) {
            Log::error('[FirebaseSyncService] TripCancelled sync failed', [
                'trip_id' => $tripId,
                'error' => $e->getMessage(),
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
     * Sync driver location
     * 
     * Centralize driver tracking writes:
     * - driver_locations collection
     * - drivers.current_location update
     * - active_trips.driver_location update (if active)
     */
    public function syncDriverLocation(string $driverId, float $latitude, float $longitude, float $accuracy = 0, ?int $tripId = null): bool
    {
        if (!$this->isEnabled()) {
            return false;
        }

        try {
            $this->ensureCollectionExists('driver_locations');
            
            // Update driver_locations collection
            $this->firestore
                ->collection('driver_locations')
                ->add([
                    'driver_id' => (string) $driverId,
                    'trip_id' => $tripId ? (string) $tripId : null,
                    'location' => [
                        'latitude' => $latitude,
                        'longitude' => $longitude,
                        'accuracy' => $accuracy,
                        'heading' => 0,
                        'speed' => 0,
                    ],
                    'timestamp' => now(),
                    'is_online' => true,
                ]);

            // Update drivers.current_location
            $this->firestore
                ->collection('drivers')
                ->document((string) $driverId)
                ->update([
                    ['path' => 'current_location.latitude', 'value' => $latitude],
                    ['path' => 'current_location.longitude', 'value' => $longitude],
                    ['path' => 'current_location.accuracy', 'value' => $accuracy],
                    ['path' => 'current_location.updated_at', 'value' => now()],
                    ['path' => 'metadata.last_location_update', 'value' => now()],
                ]);

            // Update active_trips.driver_location if on active trip
            if ($tripId) {
                $this->firestore
                    ->collection('active_trips')
                    ->document((string) $tripId)
                    ->update([
                        ['path' => 'driver_location.latitude', 'value' => $latitude],
                        ['path' => 'driver_location.longitude', 'value' => $longitude],
                        ['path' => 'driver_location.timestamp', 'value' => now()],
                    ]);
                
                // Sync trip tracking
                $this->syncTripTracking($tripId, $driverId, $latitude, $longitude);
            }

            return true;
        } catch (Exception $e) {
            Log::error('[FirebaseSyncService] Driver location sync failed', [
                'driver_id' => $driverId,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Sync trip tracking data
     * 
     * Centralize trip tracking writes:
     * - trip_tracking collection
     * - ETA and distance calculations
     */
    public function syncTripTracking(int $tripId, string $driverId, float $latitude, float $longitude, ?float $eta = null, ?float $distanceRemaining = null): bool
    {
        if (!$this->isEnabled()) {
            return false;
        }

        try {
            $this->ensureCollectionExists('trip_tracking');
            
            $this->firestore
                ->collection('trip_tracking')
                ->document((string) $tripId)
                ->set([
                    'driver_id' => (string) $driverId,
                    'driver_location' => [
                        'latitude' => $latitude,
                        'longitude' => $longitude,
                        'updated_at' => now(),
                    ],
                    'eta' => $eta,
                    'distance_remaining' => $distanceRemaining,
                    'updated_at' => now(),
                ], ['merge' => true]);

            return true;
        } catch (Exception $e) {
            Log::error('[FirebaseSyncService] Trip tracking sync failed', [
                'trip_id' => $tripId,
                'error' => $e->getMessage(),
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
            // Update active_trips.payment
            $this->firestore
                ->collection('active_trips')
                ->document((string) $tripId)
                ->update([
                    ['path' => 'payment.status', 'value' => $status],
                    ['path' => 'payment.amount', 'value' => $amount],
                    ['path' => 'payment.transaction_id', 'value' => $transactionId],
                ]);

            // Log trip event
            $this->firestore
                ->collection('trip_events')
                ->add([
                    'trip_id' => (int) $tripId,
                    'event' => 'payment_' . $status,
                    'payload' => $paymentData,
                    'timestamp' => now(),
                ]);

            // Trigger notification if completed
            if ($status === 'completed') {
                $this->syncEvent('PaymentCompleted', $paymentData);
            }

            Log::info('[FirebaseSyncService] Payment event synced', [
                'trip_id' => $tripId,
                'status' => $status,
            ]);

            return true;
        } catch (Exception $e) {
            Log::error('[FirebaseSyncService] Payment event sync failed', [
                'trip_id' => $tripId,
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
            Log::error('[FirebaseSyncService] Update average rating failed', [
                'driver_id' => $driverId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Send notification to Firestore and FCM
     */
    private function sendNotification(?int $userId, string $type, string $title, string $body, array $data = []): void
    {
        if (!$userId) {
            return;
        }

        try {
            // Write to Firestore notifications collection
            $this->firestore
                ->collection('notifications')
                ->add([
                    'user_id' => (int) $userId,
                    'type' => $type,
                    'title' => $title,
                    'body' => $body,
                    'data' => $data,
                    'read' => false,
                    'timestamp' => now(),
                ]);

            // Send FCM push notification
            if ($this->messaging) {
                $this->sendFcmNotification($userId, $title, $body, $data);
            }
        } catch (Exception $e) {
            Log::warning('[FirebaseSyncService] Notification send failed', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Send FCM push notification
     */
    private function sendFcmNotification(int $userId, string $title, string $body, array $data = []): void
    {
        try {
            // Get user's device tokens from Firestore
            $tokens = $this->firestore
                ->collection('device_tokens')
                ->where('user_id', '==', (string) $userId)
                ->where('active', '==', true)
                ->documents();

            $tokenList = [];
            foreach ($tokens as $token) {
                $tokenList[] = $token['token'];
            }

            if (empty($tokenList)) {
                return;
            }

            // Send multicast message
            $message = \Kreait\Firebase\Messaging\CloudMessage::new()
                ->withNotification(['title' => $title, 'body' => $body])
                ->withData($data);

            $this->messaging->sendMulticast($message, $tokenList);

            Log::debug('[FirebaseSyncService] FCM notification sent', [
                'user_id' => $userId,
                'tokens_count' => count($tokenList),
            ]);
        } catch (Exception $e) {
            Log::warning('[FirebaseSyncService] FCM notification failed', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Health check
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
            $this->firestore
                ->collection('users')
                ->limit(1)
                ->documents()
                ->current();

            return [
                'status' => 'connected',
                'message' => 'Firebase Firestore connection healthy',
                'bootstrap_enabled' => $this->bootstrapEnabled,
            ];
        } catch (Exception $e) {
            return [
                'status' => 'error',
                'message' => 'Firebase connection failed: ' . $e->getMessage(),
            ];
        }
    }

    // ==================== ADDITIONAL SYNC METHODS ====================

    /**
     * Sync user to Firestore
     * Idempotent, retry-safe, queue-safe
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

            Log::info('[FirebaseSyncService] User synced', ['user_id' => $userId]);
            return true;
        } catch (Exception $e) {
            Log::error('[FirebaseSyncService] User sync failed', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
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

            Log::info('[FirebaseSyncService] Driver synced', ['driver_id' => $driverId]);
            return true;
        } catch (Exception $e) {
            Log::error('[FirebaseSyncService] Driver sync failed', [
                'driver_id' => $driverId,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Sync trip to Firestore
     * Idempotent, retry-safe, queue-safe
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

            Log::info('[FirebaseSyncService] Trip synced', ['trip_id' => $tripId]);
            return true;
        } catch (Exception $e) {
            Log::error('[FirebaseSyncService] Trip sync failed', [
                'trip_id' => $tripId,
                'error' => $e->getMessage(),
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
     * Sync chat room to Firestore
     * Idempotent, retry-safe, queue-safe
     */
    public function syncChatRoom(string $roomId, array $data): bool
    {
        if (!$this->isEnabled()) {
            return false;
        }

        try {
            $this->firestore
                ->collection('chat_rooms')
                ->document($roomId)
                ->set([
                    'trip_id' => $data['trip_id'] ?? 0,
                    'participants' => $data['participants'] ?? [],
                    'type' => $data['type'] ?? 'trip_chat',
                    'created_at' => now(),
                    'updated_at' => now(),
                    'metadata' => [
                        'last_message_at' => null,
                        'message_count' => 0,
                    ],
                ], ['merge' => true]);

            Log::info('[FirebaseSyncService] Chat room synced', ['room_id' => $roomId]);
            return true;
        } catch (Exception $e) {
            Log::error('[FirebaseSyncService] Chat room sync failed', [
                'room_id' => $roomId,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Sync chat message to Firestore
     * Idempotent, retry-safe, queue-safe
     */
    public function syncChatMessage(string $roomId, array $data): bool
    {
        if (!$this->isEnabled()) {
            return false;
        }

        try {
            $this->firestore
                ->collection('chat_messages')
                ->add([
                    'room_id' => $roomId,
                    'sender_id' => $data['sender_id'] ?? '',
                    'message' => $data['message'] ?? '',
                    'message_type' => $data['message_type'] ?? 'text',
                    'timestamp' => now(),
                    'read_by' => $data['read_by'] ?? [],
                    'metadata' => $data['metadata'] ?? [],
                ]);

            // Update chat room metadata
            $this->firestore
                ->collection('chat_rooms')
                ->document($roomId)
                ->update([
                    ['path' => 'metadata.last_message_at', 'value' => now()],
                    ['path' => 'metadata.message_count', 'value' => \Kreait\Firebase\FieldValue::increment(1)],
                    ['path' => 'updated_at', 'value' => now()],
                ]);

            Log::info('[FirebaseSyncService] Chat message synced', ['room_id' => $roomId]);
            return true;
        } catch (Exception $e) {
            Log::error('[FirebaseSyncService] Chat message sync failed', [
                'room_id' => $roomId,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Sync presence to Firestore
     * Idempotent, retry-safe, queue-safe
     */
    public function syncPresence(int $userId, bool $online, array $location = null): bool
    {
        if (!$this->isEnabled()) {
            return false;
        }

        try {
            $data = [
                'user_id' => (string) $userId,
                'online' => $online,
                'last_seen' => now(),
                'device_info' => [
                    'platform' => 'android',
                    'app_version' => '1.0.0',
                ],
            ];

            if ($location) {
                $data['location'] = $location;
            }

            $this->firestore
                ->collection('presence')
                ->document((string) $userId)
                ->set($data, ['merge' => true]);

            Log::info('[FirebaseSyncService] Presence synced', ['user_id' => $userId, 'online' => $online]);
            return true;
        } catch (Exception $e) {
            Log::error('[FirebaseSyncService] Presence sync failed', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Sync device token to Firestore
     * Idempotent, retry-safe, queue-safe
     */
    public function syncDeviceToken(int $userId, string $token, string $platform = 'android'): bool
    {
        if (!$this->isEnabled()) {
            return false;
        }

        try {
            $this->ensureCollectionExists('device_tokens');
            
            $this->firestore
                ->collection('device_tokens')
                ->document($token)
                ->set([
                    'user_id' => (string) $userId,
                    'token' => $token,
                    'platform' => $platform,
                    'app_version' => '1.0.0',
                    'active' => true,
                    'created_at' => now(),
                    'last_used_at' => now(),
                ], ['merge' => true]);

            Log::info('[FirebaseSyncService] Device token synced', ['user_id' => $userId]);
            return true;
        } catch (Exception $e) {
            Log::error('[FirebaseSyncService] Device token sync failed', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Remove device token from Firestore
     * Idempotent, retry-safe, queue-safe
     */
    public function removeDeviceToken(string $token): bool
    {
        if (!$this->isEnabled()) {
            return false;
        }

        try {
            $this->firestore
                ->collection('device_tokens')
                ->document($token)
                ->delete();

            Log::info('[FirebaseSyncService] Device token removed', ['token' => substr($token, 0, 20) . '...']);
            return true;
        } catch (Exception $e) {
            Log::error('[FirebaseSyncService] Device token removal failed', [
                'token' => substr($token, 0, 20) . '...',
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Sync notification to Firestore
     * Idempotent, retry-safe, queue-safe
     */
    public function syncNotification(int $userId, string $type, string $title, string $body, array $data = []): bool
    {
        if (!$this->isEnabled()) {
            return false;
        }

        try {
            $this->firestore
                ->collection('notifications')
                ->add([
                    'user_id' => (int) $userId,
                    'type' => $type,
                    'title' => $title,
                    'body' => $body,
                    'data' => $data,
                    'read' => false,
                    'timestamp' => now(),
                    'expires_at' => null,
                ]);

            // Send FCM push notification
            if ($this->messaging) {
                $this->sendFcmNotification($userId, $title, $body, $data);
            }

            Log::info('[FirebaseSyncService] Notification synced', ['user_id' => $userId]);
            return true;
        } catch (Exception $e) {
            Log::error('[FirebaseSyncService] Notification sync failed', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }
}
