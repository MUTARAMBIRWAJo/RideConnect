<?php

namespace App\Services\Firebase;

use App\Traits\EnforcesSourceOfTruth;
use Illuminate\Support\Facades\Log;

class FirestoreManager
{
    use EnforcesSourceOfTruth;

    public function __construct(protected readonly FirebaseManager $firebaseManager) {}

    /**
     * Set/Merge document data in Firestore.
     * Guards restricted fields through EnforcesSourceOfTruth.
     */
    public function set(string $collection, string $document, array $data, bool $merge = true): void
    {
        $domain = $this->getDomainFromCollection($collection);
        $this->enforceSourceOfTruth($domain, $data);

        $firestore = $this->firebaseManager->firestore();
        if ($firestore === null) {
            Log::warning("[FirestoreManager] Firestore client unavailable. Skipped write on {$collection}/{$document}");
            return;
        }

        try {
            $firestore->database()
                ->collection($collection)
                ->document($document)
                ->set($data, ['merge' => $merge]);
        } catch (\Throwable $e) {
            Log::error("[FirestoreManager] Failed to set document {$collection}/{$document}", [
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Delete document from Firestore.
     */
    public function delete(string $collection, string $document): void
    {
        $firestore = $this->firebaseManager->firestore();
        if ($firestore === null) {
            Log::warning("[FirestoreManager] Firestore client unavailable. Skipped delete on {$collection}/{$document}");
            return;
        }

        try {
            $firestore->database()
                ->collection($collection)
                ->document($document)
                ->delete();
        } catch (\Throwable $e) {
            Log::error("[FirestoreManager] Failed to delete document {$collection}/{$document}", [
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Get document snapshot from Firestore.
     */
    public function get(string $collection, string $document): ?array
    {
        $firestore = $this->firebaseManager->firestore();
        if ($firestore === null) {
            return null;
        }

        try {
            $snapshot = $firestore->database()
                ->collection($collection)
                ->document($document)
                ->snapshot();
            return $snapshot->exists() ? $snapshot->data() : null;
        } catch (\Throwable $e) {
            Log::error("[FirestoreManager] Failed to get document {$collection}/{$document}", [
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Map Firestore collection name to Postgres-wins domain config key.
     */
    protected function getDomainFromCollection(string $collection): string
    {
        return match ($collection) {
            'active_trips' => 'trips',
            'users' => 'users',
            'drivers' => 'drivers',
            'payments' => 'payments',
            default => $collection,
        };
    }
}
