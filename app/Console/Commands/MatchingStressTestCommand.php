<?php

namespace App\Console\Commands;

use App\Models\Driver;
use App\Models\User;
use App\Models\V3\TripV3;
use App\Jobs\V3\HandleDriverTimeoutV3;
use App\Services\Matching\DriverEligibilityAuditor;
use App\Services\V3\TripLifecycleEngineV3;
use App\Services\V3\TripMatchingEngineV3;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class MatchingStressTestCommand extends Command
{
    protected $signature = 'matching:test
        {--count=100 : Number of simulated passenger requests}
        {--transport=private_car : private_car, motor_vehicle, or public_bus}
        {--radius=5 : Eligibility radius in kilometers}
        {--persist : Keep simulated trips after the report}';

    protected $description = 'Create simulated requests at recommended pickup locations and run the real V3 matching engine.';

    public function handle(
        DriverEligibilityAuditor $auditor,
        TripLifecycleEngineV3 $lifecycle,
        TripMatchingEngineV3 $matchingEngine,
    ): int {
        $count = max(1, (int) $this->option('count'));
        $transport = (string) $this->option('transport');
        $radius = (float) $this->option('radius');
        $persist = (bool) $this->option('persist');
        $locations = DriverEligibilityAuditor::PICKUP_LOCATIONS;

        Bus::fake([HandleDriverTimeoutV3::class]);

        $passenger = $this->simulatedPassenger();
        $createdTripIds = [];
        $successes = 0;
        $fallbackMatches = 0;
        $mlMatches = 0;
        $noDriver = 0;
        $latencies = [];
        $matchedDriverIds = [];

        $availableBefore = Driver::query()
            ->with(['user', 'vehicles'])
            ->get()
            ->filter(fn (Driver $driver): bool => $auditor->evaluate($driver, $transport, null, null, $radius, true)['eligible'])
            ->count();

        $bar = $this->output->createProgressBar($count);

        for ($i = 0; $i < $count; $i++) {
            $pickup = $locations[array_rand($locations)];
            $dropoff = $locations[array_rand($locations)];
            if ($dropoff['name'] === $pickup['name']) {
                $dropoff = $locations[($i + 7) % count($locations)];
            }

            $trip = TripV3::query()->create([
                'user_id' => $passenger->id,
                'transport_type' => $transport,
                'status' => 'REQUESTED',
                'pickup_location' => $pickup['name'],
                'dropoff_location' => $dropoff['name'],
                'pickup_lat' => $pickup['lat'],
                'pickup_lng' => $pickup['lng'],
                'dropoff_lat' => $dropoff['lat'],
                'dropoff_lng' => $dropoff['lng'],
                'fare_estimate' => 4500,
                'metadata' => [
                    'simulated' => true,
                    'source' => 'matching:test',
                ],
            ]);
            $createdTripIds[] = $trip->id;

            $started = microtime(true);
            try {
                $trip->matching_started_at = now();
                $trip->save();
                $lifecycle->transition($trip, 'MATCHING');
                $matchingEngine->executeMatch($trip->fresh());
            } catch (\Throwable $exception) {
                $trip->metadata = array_merge($trip->metadata ?? [], [
                    'matching_test_error' => $exception->getMessage(),
                ]);
                $trip->save();
            }

            $latencies[] = microtime(true) - $started;
            $fresh = $trip->fresh();

            if ($fresh?->matched_driver_id) {
                $successes++;
                $matchedDriverIds[(int) $fresh->matched_driver_id] = true;
                if ((bool) $fresh->fallback_match_used) {
                    $fallbackMatches++;
                } else {
                    $mlMatches++;
                }
            } else {
                $noDriver++;
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        if (! $persist && $createdTripIds !== []) {
            DB::table('trips_v3')->whereIn('id', $createdTripIds)->delete();
        }

        $avg = count($latencies) > 0 ? array_sum($latencies) / count($latencies) : 0.0;
        $successRate = round(($successes / $count) * 100, 2);
        $rejected = Driver::query()
            ->with(['user', 'vehicles'])
            ->get()
            ->filter(fn (Driver $driver): bool => ! $auditor->evaluate($driver, $transport, null, null, $radius, true)['eligible'])
            ->count();

        $this->info('Matching Stress Test Report');
        $this->table(
            ['Metric', 'Value'],
            [
                ['Matching Success Rate', $successRate.'%'],
                ['Average Match Time', round($avg, 4).' seconds'],
                ['Drivers Available', $availableBefore],
                ['Drivers Matched', count($matchedDriverIds)],
                ['Drivers Rejected', $rejected],
                ['Rejected Matches / No Driver Situations', $noDriver],
                ['ML/strict local stage match count', $mlMatches],
                ['Fallback match count', $fallbackMatches],
                ['Simulated trips persisted', $persist ? 'YES' : 'NO'],
            ]
        );

        return self::SUCCESS;
    }

    private function simulatedPassenger(): User
    {
        return User::query()->updateOrCreate(
            ['email' => 'matching.passenger@rideconnect.local'],
            [
                'name' => 'Matching Test Passenger',
                'phone' => '0788999000',
                'role' => 'PASSENGER',
                'password' => bcrypt('password123'),
                'is_approved' => true,
                'is_verified' => true,
                'email_verified_at' => now(),
                'last_seen_at' => now(),
                'is_online' => true,
                'current_device_id' => 'matching-test-'.Str::lower(Str::random(8)),
            ]
        );
    }
}
