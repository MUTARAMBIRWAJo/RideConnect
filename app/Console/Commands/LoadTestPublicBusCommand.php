<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Trip;
use App\Models\User;
use App\Models\Driver;
use Illuminate\Support\Facades\DB;
use App\Services\PublicBusTripService;
use Illuminate\Support\Facades\Log;
use Throwable;

class LoadTestPublicBusCommand extends Command
{
    protected $signature = 'test:load-public-bus {--passengers=500}';
    protected $description = 'Simulate high-concurrency requests for a single bus to verify capacity locking.';

    public function handle(PublicBusTripService $service)
    {
        $numPassengers = (int) $this->option('passengers');
        
        $this->info("Setting up load test for {$numPassengers} passengers...");
        
        $driverUser = User::factory()->create(['role' => 'DRIVER']);
        $driver = Driver::factory()->create([
            'user_id' => $driverUser->id,
            'status' => 'approved',
            'is_available' => true,
        ]);

        $this->info("Bus driver created ID: {$driver->id}. Beginning concurrent simulations...");
        
        $successCount = 0;
        $failCount = 0;

        // In a real load test we would fork processes or use HTTP requests via Artillery/JMeter.
        // For local verification of DB locking, we can simulate rapid sequential requests.
        // But to truly test locks, we need concurrent processes. 
        // A simple way in PHP CLI without pthreads is fast iteration relying on DB constraints, 
        // but since it's synchronous, it will always pass sequentially. 
        // We will output instructions on how to use actual load testing tools.
        
        for ($i = 0; $i < $numPassengers; $i++) {
            try {
                $service->requestTrip([
                    'pickup_location' => 'Load Test Hub',
                    'dropoff_location' => 'Load Test Dest',
                    'pickup_lat' => -1.95,
                    'pickup_lng' => 30.06,
                ], 1); // Mock passenger 1
                $successCount++;
            } catch (Throwable $e) {
                $failCount++;
            }
        }

        $this->info("Completed. Success: {$successCount}, Failed: {$failCount}");
        $this->info("To truly test pessimistic locking, run Apache Bench (ab) or Siege against the endpoint:");
        $this->line("ab -n {$numPassengers} -c 50 -H 'Authorization: Bearer <token>' http://localhost/api/v1/passenger/public-bus/trip-request");
    }
}
