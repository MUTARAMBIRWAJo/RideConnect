<?php

namespace Tests\Feature\PublicBus;

use App\Models\Driver;
use App\Models\TransportCorridor;
use App\Models\Trip;
use App\Models\TripRequest;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\BusRouteAssignment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PassengerPublicBusMatchingTest extends TestCase
{
    use RefreshDatabase;

    private User $passenger;
    private Driver $driver;
    private Vehicle $bus;
    private TransportCorridor $corridor;
    private BusRouteAssignment $assignment;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setupTestData();
    }

    private function setupTestData(): void
    {
        // Create a passenger user
        $this->passenger = User::factory()
            ->create(['role' => 'PASSENGER', 'is_approved' => true, 'is_verified' => true]);

        // Create a driver user
        $driverUser = User::factory()
            ->create(['role' => 'DRIVER', 'is_approved' => true, 'is_verified' => true]);

        // Create driver profile
        $this->driver = Driver::factory()
            ->create(['user_id' => $driverUser->id]);

        // Create a bus vehicle
        $this->bus = Vehicle::factory()
            ->create([
                'driver_id' => $this->driver->id,
                'vehicle_type' => 'BUS',
                'seats' => 65,
                'is_active' => true,
            ]);

        // Create a transport corridor (Remera to Nyabugogo)
        $this->corridor = TransportCorridor::factory()
            ->create([
                'corridor_code' => '105',
                'corridor_name' => 'REMERA BUS PARK -> NYABUGOGO BUS PARK (105)',
                'transport_type' => 'BUS',
                'status' => 'active',
            ]);

        // Create bus route assignment
        $this->assignment = BusRouteAssignment::create([
            'bus_id' => $this->bus->id,
            'corridor_id' => $this->corridor->id,
            'driver_id' => $this->driver->id,
            'status' => 'active',
            'started_at' => now(),
        ]);
    }

    /** @test */
    public function passenger_can_request_public_bus_trip_with_location_names()
    {
        $response = $this->actingAs($this->passenger, 'sanctum')
            ->postJson('/api/v1/passenger/public-bus/request', [
                'corridor_id' => $this->corridor->id,
                'pickup_location' => 'Kimironko Market',
                'dropoff_location' => 'Nyabugogo Bus Park',
            ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'Public bus match found',
            ])
            ->assertJsonStructure([
                'data' => [
                    'trip_request_id',
                    'corridor' => ['id', 'code', 'name'],
                    'pickup' => ['name', 'latitude', 'longitude'],
                    'dropoff' => ['name', 'latitude', 'longitude'],
                    'matched_bus' => ['vehicle_id', 'plate_number', 'capacity', 'available_seats'],
                    'driver' => ['id', 'name'],
                    'distance_to_bus_km',
                    'bus_eta_minutes',
                    'trip_distance_km',
                    'trip_duration_minutes',
                    'estimated_fare',
                    'currency',
                    'status',
                ],
            ]);

        // Verify trip request was created
        $this->assertDatabaseHas('trip_requests', [
            'passenger_id' => $this->passenger->id,
            'corridor_id' => $this->corridor->id,
            'pickup_location' => 'Kimironko Market',
            'dropoff_location' => 'Nyabugogo Bus Park',
            'status' => 'PENDING_MATCH',
        ]);
    }

    /** @test */
    public function passenger_cannot_request_trip_with_invalid_corridor()
    {
        $response = $this->actingAs($this->passenger, 'sanctum')
            ->postJson('/api/v1/passenger/public-bus/request', [
                'corridor_id' => 9999,
                'pickup_location' => 'Kimironko Market',
                'dropoff_location' => 'Nyabugogo Bus Park',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['corridor_id']);
    }

    /** @test */
    public function unapproved_passenger_cannot_request_trip()
    {
        $unapprovedPassenger = User::factory()
            ->create(['role' => 'PASSENGER', 'is_approved' => false]);

        $response = $this->actingAs($unapprovedPassenger, 'sanctum')
            ->postJson('/api/v1/passenger/public-bus/request', [
                'corridor_id' => $this->corridor->id,
                'pickup_location' => 'Kimironko Market',
                'dropoff_location' => 'Nyabugogo Bus Park',
            ]);

        $response->assertStatus(403)
            ->assertJson(['success' => false]);
    }

    /** @test */
    public function only_passengers_can_request_trip()
    {
        $driver = User::factory()
            ->create(['role' => 'DRIVER', 'is_approved' => true]);

        $response = $this->actingAs($driver, 'sanctum')
            ->postJson('/api/v1/passenger/public-bus/request', [
                'corridor_id' => $this->corridor->id,
                'pickup_location' => 'Kimironko Market',
                'dropoff_location' => 'Nyabugogo Bus Park',
            ]);

        $response->assertStatus(403)
            ->assertJson(['success' => false]);
    }

    /** @test */
    public function passenger_can_view_trip_request()
    {
        // Create a trip request
        $tripRequest = TripRequest::factory()
            ->create([
                'passenger_id' => $this->passenger->id,
                'corridor_id' => $this->corridor->id,
                'matched_driver_id' => $this->driver->id,
                'matched_vehicle_id' => $this->bus->id,
            ]);

        $response = $this->actingAs($this->passenger, 'sanctum')
            ->getJson("/api/v1/passenger/public-bus/requests/{$tripRequest->id}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'id' => $tripRequest->id,
                    'trip_request_id' => $tripRequest->id,
                    'status' => $tripRequest->status,
                ],
            ])
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'trip_request_id',
                    'corridor',
                    'pickup',
                    'dropoff',
                    'matched_bus',
                    'driver',
                    'distance_to_bus_km',
                    'bus_eta_minutes',
                    'trip_distance_km',
                    'trip_duration_minutes',
                    'estimated_fare',
                    'currency',
                    'status',
                ],
            ]);
    }

    /** @test */
    public function passenger_cannot_view_other_passengers_trip_request()
    {
        $otherPassenger = User::factory()
            ->create(['role' => 'PASSENGER', 'is_approved' => true]);

        $tripRequest = TripRequest::factory()
            ->create([
                'passenger_id' => $otherPassenger->id,
                'corridor_id' => $this->corridor->id,
            ]);

        $response = $this->actingAs($this->passenger, 'sanctum')
            ->getJson("/api/v1/passenger/public-bus/requests/{$tripRequest->id}");

        $response->assertStatus(404);
    }

    /** @test */
    public function trip_request_has_correct_status()
    {
        $response = $this->actingAs($this->passenger, 'sanctum')
            ->postJson('/api/v1/passenger/public-bus/request', [
                'corridor_id' => $this->corridor->id,
                'pickup_location' => 'Kimironko Market',
                'dropoff_location' => 'Nyabugogo Bus Park',
            ]);

        $response->assertJsonPath('data.status', 'PENDING_MATCH');
    }

    /** @test */
    public function trip_request_includes_estimated_fare()
    {
        $response = $this->actingAs($this->passenger, 'sanctum')
            ->postJson('/api/v1/passenger/public-bus/request', [
                'corridor_id' => $this->corridor->id,
                'pickup_location' => 'Kimironko Market',
                'dropoff_location' => 'Nyabugogo Bus Park',
            ]);

        $response->assertJsonPath('data.estimated_fare', $response->json('data.estimated_fare'));
        $response->assertJsonPath('data.currency', 'RWF');
    }
}
