<?php

namespace App\Jobs;

use App\Services\Firebase\FirebaseSyncService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class DriverLocationSyncJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 30;
    public int $backoff = 5; // seconds between retries

    public function __construct(
        public readonly int $driverId,
        public readonly float $latitude,
        public readonly float $longitude,
        public readonly ?float $accuracy = null,
        public readonly ?int $tripId = null,
    ) {}

    public function handle(FirebaseSyncService $firebaseSyncService): void
    {
        $jobId = $this->job?->getJobId() ?? 'unknown';
        
        Log::info('[DriverLocationSyncJob] Starting', [
            'job_id' => $jobId,
            'driver_id' => $this->driverId,
            'trip_id' => $this->tripId,
            'attempt' => $this->attempts(),
        ]);

        try {
            if (!$firebaseSyncService->isEnabled()) {
                Log::warning('[DriverLocationSyncJob] Firebase sync disabled, skipping driver location sync', [
                    'job_id' => $jobId,
                    'driver_id' => $this->driverId,
                ]);
                return;
            }

            $synced = $firebaseSyncService->syncDriverLocation(
                $this->driverId,
                $this->latitude,
                $this->longitude,
                $this->accuracy ?? 0,
                $this->tripId
            );

            if ($synced) {
                Log::info('[DriverLocationSyncJob] Driver location synced successfully', [
                    'job_id' => $jobId,
                    'driver_id' => $this->driverId,
                    'trip_id' => $this->tripId,
                    'latitude' => $this->latitude,
                    'longitude' => $this->longitude,
                ]);
            } else {
                Log::warning('[DriverLocationSyncJob] Driver location sync failed', [
                    'job_id' => $jobId,
                    'driver_id' => $this->driverId,
                    'trip_id' => $this->tripId,
                ]);
            }
        } catch (\Throwable $e) {
            Log::error('[DriverLocationSyncJob] Failed to sync driver location', [
                'job_id' => $jobId,
                'driver_id' => $this->driverId,
                'trip_id' => $this->tripId,
                'attempt' => $this->attempts(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Dead-letter logging after max retries
            if ($this->attempts() >= $this->tries) {
                Log::critical('[DriverLocationSyncJob] Dead-letter - max retries exceeded', [
                    'job_id' => $jobId,
                    'driver_id' => $this->driverId,
                    'trip_id' => $this->tripId,
                    'latitude' => $this->latitude,
                    'longitude' => $this->longitude,
                    'error' => $e->getMessage(),
                ]);
            }

            // Retry with exponential backoff
            throw $e;
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::critical('[DriverLocationSyncJob] Job failed permanently', [
            'driver_id' => $this->driverId,
            'trip_id' => $this->tripId,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'error' => $exception->getMessage(),
            'attempts' => $this->attempts(),
        ]);
    }
}
