<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class TestJob extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:test-job';
    protected $description = 'Command description';

    public function handle()
    {
        $job = new \App\Jobs\PollDemandPredictionsJob();
        $job->handle();
        $this->info("Job executed!");
    }
}
