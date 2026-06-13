<?php

namespace App\Console\Commands;

use App\Jobs\RetryFailedPaymentEventsJob;
use Illuminate\Console\Command;

class RetryFailedPaymentEventsCommand extends Command
{
    protected $signature = 'payment:retry-events {--limit=100 : Number of events to retry}';
    
    protected $description = 'Retry failed payment events';

    public function handle(): int
    {
        $limit = (int) $this->option('limit');

        $this->info("Dispatching job to retry up to {$limit} failed payment events...");

        try {
            dispatch(new RetryFailedPaymentEventsJob($limit));
            
            $this->info('Job dispatched successfully. Check logs for results.');
            return Command::SUCCESS;
        } catch (\Throwable $e) {
            $this->error("Failed to dispatch retry job: {$e->getMessage()}");
            return Command::FAILURE;
        }
    }
}
