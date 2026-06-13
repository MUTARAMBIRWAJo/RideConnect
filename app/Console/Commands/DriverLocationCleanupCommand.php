<?php

namespace App\Console\Commands;

use App\Jobs\DriverLocationCleanupJob;
use Illuminate\Console\Command;

class DriverLocationCleanupCommand extends Command
{
    protected $signature = 'driver:location-cleanup {--stale-minutes=5 : Minutes threshold for marking drivers offline}';
    
    protected $description = 'Clean up stale driver locations and mark inactive drivers offline';

    public function handle(): int
    {
        $staleMinutes = (int) $this->option('stale-minutes');

        $this->info("Dispatching driver location cleanup job (stale threshold: {$staleMinutes} minutes)...");

        try {
            dispatch(new DriverLocationCleanupJob($staleMinutes));
            
            $this->info('Job dispatched successfully.');
            return Command::SUCCESS;
        } catch (\Throwable $e) {
            $this->error("Failed to dispatch cleanup job: {$e->getMessage()}");
            return Command::FAILURE;
        }
    }
}
