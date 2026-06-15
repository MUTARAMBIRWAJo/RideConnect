<?php

namespace App\Services\Sync;

use App\Models\Trip;
use App\Services\Firebase\FirestoreManager;
use App\Services\Firebase\RealtimeDatabaseManager;
use Illuminate\Support\Facades\Log;

class TripStateSyncService
{
    public function __construct(
        protected readonly FirestoreManager $firestoreManager,
        protected readonly RealtimeDatabaseManager $rtdbManager
    ) {}

    /**
     * Synchronize a trip's Postgres state to both Firestore and Realtime Database.
     * Runs inside PostgresSyncContext to safely allow restricted field writes.
     */
    public function syncToFirebase(object $trip): void
    {
        $payload = [
            'trip_id' => $trip->id,
            'passenger_id' => $trip->passenger_id,
            'driver_id' => $trip->driver_id,
            'status' => $trip->status,
            'pickup' => [
                'latitude' => (float) $trip->pickup_lat,
                'longitude' => (float) $trip->pickup_lng,
                'address' => $trip->pickup_location,
            ],
            'dropoff' => [
                'latitude' => (float) $trip->dropoff_lat,
                'longitude' => (float) $trip->dropoff_lng,
                'address' => $trip->dropoff_location,
            ],
            'estimated_fare' => (float) ($trip->estimated_fare ?? $trip->fare ?? 0),
            'actual_fare' => $trip->actual_fare ? (float) $trip->actual_fare : null,
            'updated_at' => $trip->updated_at?->toIso8601String() ?? now()->toIso8601String(),
            'version' => $trip->updated_at?->getTimestamp() ?? now()->getTimestamp(),
        ];

        try {
            PostgresSyncContext::run(function () use ($trip, $payload) {
                // Sync to Realtime Database active_trips node
                $this->rtdbManager->set("active_trips/{$trip->id}", $payload);

                // Sync to Realtime Database live_tracking node if driver is present
                if ($trip->driver_id) {
                    $this->rtdbManager->set("live_tracking/{$trip->driver_id}", $payload);
                }

                // Sync to Firestore active_trips collection
                $this->firestoreManager->set("active_trips", (string) $trip->id, $payload);
            });
        } catch (\Throwable $e) {
            Log::error("[TripStateSyncService] Failed to sync trip {$trip->id} to Firebase", [
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Reconcile conflict between Firebase state and Postgres.
     * Enforces the rule: PostgreSQL ALWAYS wins.
     */
    public function syncToPostgres(int $tripId, array $firebaseData): void
    {
        $trip = Trip::find($tripId);
        if (!$trip) {
            Log::warning("[TripStateSyncService] Attempted to sync non-existent trip: {$tripId}");
            return;
        }

        $postgresTimestamp = $trip->updated_at?->getTimestamp() ?? 0;
        $firebaseTimestamp = $firebaseData['version'] ?? 0;

        // If Postgres is newer or equal, or if any conflict arises, Postgres wins.
        // We override Firebase with Postgres' authoritative data.
        if ($postgresTimestamp >= $firebaseTimestamp) {
            Log::info("[TripStateSyncService] Conflict detected. Postgres is newer than Firebase for trip {$tripId}. Overwriting Firebase.");
        } else {
            Log::info("[TripStateSyncService] Conflict detected. Firebase timestamp newer, but Postgres is absolute source of truth. Overwriting Firebase.");
        }

        $this->syncToFirebase($trip);
    }

    /**
     * Run conflict resolution on all active trips.
     */
    public function resolveConflicts(): void
    {
        $activeStatuses = ['requested', 'matched', 'in_progress', 'REQUESTED', 'MATCHING', 'DRIVER_FOUND', 'ASSIGNED', 'ACCEPTED', 'ARRIVED', 'STARTED'];
        
        Trip::whereIn('status', $activeStatuses)->chunk(100, function ($trips) {
            foreach ($trips as $trip) {
                $this->syncToFirebase($trip);
            }
        });
    }
}
