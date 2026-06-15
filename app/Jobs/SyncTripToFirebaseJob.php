<?php

namespace App\Jobs;

use App\Models\Trip;
use App\Services\Sync\TripStateSyncService;
use App\Traits\IdempotentJob;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SyncTripToFirebaseJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, IdempotentJob;

    public int $tries = 5;
    public int $backoff = 5;

    public function __construct(public readonly int $tripId) {}

    /**
     * Execute the job.
     */
    public function handle(TripStateSyncService $syncService): void
    {
        if (!$this->startProcessing()) {
            Log::info("[SyncTripToFirebaseJob] Duplicate job skipped for trip {$this->tripId}");
            return;
        }

        $trip = Trip::find($this->tripId);
        if (!$trip) {
            Log::warning("[SyncTripToFirebaseJob] Trip {$this->tripId} not found. Skipped sync.");
            return;
        }

        try {
            $syncService->syncToFirebase($trip);
        } catch (\Throwable $e) {
            Log::error("[SyncTripToFirebaseJob] Failed sync for trip {$this->tripId}", [
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }
}
