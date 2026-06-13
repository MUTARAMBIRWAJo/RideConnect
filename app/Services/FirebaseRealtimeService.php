<?php

namespace App\Services;

use Kreait\Firebase\Factory;
use Illuminate\Support\Facades\Log;
use App\Services\Firebase\FirebaseSyncService;

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

    public function __construct(
        private readonly FirebaseSyncService $firebaseSyncService,
    ) {
        Log::warning('[FirebaseRealtimeService] DEPRECATED - Use FirebaseSyncService for writes');

        $projectId     = config('services.firebase.project_id');
        $credentials   = config('services.firebase.credentials');
        $databaseUrl   = config('services.firebase.database_url');   // Realtime DB only
        $firestoreDb   = config('services.firebase.firestore_database'); // optional DB ID

        if (! $projectId || ! $credentials || ! file_exists($credentials)) {
            Log::debug('FirebaseRealtimeService: disabled — no project/credentials configured');
            return;
        }

        try {
            $factory = (new Factory)->withServiceAccount($credentials)->withProjectId($projectId);

            if ($firestoreDb) {
                $this->firestore = $factory->createFirestore()->database($firestoreDb);
            } else {
                $this->firestore = $factory->createFirestore()->database();
            }

            // Realtime DB (used by the legacy /realtime/config endpoint)
            if ($databaseUrl) {
                $this->database = $factory->createDatabase()->withUri($databaseUrl);
            }

            $this->enabled = true;
            Log::info('FirebaseRealtimeService: initialised (read-only mode)', [
                'project_id' => $projectId,
                'firestore_db' => $firestoreDb ?? '(default)',
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

        // Notifications are handled by FirebaseSyncService internally via sendNotification()
        // This method is kept for backward compatibility only
        // No-op - notifications are now sent automatically by FirebaseSyncService event handlers
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    /**
     * @return array<string, mixed>
     */
    public function connectivityStatus(): array
    {
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
