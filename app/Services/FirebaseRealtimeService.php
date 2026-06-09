<?php

namespace App\Services;

use Kreait\Firebase\Factory;
use Illuminate\Support\Facades\Log;

/**
 * Firestore event-delivery layer.
 *
 * Contracts:
 *   POST Laravel     → writes Firestore doc → Flutter listens on a snapshot listener
 *   POST Laravel     → pushTripEvent()
 *
 * Public API — read-only for the rest of the backend. Never throws on failure.
 */
class FirebaseRealtimeService
{
    private ?\Kreait\Firebase\Database $database = null;
    private ?\Kreait\Firebase\Firestore $firestore = null;
    private bool $enabled = false;

    public function __construct()
    {
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
            Log::info('FirebaseRealtimeService: initialised', [
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
     * Collection: trip_events
     * Document ID: auto
     * Fields: event, trip_id, payload, timestamp
     */
    public function pushTripEvent(string $tripId, string $event, array $payload = []): void
    {
        if (! $this->enabled || ! $this->firestore) {
            return;
        }

        try {
            $this->firestore->collection('trip_events')->add([
                'trip_id'    => (int) $tripId,
                'event'      => $event,
                'payload'    => $payload,
                'timestamp'  => now()->toIso8601String(),
            ]);
        } catch (\Throwable $e) {
            Log::debug('Firestore pushTripEvent failed (non-critical)', [
                'trip_id' => $tripId, 'event' => $event, 'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Push an in-app notification into Firestore (for Flutter badge count).
     */
    public function pushNotification(string $userId, string $type, string $title, string $body, array $data = []): void
    {
        if (! $this->enabled || ! $this->firestore) {
            return;
        }

        try {
            $this->firestore->collection('notifications')->add([
                'user_id'  => (int) $userId,
                'type'     => $type,
                'title'    => $title,
                'body'     => $body,
                'data'     => $data,
                'read'     => false,
                'timestamp'=> now()->toIso8601String(),
            ]);
        } catch (\Throwable $e) {
            Log::debug('Firestore pushNotification failed', [
                'user_id' => $userId, 'error' => $e->getMessage(),
            ]);
        }
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }
}
