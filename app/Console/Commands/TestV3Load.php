<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Models\V3\TripV3;
use App\Services\V3\TripLifecycleEngineV3;
use App\Services\V3\TripMatchingEngineV3;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TestV3Load extends Command
{
    protected $signature = 'test:load-v3 {--count=1000}';
    protected $description = 'Simulate load testing for Trip System V3';

    public function handle(TripMatchingEngineV3 $matchingEngine)
    {
        $count = (int) $this->option('count');
        $this->info("Simulating {$count} requests for V3 Trip System...");

        $user = User::first() ?? User::factory()->create();

        $startTime = microtime(true);
        $bar = $this->output->createProgressBar($count);

        for ($i = 0; $i < $count; $i++) {
            $type = ['motor_vehicle', 'private_car', 'public_bus'][array_rand(['motor_vehicle', 'private_car', 'public_bus'])];
            
            DB::transaction(function () use ($user, $type, $matchingEngine) {
                $trip = new TripV3([
                    'user_id' => $user->id,
                    'transport_type' => $type,
                    'pickup_location' => 'Point A ' . Str::random(4),
                    'dropoff_location' => 'Point B ' . Str::random(4),
                    'metadata' => [
                        'simulated' => true,
                    ]
                ]);
                $trip->save();

                // Start matching
                $matchingEngine->startMatching($trip);
            });

            $bar->advance();
        }

        $bar->finish();
        $this->newLine();

        $endTime = microtime(true);
        $duration = number_format($endTime - $startTime, 2);

        $this->info("Successfully processed {$count} requests in {$duration} seconds.");
        return Command::SUCCESS;
    }
}
