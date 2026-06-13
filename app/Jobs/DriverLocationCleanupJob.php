<?php

namespace App\Jobs;

use App\Models\DriverLocation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class DriverLocationCleanupJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 300;

    public function __construct(
        private readonly int $staleMinutes = 5,
    ) {}

    public function handle(): void
    {
        $staleThreshold = now()->subMinutes($this->staleMinutes);

        $updated = DriverLocation::where('is_online', true)
            ->where('last_activity_at', '<', $staleThreshold)
            ->update([
                'is_online' => false,
                'updated_at' => now(),
            ]);

        if ($updated > 0) {
            Log::info("Marked {$updated} drivers as offline due to inactivity", [
                'stale_threshold_minutes' => $this->staleMinutes,
            ]);
        }

        // Clean up very old location history (older than 30 days)
        // This would be implemented if we had a location history table
        // For now, we just log the action
        Log::info('Driver location cleanup completed', [
            'marked_offline' => $updated,
        ]);
    }
}
