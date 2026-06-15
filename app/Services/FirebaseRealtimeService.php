<?php

namespace App\Services;

use Kreait\Firebase\Factory;
use Illuminate\Support\Facades\Log;
use App\Services\Firebase\FirebaseSyncService;
use App\Services\Firebase\FirebaseHealthService;

/**
 * FirebaseRealtimeService - COMPATIBILITY WRAPPER (Read-Only)
 *
 * CRITICAL ARCHITECTURE RULE:
 * This service NO LONGER writes to Firestore directly.
 * All writes MUST go through FirebaseSyncService::syncEvent()
 *
 * This service is now ONLY for:
 * - Connectivity status checks
 * - Realtime Database read operations (legacy)
 * - Health monitoring
 *
 * @deprecated Use FirebaseSyncService for all Firestore writes
 */
class FirebaseRealtimeService
{
    private ?\Kreait\Firebase\Database $database = null;
    private ?\Kreait\Firebase\Firestore $firestore = null;
    private bool $enabled = false;
    private bool $initialized = false;

    public function __construct(
        private readonly FirebaseSyncService $firebaseSyncService,
        private readonly FirebaseHealthService $healthService,
    ) {
        // Lazy initialization — do NOT connect to Firestore here.
    }

    /**
     * Lazy-initialize Firebase connection on first use.
     */
    private function ensureInitialized(): void
    {
        if ($this->initialized) {
            return;
        }
        $this->initialized = true;

        $projectId     = config('services.firebase.project_id');
        $credentials   = config('services.firebase.credentials');
        $databaseUrl   = config('services.firebase.database_url');
        $firestoreDb   = config('services.firebase.firestore_database');

        if (! $projectId || ! $credentials || ! file_exists($credentials)) {
            Log::debug('FirebaseRealtimeService: disabled — no project/credentials configured');
            return;
        }

        try {
            $factory = (new Factory)->withServiceAccount($credentials)->withProjectId($projectId);

            // Firestore — only if ext-grpc is available
            if ($this->healthService->grpcAvailable()) {
                if ($firestoreDb) {
                    $this->firestore = $factory->createFirestore()->database($firestoreDb);
                } else {
                    $this->firestore = $factory->createFirestore()->database();
                }
            } else {
                Log::info('[FirebaseRealtimeService] ext-grpc not installed — Firestore skipped');
            }

            // Realtime DB (used by the legacy /realtime/config endpoint)
            if ($databaseUrl) {
                $this->database = $factory->createDatabase()->withUri($databaseUrl);
            }

            $this->enabled = true;
            Log::info('FirebaseRealtimeService: initialised (read-only mode)', [
                'project_id' => $projectId,
                'firestore_db' => $firestoreDb ?? '(default)',
                'firestore_available' => $this->firestore !== null,
                'database_url' => $databaseUrl,
            ]);
        } catch (\Throwable $e) {
            Log::warning('FirebaseRealtimeService: init failed — ' . $e->getMessage());
            $this->enabled = false;
        }
    }

    /**
     * Push a trip lifecycle event into Firestore.
     *
     * DEPRECATED: Now routes through FirebaseSyncService
     *
     * @deprecated Use FirebaseSyncService::syncEvent() instead
     */
    public function pushTripEvent(string $tripId, string $event, array $payload = []): void
    {
        Log::warning('[FirebaseRealtimeService] pushTripEvent called - DEPRECATED. Use FirebaseSyncService::syncEvent() instead', [
            'trip_id' => $tripId,
            'event' => $event,
        ]);

        // Route through FirebaseSyncService
        $this->firebaseSyncService->syncEvent($event, array_merge($payload, ['trip_id' => $tripId]));
    }

    /**
     * Push an in-app notification into Firestore.
     *
     * DEPRECATED: Now routes through FirebaseSyncService
     *
     * @deprecated Use FirebaseSyncService for notification sync
     */
    public function pushNotification(string $userId, string $type, string $title, string $body, array $data = []): void
    {
        Log::warning('[FirebaseRealtimeService] pushNotification called - DEPRECATED. Use FirebaseSyncService for notifications', [
            'user_id' => $userId,
        ]);

        // No-op - notifications are now sent automatically by FirebaseSyncService event handlers
    }

    public function isEnabled(): bool
    {
        $this->ensureInitialized();
        return $this->enabled;
    }

    /**
     * @return array<string, mixed>
     */
    public function connectivityStatus(): array
    {
        $this->ensureInitialized();

        if (! $this->enabled) {
            return [
                'firestore' => false,
                'realtime_database' => false,
                'message' => 'Firebase client not initialized',
            ];
        }

        $firestoreOk = false;
        $rtdbOk = false;

        if ($this->firestore) {
            try {
                $this->firestore->collection('users')->limit(1)->documents();
                $firestoreOk = true;
            } catch (\Throwable $exception) {
                return [
                    'firestore' => false,
                    'realtime_database' => false,
                    'message' => 'Firestore probe failed: '.$exception->getMessage(),
                ];
            }
        }

        if ($this->database) {
            try {
                $this->database->getReference('.info/connected');
                $rtdbOk = true;
            } catch (\Throwable) {
                $rtdbOk = false;
            }
        }

        return [
            'firestore' => $firestoreOk,
            'realtime_database' => $rtdbOk,
            'grpc_available' => $this->healthService->grpcAvailable(),
            'message' => 'Connectivity probe completed',
        ];
    }

    /**
     * Health check - delegates to FirebaseSyncService
     * @deprecated Use FirebaseSyncService::healthCheck()
     */
    public function healthCheck(): array
    {
        return $this->firebaseSyncService->healthCheck();
    }
}
