<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class TestPredict extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:test-predict';

    protected $description = 'Test ML prediction service integration';

    public function handle()
    {
        $service = app(\App\Services\DemandPredictionService::class);
        $points = $service->predict();
        $this->info("Predictions retrieved:");
        foreach ($points as $point) {
            $this->line("- Lat: {$point['lat']}, Lng: {$point['lng']}, Intensity: {$point['intensity']}");
        }

        $dbCount = \App\Models\DemandPrediction::count();
        $this->info("Total predictions in DB: {$dbCount}");
    }
}
