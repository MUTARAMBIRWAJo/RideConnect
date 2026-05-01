<?php

namespace Tests\Feature;

use App\Http\Controllers\Api\TripController;
use App\Models\Driver;
use App\Models\MobileUser;
use App\Models\Ride;
use App\Models\Trip;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class TripCreationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Route::post('/test/trips', [TripController::class, 'store']);
    }

    public function test_trip_requires_locations()
    {
        $user = User::factory()->create(['is_approved' => true, 'role' => 'PASSENGER']);
        $mobileUser = MobileUser::factory()->create(['email' => $user->email]);
        $user->update(['mobile_user_id' => $mobileUser->id]);

        $response = $this->actingAs($user)->postJson('/test/trips', [
            'ride_id' => null,
            'pickup_location' => null,
            'pickup_lat' => null,
            'pickup_lng' => null,
            'dropoff_location' => null,
            'dropoff_lat' => null,
            'dropoff_lng' => null,
            'fare' => 100,
        ]);

        $response->assertStatus(422);
        $response->assertJson([
            'status' => 'error',
            'message' => 'Pickup and dropoff locations are required',
        ]);
    }

    public function test_trip_auto_assigns_driver_from_ride()
    {
        $user = User::factory()->create(['is_approved' => true, 'role' => 'PASSENGER']);
        $mobileUser = MobileUser::factory()->create(['email' => $user->email]);
        $user->update(['mobile_user_id' => $mobileUser->id]);

        $driverUser = User::factory()->create(['is_approved' => true, 'role' => 'DRIVER']);
        $driver = Driver::factory()->create(['user_id' => $driverUser->id, 'status' => 'active']);
        $vehicle = Vehicle::factory()->create(['driver_id' => $driver->id, 'is_active' => true]);

        $ride = Ride::factory()->create([
            'driver_id' => $driver->id,
            'vehicle_id' => $vehicle->id,
            'transport_type' => Ride::TRANSPORT_CAR,
            'travel_mode' => Ride::MODE_ON_DEMAND,
            'origin_address' => 'Origin Drive',
            'origin_lat' => -1.9403,
            'origin_lng' => 29.8739,
            'destination_address' => 'Destination Ave',
            'destination_lat' => -1.9500,
            'destination_lng' => 30.0588,
            'status' => 'PUBLISHED',
        ]);

        $response = $this->actingAs($user)->postJson('/test/trips', [
            'ride_id' => $ride->id,
            'pickup_location' => 'Origin Drive',
            'pickup_lat' => -1.9403,
            'pickup_lng' => 29.8739,
            'dropoff_location' => 'Destination Ave',
            'dropoff_lat' => -1.9500,
            'dropoff_lng' => 30.0588,
            'fare' => 150,
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('data.ride_id', $ride->id);
        $response->assertJsonStructure(['data' => ['id', 'ride_id', 'status', 'trip_state', 'driver_location', 'eta']]);

        $tripId = $response->json('data.id');
        $trip = Trip::find($tripId);

        $this->assertNotNull($trip);
        $this->assertSame($driver->id, $trip->driver_id);
    }

    public function test_trip_fails_without_driver_for_selected_ride()
    {
        $user = User::factory()->create(['is_approved' => true, 'role' => 'PASSENGER']);
        $mobileUser = MobileUser::factory()->create(['email' => $user->email]);
        $user->update(['mobile_user_id' => $mobileUser->id]);

        $ride = Ride::factory()->create([
            'driver_id' => null,
            'transport_type' => Ride::TRANSPORT_CAR,
            'travel_mode' => Ride::MODE_ON_DEMAND,
            'origin_address' => 'Origin Null',
            'origin_lat' => -1.9403,
            'origin_lng' => 29.8739,
            'destination_address' => 'Destination Null',
            'destination_lat' => -1.9500,
            'destination_lng' => 30.0588,
            'status' => 'PUBLISHED',
        ]);

        $response = $this->actingAs($user)->postJson('/test/trips', [
            'ride_id' => $ride->id,
            'pickup_location' => 'Origin Null',
            'pickup_lat' => -1.9403,
            'pickup_lng' => 29.8739,
            'dropoff_location' => 'Destination Null',
            'dropoff_lat' => -1.9500,
            'dropoff_lng' => 30.0588,
            'fare' => 150,
        ]);

        $response->assertStatus(422);
        $response->assertJson([
            'status' => 'error',
            'message' => 'No active driver available for this ride',
        ]);
    }

    public function test_trip_fails_without_coordinates()
    {
        $user = User::factory()->create(['is_approved' => true, 'role' => 'PASSENGER']);
        $mobileUser = MobileUser::factory()->create(['email' => $user->email]);
        $user->update(['mobile_user_id' => $mobileUser->id]);

        $response = $this->actingAs($user)->postJson('/test/trips', [
            'ride_id' => null,
            'pickup_location' => 'Valid pickup',
            'pickup_lat' => null,
            'pickup_lng' => null,
            'dropoff_location' => 'Valid dropoff',
            'dropoff_lat' => null,
            'dropoff_lng' => null,
            'fare' => 100,
        ]);

        $response->assertStatus(422);
        $response->assertJson([
            'status' => 'error',
            'message' => 'Pickup and dropoff locations are required',
        ]);
    }
}
