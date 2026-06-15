<?php

namespace App\Services\Firebase;

use App\Traits\EnforcesSourceOfTruth;
use Illuminate\Support\Facades\Log;

class RealtimeDatabaseManager
{
    use EnforcesSourceOfTruth;

    public function __construct(protected readonly FirebaseManager $firebaseManager) {}

    /**
     * Overwrite RTDB node data.
     * Guards restricted fields through EnforcesSourceOfTruth.
     */
    public function set(string $reference, array $data): void
    {
        $domain = $this->getDomainFromReference($reference);
        $this->enforceSourceOfTruth($domain, $data);

        $database = $this->firebaseManager->database();
        if ($database === null) {
            Log::warning("[RealtimeDatabaseManager] RTDB client unavailable. Skipped write on {$reference}");
            return;
        }

        try {
            $database->getReference($reference)->set($data);
        } catch (\Throwable $e) {
            Log::error("[RealtimeDatabaseManager] Failed to set reference {$reference}", [
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Perform structural updates on RTDB node keys.
     * Guards restricted fields through EnforcesSourceOfTruth.
     */
    public function update(string $reference, array $data): void
    {
        $domain = $this->getDomainFromReference($reference);
        $this->enforceSourceOfTruth($domain, $data);

        $database = $this->firebaseManager->database();
        if ($database === null) {
            Log::warning("[RealtimeDatabaseManager] RTDB client unavailable. Skipped update on {$reference}");
            return;
        }

        try {
            $database->getReference($reference)->update($data);
        } catch (\Throwable $e) {
            Log::error("[RealtimeDatabaseManager] Failed to update reference {$reference}", [
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Remove RTDB node.
     */
    public function delete(string $reference): void
    {
        $database = $this->firebaseManager->database();
        if ($database === null) {
            Log::warning("[RealtimeDatabaseManager] RTDB client unavailable. Skipped delete on {$reference}");
            return;
        }

        try {
            $database->getReference($reference)->remove();
        } catch (\Throwable $e) {
            Log::error("[RealtimeDatabaseManager] Failed to delete reference {$reference}", [
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Get value of RTDB reference.
     */
    public function get(string $reference): mixed
    {
        $database = $this->firebaseManager->database();
        if ($database === null) {
            return null;
        }

        try {
            return $database->getReference($reference)->getValue();
        } catch (\Throwable $e) {
            Log::error("[RealtimeDatabaseManager] Failed to get reference {$reference}", [
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Map reference paths to the Postgres-wins domain config key.
     */
    protected function getDomainFromReference(string $reference): string
    {
        $parts = explode('/', trim($reference, '/'));
        $root = $parts[0] ?? '';
        return match ($root) {
            'active_trips' => 'trips',
            'drivers_online', 'driver_locations' => 'drivers',
            'payments' => 'payments',
            default => $root,
        };
    }
}
