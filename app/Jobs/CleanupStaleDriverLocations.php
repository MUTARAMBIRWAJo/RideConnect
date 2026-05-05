<?php

namespace App\Jobs;

use App\Services\Location\DriverLocationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class CleanupStaleDriverLocations implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct()
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(DriverLocationService $locationService): void
    {
        Log::info('Starting cleanup of stale driver locations');

        // Mark drivers as offline if they haven't updated recently
        $markedOffline = $locationService->markStaleDriversOffline();

        // Clear old location cache entries
        $this->clearExpiredLocationCache();

        Log::info('Completed cleanup of stale driver locations', [
            'drivers_marked_offline' => $markedOffline,
        ]);
    }

    /**
     * Clear expired location cache entries
     */
    private function clearExpiredLocationCache(): void
    {
        // Cache cleanup is handled automatically by Laravel's cache system
        // This method is here for future enhancements if needed
        Log::debug('Location cache cleanup completed');
    }
}
