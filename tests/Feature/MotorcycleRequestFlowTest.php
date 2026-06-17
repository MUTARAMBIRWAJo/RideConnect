<?php

namespace Tests\Feature;

use App\Models\Driver;
use App\Models\DriverLocation;
use App\Models\MotorcycleTrip;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\GoogleRouteService;
use App\Services\MatchingService;
use App\Services\MotorcycleTripService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MotorcycleRequestFlowTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Mock GoogleRouteService to avoid external API calls
        $this->app->instance(GoogleRouteService::class, new class extends GoogleRouteService
        {
            public function computeRoute(array $origin, array $destination): array
            {
                return [
                    'success' => true,
                    'distance_meters' => 2000,
                    'duration' => '300s',
                    'distance_km' => 2.0,
                    'polyline' => null,
                    'error' => null,
                ];
            }
        });
    }

    private function createDriver(string $name, float $lat, float $lng, string $status = 'approved', string $availStatus = 'online'): Driver
    {
        $user = User::factory()->create([
            'name' => $name,
            'role' => 'DRIVER',
            'is_approved' => true,
        ]);

        $driver = Driver::factory()->create([
            'user_id' => $user->id,
            'status' => $status,
            'availability_status' => $availStatus,
            'is_available' => true,
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
            'is_online' => true,
            'last_activity_at' => now(),
        ]);

        return $driver;
    }

    /**
     * Scenario 1: Single passenger + single driver.
     */
    public function test_scenario_1_single_passenger_single_driver(): void
    {
        Http::fake([
            '*/rank-drivers' => Http::response([
                'ranked_drivers' => [
                    ['driver_id' => 1, 'score' => 95.0]
                ],
                'model_version' => 'test-model-v1',
                'latency_ms' => 10,
            ], 200),
            '*/match' => Http::response([
                'selected_driver_id' => 1,
                'score' => 95.0,
                'reason' => 'ML matching success',
            ], 200),
        ]);

        $driver = $this->createDriver('Driver One', -1.9441, 30.0619);
        // Overwrite id to 1 for mock response alignment if needed, or get id dynamically
        Http::fake([
            '*/rank-drivers' => Http::response([
                'ranked_drivers' => [
                    ['driver_id' => $driver->id, 'score' => 95.0]
                ],
                'model_version' => 'test-model-v1',
                'latency_ms' => 10,
            ], 200),
            '*/match' => Http::response([
                'selected_driver_id' => $driver->id,
                'score' => 95.0,
                'reason' => 'ML matching success',
            ], 200),
        ]);

        $passenger = User::factory()->create(['role' => 'PASSENGER', 'is_approved' => true]);

        $response = $this->actingAs($passenger, 'sanctum')
            ->postJson('/api/v1/passenger/motor-vehicle/trip-requests', [
                'pickup_location' => 'Kigali Heights',
                'pickup_lat' => -1.9440,
                'pickup_lng' => 30.0618,
                'dropoff_location' => 'Kigali Convention Centre',
                'dropoff_lat' => -1.9536,
                'dropoff_lng' => 30.0928,
            ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('motorcycle_trips', [
            'passenger_id' => $passenger->id,
            'driver_id' => $driver->id,
            'status' => 'ASSIGNED',
        ]);
    }

    /**
     * Scenario 2: Single passenger + multiple drivers.
     */
    public function test_scenario_2_single_passenger_multiple_drivers(): void
    {
        $driver1 = $this->createDriver('Driver Near', -1.9442, 30.0620);
        $driver2 = $this->createDriver('Driver Far', -1.9600, 30.0700);

        // Mock ML service ranking driver 1 higher
        Http::fake([
            '*/match' => Http::response([
                'selected_driver_id' => $driver1->id,
                'score' => 98.5,
                'reason' => 'ML picked Driver Near',
            ], 200),
        ]);

        $passenger = User::factory()->create(['role' => 'PASSENGER', 'is_approved' => true]);

        $response = $this->actingAs($passenger, 'sanctum')
            ->postJson('/api/v1/passenger/motor-vehicle/trip-requests', [
                'pickup_location' => 'Kigali Heights',
                'pickup_lat' => -1.9440,
                'pickup_lng' => 30.0618,
                'dropoff_location' => 'Kigali Convention Centre',
                'dropoff_lat' => -1.9536,
                'dropoff_lng' => 30.0928,
            ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('motorcycle_trips', [
            'passenger_id' => $passenger->id,
            'driver_id' => $driver1->id,
            'status' => 'ASSIGNED',
        ]);
    }

    /**
     * Scenario 3: Multiple passengers + multiple drivers.
     */
    public function test_scenario_3_multiple_passengers_multiple_drivers(): void
    {
        $driver1 = $this->createDriver('Driver Alpha', -1.9441, 30.0619);
        $driver2 = $this->createDriver('Driver Beta', -1.9535, 30.0927);

        $passenger1 = User::factory()->create(['role' => 'PASSENGER', 'is_approved' => true]);
        $passenger2 = User::factory()->create(['role' => 'PASSENGER', 'is_approved' => true]);

        // Mock ML for Passenger 1 -> Driver Alpha
        Http::fake([
            '*/match' => Http::sequence()
                ->push([
                    'selected_driver_id' => $driver1->id,
                    'score' => 92.0,
                ])
                ->push([
                    'selected_driver_id' => $driver2->id,
                    'score' => 94.0,
                ])
        ]);

        // Passenger 1 requests
        $response1 = $this->actingAs($passenger1, 'sanctum')
            ->postJson('/api/v1/passenger/motor-vehicle/trip-requests', [
                'pickup_location' => 'Kigali Heights',
                'pickup_lat' => -1.9440,
                'pickup_lng' => 30.0618,
                'dropoff_location' => 'Kigali Convention Centre',
                'dropoff_lat' => -1.9536,
                'dropoff_lng' => 30.0928,
            ]);
        $response1->assertStatus(201);

        // Passenger 2 requests
        $response2 = $this->actingAs($passenger2, 'sanctum')
            ->postJson('/api/v1/passenger/motor-vehicle/trip-requests', [
                'pickup_location' => 'KCC',
                'pickup_lat' => -1.9536,
                'pickup_lng' => 30.0928,
                'dropoff_location' => 'Kigali Heights',
                'dropoff_lat' => -1.9440,
                'dropoff_lng' => 30.0618,
            ]);
        $response2->assertStatus(201);

        $this->assertDatabaseHas('motorcycle_trips', [
            'passenger_id' => $passenger1->id,
            'driver_id' => $driver1->id,
        ]);

        $this->assertDatabaseHas('motorcycle_trips', [
            'passenger_id' => $passenger2->id,
            'driver_id' => $driver2->id,
        ]);
    }

    /**
     * Scenario 4: No drivers available.
     */
    public function test_scenario_4_no_drivers_available(): void
    {
        $passenger = User::factory()->create(['role' => 'PASSENGER', 'is_approved' => true]);

        // No drivers seeded

        $response = $this->actingAs($passenger, 'sanctum')
            ->postJson('/api/v1/passenger/motor-vehicle/trip-requests', [
                'pickup_location' => 'Kigali Heights',
                'pickup_lat' => -1.9440,
                'pickup_lng' => 30.0618,
                'dropoff_location' => 'Kigali Convention Centre',
                'dropoff_lat' => -1.9536,
                'dropoff_lng' => 30.0928,
            ]);

        // Should return 202 MATCHING_PENDING since retry system is activated
        $response->assertStatus(202);
        $this->assertDatabaseHas('motorcycle_trips', [
            'passenger_id' => $passenger->id,
            'status' => 'MATCHING_PENDING',
            'matching_status' => 'RETRY_SCHEDULED',
        ]);
    }

    /**
     * Scenario 5: ML service unavailable.
     */
    public function test_scenario_5_ml_service_unavailable(): void
    {
        $driver = $this->createDriver('Driver Backup', -1.9441, 30.0619);

        // Mock ML service down (503 Service Unavailable)
        Http::fake([
            '*/match' => Http::response(['error' => 'Service Unavailable'], 503),
        ]);

        $passenger = User::factory()->create(['role' => 'PASSENGER', 'is_approved' => true]);

        $response = $this->actingAs($passenger, 'sanctum')
            ->postJson('/api/v1/passenger/motor-vehicle/trip-requests', [
                'pickup_location' => 'Kigali Heights',
                'pickup_lat' => -1.9440,
                'pickup_lng' => 30.0618,
                'dropoff_location' => 'Kigali Convention Centre',
                'dropoff_lat' => -1.9536,
                'dropoff_lng' => 30.0928,
            ]);

        // Should fallback to local nearest driver match
        $response->assertStatus(201);
        $this->assertDatabaseHas('motorcycle_trips', [
            'passenger_id' => $passenger->id,
            'driver_id' => $driver->id,
            'status' => 'ASSIGNED',
            'matched_via' => 'fast_local',
        ]);
    }

    /**
     * Scenario 6: Slow ML response (exceeds timeout).
     */
    public function test_scenario_6_slow_ml_response(): void
    {
        $driver = $this->createDriver('Driver Near', -1.9441, 30.0619);

        // Mock ML service with a timeout (throwing connection exception)
        Http::fake([
            '*/match' => function () {
                throw new \Illuminate\Http\Client\ConnectionException('Connection timed out');
            },
        ]);

        $passenger = User::factory()->create(['role' => 'PASSENGER', 'is_approved' => true]);

        $response = $this->actingAs($passenger, 'sanctum')
            ->postJson('/api/v1/passenger/motor-vehicle/trip-requests', [
                'pickup_location' => 'Kigali Heights',
                'pickup_lat' => -1.9440,
                'pickup_lng' => 30.0618,
                'dropoff_location' => 'Kigali Convention Centre',
                'dropoff_lat' => -1.9536,
                'dropoff_lng' => 30.0928,
            ]);

        // Should fallback instantly to distance match
        $response->assertStatus(201);
        $this->assertDatabaseHas('motorcycle_trips', [
            'passenger_id' => $passenger->id,
            'driver_id' => $driver->id,
            'status' => 'ASSIGNED',
            'matched_via' => 'fast_local',
        ]);
    }

    /**
     * Scenario 7: Fallback matching activation.
     */
    public function test_scenario_7_fallback_matching_activation(): void
    {
        // Setup: Driver is online, active, has motorcycle, and available
        $driver = $this->createDriver('Nearest Driver', -1.9441, 30.0619);

        // Trigger fallback by faking ML service returning no driver or failing
        Http::fake([
            '*/match' => Http::response(['message' => 'No driver found'], 200),
        ]);

        $passenger = User::factory()->create(['role' => 'PASSENGER', 'is_approved' => true]);

        $response = $this->actingAs($passenger, 'sanctum')
            ->postJson('/api/v1/passenger/motor-vehicle/trip-requests', [
                'pickup_location' => 'Kigali Heights',
                'pickup_lat' => -1.9440,
                'pickup_lng' => 30.0618,
                'dropoff_location' => 'Kigali Convention Centre',
                'dropoff_lat' => -1.9536,
                'dropoff_lng' => 30.0928,
            ]);

        // Asserts fallback nearest driver is assigned immediately
        $response->assertStatus(201);
        $this->assertDatabaseHas('motorcycle_trips', [
            'passenger_id' => $passenger->id,
            'driver_id' => $driver->id,
            'status' => 'ASSIGNED',
            'matched_via' => 'fast_local',
        ]);
    }
}
