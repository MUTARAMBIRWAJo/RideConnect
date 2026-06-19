<?php

namespace Tests\Feature\V3;

use App\Models\Driver;
use App\Models\DriverLocation;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\V3\TripV3;
use App\Services\V3\TripMatchingEngineV3;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TripV3MatchingFallbackTest extends TestCase
{
    use RefreshDatabase;

    private function createDriver(string $name, float $lat, float $lng): Driver
    {
        $user = User::factory()->create([
            'name' => $name,
            'role' => 'DRIVER',
            'is_approved' => true,
        ]);

        $driver = Driver::factory()->create([
            'user_id' => $user->id,
            'status' => 'approved',
            'availability_status' => 'available',
            'is_available' => true,
            'is_online' => true,
            'last_seen_at' => now(),
            'current_latitude' => $lat,
            'current_longitude' => $lng,
        ]);

        Vehicle::factory()->create([
            'driver_id' => $driver->id,
            'vehicle_type' => 'motorcycle',
            'is_active' => true,
        ]);

        DriverLocation::query()->create([
            'driver_id' => $driver->id,
            'latitude' => $lat,
            'longitude' => $lng,
            'lat' => $lat,
            'lng' => $lng,
            'is_online' => true,
            'last_activity_at' => now(),
        ]);

        return $driver;
    }

    public function test_v3_deterministic_matching_assigns_nearest_online_driver_within_5km(): void
    {
        // 1. Create a passenger and an online motorcycle driver within 5km (e.g. 1km away)
        $passenger = User::factory()->create(['role' => 'PASSENGER', 'is_approved' => true]);
        
        // Target driver: 1km away (approx -1.944, 30.061 vs passenger -1.944, 30.071)
        $driver = $this->createDriver('Fallback Boda', -1.9440, 30.0610);

        // 2. Create the V3 trip request
        $trip = TripV3::query()->create([
            'user_id' => $passenger->id,
            'transport_type' => 'motor_vehicle',
            'pickup_location' => 'Kigali Heights',
            'pickup_lat' => -1.9440,
            'pickup_lng' => 30.0710,
            'dropoff_location' => 'Kigali Convention Centre',
            'dropoff_lat' => -1.9536,
            'dropoff_lng' => 30.0928,
            'matching_started_at' => now(),
            'status' => 'MATCHING',
        ]);

        // 3. Fake queue to prevent background/delayed jobs from executing synchronously
        \Illuminate\Support\Facades\Queue::fake();

        $engine = app(TripMatchingEngineV3::class);
        $engine->executeMatch($trip);

        // 4. Assert that the nearest driver has been matched successfully
        $trip->refresh();
        $this->assertEquals($driver->id, $trip->matched_driver_id);
        $this->assertEquals('pending', $trip->driver_response_status);

        // 5. Assert that a DriverTripOffer was created for this driver
        $this->assertDatabaseHas('driver_trip_offers', [
            'trip_id' => $trip->id,
            'driver_id' => $driver->id,
            'status' => 'pending',
        ]);
    }
}
