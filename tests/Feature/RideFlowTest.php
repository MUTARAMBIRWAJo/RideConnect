<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Ride;
use App\Models\Trip;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RideFlowTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function cannot_overbook_seats()
    {
        $user = User::factory()->create(['is_approved' => true]);
        $driver = \App\Models\Driver::factory()->create(['user_id' => $user->id]);
        $vehicle = \App\Models\Vehicle::factory()->create(['driver_id' => $driver->id, 'vehicle_type' => 'van']);
        $ride = Ride::factory()->create([
            'driver_id' => $driver->id,
            'vehicle_id' => $vehicle->id,
            'travel_mode' => Ride::MODE_SCHEDULED,
            'transport_type' => Ride::TRANSPORT_BUS,
            'available_seats' => 1,
            'status' => 'PUBLISHED',
            'departure_time' => now()->addHours(2),
        ]);

        $this->actingAs($user)->postJson('/api/v1/passenger/bookings', [
            'ride_id' => $ride->id,
            'seats_booked' => 2,
            'pickup_address' => 'A',
            'pickup_lat' => -1.9,
            'pickup_lng' => 30.0,
            'dropoff_address' => 'B',
            'dropoff_lat' => -1.9,
            'dropoff_lng' => 30.0,
        ])->assertStatus(422);
    }

    /** @test */
    public function driver_cannot_accept_wrong_vehicle_type()
    {
        $this->withoutExceptionHandling();
        $passenger = User::factory()->create(['is_approved' => true, 'role' => \App\Enums\UserRole::PASSENGER]);
        $mobileUser = \App\Models\MobileUser::factory()->create(['email' => $passenger->email]);
        $passenger->update(['mobile_user_id' => $mobileUser->id]);

        $driverUser = User::factory()->create(['is_approved' => true, 'role' => \App\Enums\UserRole::DRIVER]);
        $driver = \App\Models\Driver::factory()->create(['user_id' => $driverUser->id]);
        // Driver has a sedan (car) but ride requires motorcycle
        $vehicle = \App\Models\Vehicle::factory()->create(['driver_id' => $driver->id, 'vehicle_type' => 'sedan']);

        $ride = Ride::factory()->create([
            'driver_id' => $driver->id,
            'vehicle_id' => $vehicle->id,
            'transport_type' => Ride::TRANSPORT_MOTORCYCLE,
            'travel_mode' => Ride::MODE_ON_DEMAND,
            'status' => 'PUBLISHED',
        ]);

        $trip = Trip::factory()->create([
            'passenger_id' => $mobileUser->id,
            'ride_id' => $ride->id,
            'driver_id' => null,
            'status' => 'PENDING',
        ]);

        // Register temporary route to exercise TripController::accept
        \Illuminate\Support\Facades\Route::put('/test/trips/{id}/accept', [\App\Http\Controllers\Api\TripController::class, 'accept']);

        $this->actingAs($driverUser)->putJson('/test/trips/' . $trip->id . '/accept')
            ->assertStatus(422);
    }

    /** @test */
    public function api_returns_correct_flags()
    {
        $ride = Ride::factory()->create([
            'transport_type' => Ride::TRANSPORT_BUS,
            'travel_mode' => Ride::MODE_SCHEDULED,
        ]);
        $user = User::factory()->create(['is_approved' => true]);
        $response = $this->actingAs($user)->getJson('/api/v1/passenger/rides/available');
        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [[
                'transport_type',
                'travel_mode',
                'ride_rules' => ['can_book', 'can_request_trip', 'allowed_flow'],
            ]],
        ]);
    }

    /** @test */
    public function driver_can_accept_with_compatible_vehicle_type()
    {
        $this->withoutExceptionHandling();
        $passenger = User::factory()->create(['is_approved' => true, 'role' => \App\Enums\UserRole::PASSENGER]);
        $mobileUser = \App\Models\MobileUser::factory()->create(['email' => $passenger->email]);
        $passenger->update(['mobile_user_id' => $mobileUser->id]);

        $driverUser = User::factory()->create(['is_approved' => true, 'role' => \App\Enums\UserRole::DRIVER]);
        $driver = \App\Models\Driver::factory()->create(['user_id' => $driverUser->id]);
        // Driver has a van (bus) and ride requires bus
        $vehicle = \App\Models\Vehicle::factory()->create(['driver_id' => $driver->id, 'vehicle_type' => 'van']);

        $ride = Ride::factory()->create([
            'driver_id' => $driver->id,
            'vehicle_id' => $vehicle->id,
            'transport_type' => Ride::TRANSPORT_BUS,
            'travel_mode' => Ride::MODE_SCHEDULED,
            'status' => 'PUBLISHED',
        ]);

        $trip = Trip::factory()->create([
            'passenger_id' => $mobileUser->id,
            'ride_id' => $ride->id,
            'driver_id' => null,
            'status' => 'PENDING',
        ]);

        // Register temporary route
        \Illuminate\Support\Facades\Route::put('/test/trips/{id}/accept', [\App\Http\Controllers\Api\TripController::class, 'accept']);

        $response = $this->actingAs($driverUser)->putJson('/test/trips/' . $trip->id . '/accept');
        $response->assertStatus(200);
        $response->assertJson(['success' => true]);
    }

    /** @test */
    public function driver_can_accept_car_ride_with_suv()
    {
        $this->withoutExceptionHandling();
        $passenger = User::factory()->create(['is_approved' => true, 'role' => \App\Enums\UserRole::PASSENGER]);
        $mobileUser = \App\Models\MobileUser::factory()->create(['email' => $passenger->email]);
        $passenger->update(['mobile_user_id' => $mobileUser->id]);

        $driverUser = User::factory()->create(['is_approved' => true, 'role' => \App\Enums\UserRole::DRIVER]);
        $driver = \App\Models\Driver::factory()->create(['user_id' => $driverUser->id]);
        // Driver has a suv (car) and ride requires car
        $vehicle = \App\Models\Vehicle::factory()->create(['driver_id' => $driver->id, 'vehicle_type' => 'suv']);

        $ride = Ride::factory()->create([
            'driver_id' => $driver->id,
            'vehicle_id' => $vehicle->id,
            'transport_type' => Ride::TRANSPORT_CAR,
            'travel_mode' => Ride::MODE_SCHEDULED,
            'status' => 'PUBLISHED',
        ]);

        $trip = Trip::factory()->create([
            'passenger_id' => $mobileUser->id,
            'ride_id' => $ride->id,
            'driver_id' => null,
            'status' => 'PENDING',
        ]);

        // Register temporary route
        \Illuminate\Support\Facades\Route::put('/test/trips/{id}/accept', [\App\Http\Controllers\Api\TripController::class, 'accept']);

        $response = $this->actingAs($driverUser)->putJson('/test/trips/' . $trip->id . '/accept');
        $response->assertStatus(200);
        $response->assertJson(['success' => true]);
    }

    /** @test */
    public function ride_model_has_vehicle_compatibility_helper()
    {
        $ride = Ride::factory()->create([
            'transport_type' => Ride::TRANSPORT_BUS,
        ]);

        $this->assertTrue($ride->isVehicleCompatible('van'));
        $this->assertTrue($ride->isVehicleCompatible('bus'));
        $this->assertFalse($ride->isVehicleCompatible('sedan'));
        $this->assertFalse($ride->isVehicleCompatible('motorbike'));
    }
}


