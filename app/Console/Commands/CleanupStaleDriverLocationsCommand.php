<?php

namespace App\Console\Commands;

use App\Jobs\CleanupStaleDriverLocations;
use App\Services\Location\DriverLocationService;
use Illuminate\Console\Command;

class CleanupStaleDriverLocationsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'drivers:cleanup-locations {--force : Run immediately without queue}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clean up stale driver location data and mark inactive drivers as offline';

    /**
     * Execute the console command.
     */
    public function handle(DriverLocationService $locationService)
    {
        $this->info('Starting cleanup of stale driver locations...');

        if ($this->option('force')) {
            // Run synchronously
            $job = new CleanupStaleDriverLocations();
            $job->handle($locationService);

            $this->info('Cleanup completed synchronously.');
        } else {
            // Dispatch to queue
            CleanupStaleDriverLocations::dispatch();
            $this->info('Cleanup job dispatched to queue.');
        }

        $onlineCount = $locationService->getOnlineDriversCount();
        $this->info("Currently {$onlineCount} drivers marked as online.");
    }
}
