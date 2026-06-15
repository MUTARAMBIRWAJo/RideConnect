<?php

namespace App\Jobs;

use App\Services\Firebase\RealtimeDatabaseManager;
use App\Traits\IdempotentJob;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class UpdateDriverLocationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, IdempotentJob;

    public int $tries = 3;
    public int $backoff = 3;

    public function __construct(
        public readonly int $driverId,
        public readonly float $latitude,
        public readonly float $longitude,
        public readonly ?float $speedKmh = null,
        public readonly ?float $heading = null,
        public readonly ?float $accuracy = null,
        public readonly ?int $tripId = null,
        public readonly ?float $batteryLevel = null
    ) {}

    /**
     * Execute the job.
     */
    public function handle(RealtimeDatabaseManager $rtdbManager): void
    {
        if (!$this->startProcessing()) {
            Log::debug("[UpdateDriverLocationJob] Duplicate location event skipped for driver {$this->driverId}");
            return;
        }

        $payload = [
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'speed' => $this->speedKmh,
            'heading' => $this->heading,
            'accuracy' => $this->accuracy,
            'battery_level' => $this->batteryLevel,
            'updated_at' => now()->toIso8601String(),
        ];

        try {
            // Write to RTDB driver_locations/
            $rtdbManager->set("driver_locations/{$this->driverId}", $payload);

            // Write to RTDB trip_locations/ if driver is on a trip
            if ($this->tripId) {
                $rtdbManager->set("trip_locations/{$this->tripId}", $payload);
            }
        } catch (\Throwable $e) {
            Log::error("[UpdateDriverLocationJob] Failed to sync telemetry for driver {$this->driverId}", [
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }
}
