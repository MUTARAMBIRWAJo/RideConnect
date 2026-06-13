<?php

namespace App\Jobs;

use App\Models\DriverLocation;
use App\Services\FirebaseSync;
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

    public function __construct(
        public readonly int $driverId,
        public readonly float $latitude,
        public readonly float $longitude,
        public readonly ?float $accuracy = null,
        public readonly ?int $tripId = null,
    ) {}

    public function handle(FirebaseSync $firebaseSync): void
    {
        try {
            if (!$firebaseSync->isEnabled()) {
                Log::debug('Firebase sync disabled, skipping driver location sync');
                return;
            }

            $synced = $firebaseSync->syncDriverLocation(
                $this->driverId,
                $this->latitude,
                $this->longitude,
                $this->accuracy ?? 0
            );

            if ($synced) {
                Log::debug('Driver location synced to Firebase', [
                    'driver_id' => $this->driverId,
                    'trip_id' => $this->tripId,
                ]);
            }
        } catch (\Throwable $e) {
            Log::error('Failed to sync driver location to Firebase', [
                'driver_id' => $this->driverId,
                'error' => $e->getMessage(),
            ]);
            
            // Retry with exponential backoff
            $this->release($this->attempts() * 10);
        }
    }
}
