<?php

namespace App\Jobs;

use App\Services\Firebase\FirestoreManager;
use App\Services\Firebase\RealtimeDatabaseManager;
use App\Traits\IdempotentJob;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class UpdateDriverPresenceJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, IdempotentJob;

    public int $tries = 5;
    public int $backoff = 5;

    public function __construct(
        public readonly int $driverId,
        public readonly string $status // online_available, online_busy, on_trip, offline, break
    ) {}

    /**
     * Execute the job.
     */
    public function handle(
        FirestoreManager $firestoreManager,
        RealtimeDatabaseManager $rtdbManager
    ): void {
        if (!$this->startProcessing()) {
            Log::info("[UpdateDriverPresenceJob] Duplicate presence event skipped for driver {$this->driverId}");
            return;
        }

        $payload = [
            'driver_id' => $this->driverId,
            'status' => $this->status,
            'updated_at' => now()->toIso8601String(),
        ];

        try {
            // Write to Firestore collection: driver_presence
            $firestoreManager->set('driver_presence', (string) $this->driverId, $payload);

            // Write to RTDB path: drivers_online (remove if offline)
            if ($this->status === 'offline') {
                $rtdbManager->delete("drivers_online/{$this->driverId}");
            } else {
                $rtdbManager->set("drivers_online/{$this->driverId}", $payload);
            }
        } catch (\Throwable $e) {
            Log::error("[UpdateDriverPresenceJob] Failed to sync presence for driver {$this->driverId}", [
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }
}
