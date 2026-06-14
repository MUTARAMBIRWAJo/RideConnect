<?php

namespace App\Services\Firebase;

use Kreait\Firebase\Factory;
use Kreait\Firebase\Firestore;
use Illuminate\Support\Facades\Log;
use Exception;

/**
 * FirebaseBootstrapService - Firestore Schema Bootstrap Engine
 *
 * CRITICAL ARCHITECTURE RULE:
 * This service is responsible for auto-bootstrapping Firestore schema.
 * It creates all required collections and system documents.
 * NO manual Firestore console setup required.
 *
 * Features:
 * - Idempotent bootstrap (safe to run multiple times)
 * - Merge-safe operations (uses merge: true)
 * - Schema validation
 * - System document seeding
 */
class FirebaseBootstrapService
{
    private ?Firestore $firestore = null;
    private bool $enabled = false;
    private bool $bootstrapEnabled = false;

    /**
     * Required collections for Firestore schema
     */
    private const REQUIRED_COLLECTIONS = [
        'users',
        'drivers',
        'active_trips',
        'trip_events',
        'driver_locations',
        'trip_tracking',
        'notifications',
        'presence',
        'device_tokens',
        'payments',
        'ratings',
        'chat_rooms',
        'chat_messages',
    ];

    /**
     * System document IDs
     */
    private const SYSTEM_CONFIG_DOC = '_system_config';
    private const APP_METADATA_DOC = '_app_metadata';
    private const REALTIME_FLAGS_DOC = '_realtime_flags';

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
            Log::debug('[FirebaseBootstrapService] Firebase disabled in configuration');
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
            $this->bootstrapEnabled = config('firebase.bootstrap_enabled', false);

            Log::info('[FirebaseBootstrapService] Initialized successfully', [
                'project_id' => $projectId,
                'firestore_db' => $firestoreDb,
                'bootstrap_enabled' => $this->bootstrapEnabled,
            ]);

            // Auto-bootstrap if enabled
            if ($this->bootstrapEnabled) {
                $this->autoBootstrap();
            }
        } catch (Exception $e) {
            Log::warning('[FirebaseBootstrapService] Initialization failed: ' . $e->getMessage());
            $this->enabled = false;
        }
    }

    /**
     * Auto-bootstrap on startup
     */
    private function autoBootstrap(): void
    {
        try {
            $health = $this->validateSchemaHealth();
            
            if (!$health['ready']) {
                Log::info('[FirebaseBootstrapService] Schema not ready, auto-bootstrapping...', [
                    'missing_collections' => $health['missing'],
                ]);
                
                $this->bootstrapSchema();
            } else {
                Log::info('[FirebaseBootstrapService] Schema already ready, skipping bootstrap');
            }
        } catch (Exception $e) {
            Log::warning('[FirebaseBootstrapService] Auto-bootstrap failed: ' . $e->getMessage());
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
     * Check if bootstrap is enabled
     */
    public function isBootstrapEnabled(): bool
    {
        return $this->bootstrapEnabled;
    }

    /**
     * Bootstrap Firestore schema
     *
     * Creates all required collections with seed documents
     * Idempotent - safe to run multiple times
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

            // Bootstrap each collection with schema seed
            foreach (self::REQUIRED_COLLECTIONS as $collection) {
                $results[$collection] = $this->bootstrapCollection($collection);
            }

            // Seed system documents
            $results['system_documents'] = $this->seedDefaultDocuments();

            Log::info('[FirebaseBootstrapService] Schema bootstrap completed', $results);

            return [
                'success' => true,
                'message' => 'Firestore schema bootstrapped successfully',
                'results' => $results,
            ];
        } catch (Exception $e) {
            Log::error('[FirebaseBootstrapService] Schema bootstrap failed', [
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'Schema bootstrap failed: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Bootstrap a single collection with seed document
     */
    private function bootstrapCollection(string $collection): array
    {
        try {
            // Create a schema seed document to ensure collection exists
            $this->firestore
                ->collection($collection)
                ->document('_schema_seed')
                ->set([
                    '_schema_version' => '1.0.0',
                    '_bootstrapped_at' => now()->toIso8601String(),
                    '_collection' => $collection,
                ], ['merge' => true]);

            return [
                'collection' => $collection,
                'status' => 'success',
            ];
        } catch (Exception $e) {
            Log::warning("[FirebaseBootstrapService] Failed to bootstrap collection {$collection}", [
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
     * Seed default system documents
     */
    public function seedDefaultDocuments(): array
    {
        try {
            $results = [];

            // System config document
            $results['system_config'] = $this->seedSystemConfig();

            // App metadata document
            $results['app_metadata'] = $this->seedAppMetadata();

            // Realtime flags document
            $results['realtime_flags'] = $this->seedRealtimeFlags();

            return [
                'success' => true,
                'documents' => $results,
            ];
        } catch (Exception $e) {
            Log::error('[FirebaseBootstrapService] Failed to seed default documents', [
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Seed system config document
     */
    private function seedSystemConfig(): array
    {
        try {
            $this->firestore
                ->collection('_system')
                ->document(self::SYSTEM_CONFIG_DOC)
                ->set([
                    'version' => '1.0.0',
                    'environment' => config('app.env'),
                    'bootstrapped_at' => now()->toIso8601String(),
                    'schema_version' => '1.0.0',
                    'features' => [
                        'realtime_sync' => true,
                        'push_notifications' => true,
                        'chat' => true,
                        'location_tracking' => true,
                    ],
                ], ['merge' => true]);

            return ['status' => 'success'];
        } catch (Exception $e) {
            return ['status' => 'failed', 'error' => $e->getMessage()];
        }
    }

    /**
     * Seed app metadata document
     */
    private function seedAppMetadata(): array
    {
        try {
            $this->firestore
                ->collection('_system')
                ->document(self::APP_METADATA_DOC)
                ->set([
                    'app_name' => config('app.name'),
                    'app_url' => config('app.url'),
                    'timezone' => config('app.timezone'),
                    'locale' => config('app.locale'),
                    'last_updated' => now()->toIso8601String(),
                ], ['merge' => true]);

            return ['status' => 'success'];
        } catch (Exception $e) {
            return ['status' => 'failed', 'error' => $e->getMessage()];
        }
    }

    /**
     * Seed realtime flags document
     */
    private function seedRealtimeFlags(): array
    {
        try {
            $this->firestore
                ->collection('_system')
                ->document(self::REALTIME_FLAGS_DOC)
                ->set([
                    'driver_location_sync_enabled' => true,
                    'trip_event_sync_enabled' => true,
                    'payment_sync_enabled' => true,
                    'notification_sync_enabled' => true,
                    'chat_sync_enabled' => true,
                    'presence_sync_enabled' => true,
                    'last_updated' => now()->toIso8601String(),
                ], ['merge' => true]);

            return ['status' => 'success'];
        } catch (Exception $e) {
            return ['status' => 'failed', 'error' => $e->getMessage()];
        }
    }

    /**
     * Validate schema health
     *
     * Returns schema readiness status
     */
    public function validateSchemaHealth(): array
    {
        if (!$this->isEnabled()) {
            return [
                'ready' => false,
                'message' => 'Firebase not enabled',
                'collections_ready' => [],
                'missing' => self::REQUIRED_COLLECTIONS,
                'warnings' => [],
                'total_collections' => count(self::REQUIRED_COLLECTIONS),
                'ready_collections' => 0,
            ];
        }

        try {
            $collectionsReady = [];
            $missing = [];
            $warnings = [];

            foreach (self::REQUIRED_COLLECTIONS as $collection) {
                try {
                    // Try to read the schema seed document
                    $this->firestore
                        ->collection($collection)
                        ->document('_schema_seed')
                        ->snapshot();

                    $collectionsReady[] = $collection;
                } catch (Exception $e) {
                    $missing[] = $collection;
                    $warnings[] = "Collection {$collection} missing or inaccessible: " . $e->getMessage();
                }
            }

            // Check system documents
            try {
                $this->firestore
                    ->collection('_system')
                    ->document(self::SYSTEM_CONFIG_DOC)
                    ->snapshot();
            } catch (Exception $e) {
                $warnings[] = "System config document missing: " . $e->getMessage();
            }

            $ready = empty($missing);

            return [
                'ready' => $ready,
                'collections_ready' => $collectionsReady,
                'missing' => $missing,
                'warnings' => $warnings,
                'total_collections' => count(self::REQUIRED_COLLECTIONS),
                'ready_collections' => count($collectionsReady),
            ];
        } catch (Exception $e) {
            return [
                'ready' => false,
                'message' => 'Schema validation failed: ' . $e->getMessage(),
                'collections_ready' => [],
                'missing' => self::REQUIRED_COLLECTIONS,
                'warnings' => [$e->getMessage()],
                'total_collections' => count(self::REQUIRED_COLLECTIONS),
                'ready_collections' => 0,
            ];
        }
    }

    /**
     * Get required collections list
     */
    public function getRequiredCollections(): array
    {
        return self::REQUIRED_COLLECTIONS;
    }
}
