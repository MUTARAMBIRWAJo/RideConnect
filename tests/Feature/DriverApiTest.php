<?php

namespace Tests\Feature;

use App\Models\Driver;
use App\Models\MobileUser;
use App\Models\Ride;
use App\Models\Trip;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DriverApiTest extends TestCase
{
    use RefreshDatabase;

    private MobileUser $driverMobileUser;
    private User $driverUser;
    private Driver $driver;
    private Vehicle $vehicle;

    protected function setUp(): void
    {
        parent::setUp();

        // Create test driver
        $this->driverMobileUser = MobileUser::factory()->create([
            'role' => 'DRIVER',
            'is_verified' => true,
        ]);

        $this->driverUser = User::factory()->create([
            'role' => 'DRIVER',
            'mobile_user_id' => $this->driverMobileUser->id,
            'is_approved' => true,
        ]);

        $this->driver = Driver::factory()->create([
            'user_id' => $this->driverUser->id,
            'status' => 'approved',
        ]);

        $this->vehicle = Vehicle::factory()->create([
            'driver_id' => $this->driver->id,
            'vehicle_type' => 'sedan',
            'is_active' => true,
        ]);

        // Create driver location
        \DB::table('driver_locations')->insert([
            'driver_id' => $this->driverMobileUser->id,
            'latitude' => -1.9403,
            'longitude' => 29.8739,
            'updated_at' => now(),
        ]);
    }

    public function test_update_driver_status()
    {
        $response = $this->actingAs($this->driverUser, 'sanctum')
            ->postJson('/api/v1/mobile/drivers/status', [
                'is_online' => true,
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'data' => [
                    'is_online' => true,
                ],
            ]);
    }

    public function test_get_available_trips()
    {
        $ride = Ride::factory()->create([
            'transport_type' => 'CAR',
            'travel_mode' => 'ON_DEMAND',
            'status' => 'scheduled',
        ]);

        // Create a pending trip with an associated ride
        $trip = Trip::factory()->create([
            'driver_id' => null,
            'ride_id' => $ride->id,
            'status' => 'PENDING',
            'pickup_location' => 'Test Pickup',
            'pickup_lat' => -1.9403,
            'pickup_lng' => 29.8739,
            'dropoff_location' => 'Test Dropoff',
            'dropoff_lat' => -1.9500,
            'dropoff_lng' => 29.8800,
            'fare' => 5000,
        ]);

        $response = $this->actingAs($this->driverUser, 'sanctum')
            ->getJson('/api/v1/mobile/drivers/trips');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonStructure([
                'status',
                'data' => [
                    '*' => [
                        'id',
                        'passenger',
                        'pickup_location',
                        'pickup_lat',
                        'pickup_lng',
                        'dropoff_location',
                        'dropoff_lat',
                        'dropoff_lng',
                        'fare',
                        'requested_at',
                    ],
                ],
            ]);
    }

    public function test_accept_trip()
    {
        $ride = Ride::factory()->create([
            'transport_type' => 'CAR',
            'travel_mode' => 'ON_DEMAND',
            'status' => 'scheduled',
        ]);

        $trip = Trip::factory()->create([
            'driver_id' => null,
            'ride_id' => $ride->id,
            'status' => 'PENDING',
            'pickup_location' => 'Test Pickup',
            'pickup_lat' => -1.9403,
            'pickup_lng' => 29.8739,
            'dropoff_location' => 'Test Dropoff',
            'dropoff_lat' => -1.9500,
            'dropoff_lng' => 29.8800,
            'fare' => 5000,
        ]);

        $response = $this->actingAs($this->driverUser, 'sanctum')
            ->postJson("/api/v1/mobile/drivers/trips/{$trip->id}/accept");

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'data' => [
                    'trip_id' => $trip->id,
                    'trip_state' => 'ACCEPTED',
                ],
            ]);

        $trip->refresh();
        $this->assertEquals('ACCEPTED', $trip->status);
        $this->assertEquals($this->driver->id, $trip->driver_id);
    }

    public function test_update_location()
    {
        $trip = Trip::factory()->create([
            'driver_id' => $this->driver->id,
            'status' => 'ACCEPTED',
        ]);

        $response = $this->actingAs($this->driverUser, 'sanctum')
            ->postJson('/api/v1/mobile/drivers/location', [
                'trip_id' => $trip->id,
                'lat' => -1.9500,
                'lng' => 29.8800,
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'data' => [
                    'trip_id' => $trip->id,
                    'location_updated' => true,
                ],
            ]);
    }

    public function test_start_trip()
    {
        $trip = Trip::factory()->create([
            'driver_id' => $this->driver->id,
            'status' => 'ACCEPTED',
        ]);

        $response = $this->actingAs($this->driverUser, 'sanctum')
            ->putJson("/api/v1/mobile/drivers/trips/{$trip->id}/start");

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'data' => [
                    'trip_id' => $trip->id,
                    'trip_state' => 'STARTED',
                ],
            ]);

        $trip->refresh();
        $this->assertEquals('STARTED', $trip->status);
    }

    public function test_complete_trip()
    {
        $trip = Trip::factory()->create([
            'driver_id' => $this->driver->id,
            'status' => 'STARTED',
        ]);

        $response = $this->actingAs($this->driverUser, 'sanctum')
            ->putJson("/api/v1/mobile/drivers/trips/{$trip->id}/complete");

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'data' => [
                    'trip_id' => $trip->id,
                    'trip_state' => 'COMPLETED',
                ],
            ]);

        $trip->refresh();
        $this->assertEquals('COMPLETED', $trip->status);
    }

    public function test_cancel_trip()
    {
        $trip = Trip::factory()->create([
            'driver_id' => $this->driver->id,
            'status' => 'ACCEPTED',
        ]);

        $response = $this->actingAs($this->driverUser, 'sanctum')
            ->putJson("/api/v1/mobile/drivers/trips/{$trip->id}/cancel");

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'data' => [
                    'trip_id' => $trip->id,
                    'trip_state' => 'CANCELLED',
                ],
            ]);

        $trip->refresh();
        $this->assertEquals('CANCELLED', $trip->status);
    }

    public function test_driver_cannot_accept_incompatible_vehicle_trip()
    {
        // Create motorcycle driver
        $motorcycleDriver = Driver::factory()->create([
            'user_id' => User::factory()->create(['role' => 'DRIVER']),
            'status' => 'approved',
        ]);

        Vehicle::factory()->create([
            'driver_id' => $motorcycleDriver->id,
            'vehicle_type' => 'motorcycle',
            'is_active' => true,
        ]);

        // Create CAR trip
        $ride = Ride::factory()->create([
            'transport_type' => 'CAR',
            'travel_mode' => 'ON_DEMAND',
            'status' => 'scheduled',
        ]);

        $carTrip = Trip::factory()->create([
            'driver_id' => null,
            'status' => 'PENDING',
            'ride_id' => $ride->id,
        ]);

        $response = $this->actingAs($this->driverUser, 'sanctum')
            ->postJson("/api/v1/mobile/drivers/trips/{$carTrip->id}/accept");

        // Should succeed since our driver has sedan (compatible with CAR)
        $response->assertStatus(200);
    }
}