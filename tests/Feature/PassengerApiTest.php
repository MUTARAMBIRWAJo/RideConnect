<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Driver;
use App\Models\MobileUser;
use App\Models\Ride;
use App\Models\Trip;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PassengerApiTest extends TestCase
{
    use RefreshDatabase;

    private MobileUser $passenger;
    private User $passengerUser;
    private Driver $carDriver;
    private Vehicle $carVehicle;

    protected function setUp(): void
    {
        parent::setUp();

        // Create test passenger
        $this->passenger = MobileUser::factory()->create([
            'role' => 'PASSENGER',
            'is_verified' => true,
        ]);

        $this->passengerUser = User::factory()->create([
            'role' => 'PASSENGER',
            'mobile_user_id' => $this->passenger->id,
            'is_approved' => true,
        ]);

        // Create CAR driver
        $carMobileUser = MobileUser::factory()->create([
            'role' => 'DRIVER',
            'is_verified' => true,
        ]);
        $carDriverUser = User::factory()->create([
            'role' => 'DRIVER',
            'mobile_user_id' => $carMobileUser->id,
            'is_approved' => true,
        ]);
        $this->carDriver = Driver::factory()->create([
            'user_id' => $carDriverUser->id,
            'status' => 'approved',
        ]);
        $this->carVehicle = Vehicle::factory()->create([
            'driver_id' => $this->carDriver->id,
            'vehicle_type' => 'sedan',
            'is_active' => true,
        ]);

        // Create driver location
        \DB::table('driver_locations')->insert([
            'driver_id' => $carMobileUser->id,
            'latitude' => -1.9403,
            'longitude' => 29.8739,
            'updated_at' => now(),
        ]);
    }

    public function test_get_rides_returns_correct_structure()
    {
        // Create test rides
        Ride::factory()->create([
            'transport_type' => 'CAR',
            'travel_mode' => 'ON_DEMAND',
            'status' => 'scheduled',
            'available_seats' => 4,
        ]);

        $response = $this->actingAs($this->passengerUser, 'sanctum')
            ->getJson('/api/v1/mobile/rides');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'data' => [
                    '*' => [
                        'id',
                        'transport_type',
                        'travel_mode',
                        'origin',
                        'destination',
                        'price',
                        'ride_rules' => [
                            'can_book',
                            'can_request_trip',
                            'allowed_flow',
                        ],
                    ],
                ],
            ]);
    }

    public function test_cannot_book_on_demand_ride()
    {
        $ride = Ride::factory()->create([
            'transport_type' => 'CAR',
            'travel_mode' => 'ON_DEMAND',
            'status' => 'scheduled',
        ]);

        $response = $this->actingAs($this->passengerUser, 'sanctum')
            ->postJson('/api/v1/mobile/bookings', [
                'ride_id' => $ride->id,
                'seats' => 1,
            ]);

        $response->assertStatus(422)
            ->assertJson([
                'status' => 'error',
                'type' => 'DOMAIN_ERROR',
            ]);
    }

    public function test_can_book_scheduled_ride()
    {
        $ride = Ride::factory()->create([
            'transport_type' => 'CAR',
            'travel_mode' => 'SCHEDULED',
            'status' => 'scheduled',
            'price_per_seat' => 5000,
        ]);

        $response = $this->actingAs($this->passengerUser, 'sanctum')
            ->postJson('/api/v1/mobile/bookings', [
                'ride_id' => $ride->id,
                'seats' => 2,
            ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'status',
                'data' => [
                    'id',
                    'status',
                    'total_price',
                    'currency',
                ],
            ]);
    }

    public function test_can_request_on_demand_trip()
    {
        $ride = Ride::factory()->create([
            'transport_type' => 'CAR',
            'travel_mode' => 'ON_DEMAND',
            'status' => 'scheduled',
        ]);

        $response = $this->actingAs($this->passengerUser, 'sanctum')
            ->postJson('/api/v1/mobile/trips/request', [
                'ride_id' => $ride->id,
                'pickup_location' => 'Downtown',
                'pickup_lat' => -1.9403,
                'pickup_lng' => 29.8739,
                'dropoff_location' => 'Airport',
                'dropoff_lat' => -1.9764,
                'dropoff_lng' => 30.0116,
            ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'status',
                'data' => [
                    'id',
                    'trip_state',
                ],
            ]);
    }

    public function test_get_current_trip_returns_active_trip()
    {
        $trip = Trip::factory()->create([
            'passenger_id' => $this->passenger->id,
            'status' => 'ACCEPTED',
            'pickup_location' => 'Test Pickup',
            'pickup_lat' => -1.9403,
            'pickup_lng' => 29.8739,
            'dropoff_location' => 'Test Dropoff',
            'dropoff_lat' => -1.9500,
            'dropoff_lng' => 29.8800,
            'fare' => 5000,
        ]);

        $response = $this->actingAs($this->passengerUser, 'sanctum')
            ->getJson('/api/v1/mobile/trips/current');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'data' => [
                    'trip_id',
                    'trip_state',
                    'driver_location',
                    'eta',
                    'fare',
                ],
            ]);
    }

    public function test_track_trip_returns_location_data()
    {
        $trip = Trip::factory()->create([
            'passenger_id' => $this->passenger->id,
            'driver_id' => $this->carDriver->id,
            'status' => 'STARTED',
        ]);

        $response = $this->actingAs($this->passengerUser, 'sanctum')
            ->getJson("/api/v1/mobile/trips/{$trip->id}/track");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'data' => [
                    'driver_location',
                    'route_path',
                    'eta',
                ],
            ]);
    }

    public function test_api_returns_ride_rules_correctly()
    {
        $scheduledRide = Ride::factory()->create([
            'transport_type' => 'CAR',
            'travel_mode' => 'SCHEDULED',
            'status' => 'scheduled',
        ]);

        $onDemandRide = Ride::factory()->create([
            'transport_type' => 'CAR',
            'travel_mode' => 'ON_DEMAND',
            'status' => 'scheduled',
        ]);

        $response = $this->actingAs($this->passengerUser, 'sanctum')
            ->getJson('/api/v1/mobile/rides');

        $response->assertStatus(200);

        $data = $response->json('data');
        $scheduled = collect($data)->firstWhere('id', $scheduledRide->id);
        $onDemand = collect($data)->firstWhere('id', $onDemandRide->id);

        $this->assertTrue($scheduled['ride_rules']['can_book']);
        $this->assertFalse($scheduled['ride_rules']['can_request_trip']);
        $this->assertEquals('BOOKING_ONLY', $scheduled['ride_rules']['allowed_flow']);

        $this->assertFalse($onDemand['ride_rules']['can_book']);
        $this->assertTrue($onDemand['ride_rules']['can_request_trip']);
        $this->assertEquals('TRIP_ONLY', $onDemand['ride_rules']['allowed_flow']);
    }
}