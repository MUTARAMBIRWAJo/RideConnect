<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Ride;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class BookingConcurrencySafetyTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function concurrent_booking_does_not_overbook_with_lock()
    {
        // Create a ride with only 1 seat
        $driver = \App\Models\Driver::factory()->create();
        $vehicle = \App\Models\Vehicle::factory()->create([
            'driver_id' => $driver->id,
            'vehicle_type' => 'sedan',
        ]);

        $ride = Ride::factory()->create([
            'driver_id' => $driver->id,
            'vehicle_id' => $vehicle->id,
            'travel_mode' => Ride::MODE_SCHEDULED,
            'transport_type' => Ride::TRANSPORT_CAR,
            'available_seats' => 1,
            'status' => 'PUBLISHED',
            'departure_time' => now()->addHours(2),
        ]);

        // Create two passengers
        $passenger1 = User::factory()->create(['is_approved' => true]);
        $passenger2 = User::factory()->create(['is_approved' => true]);

        // First passenger books 1 seat
        $response1 = $this->actingAs($passenger1)->postJson('/api/v1/passenger/bookings', [
            'ride_id' => $ride->id,
            'seats_booked' => 1,
            'pickup_address' => 'A',
            'pickup_lat' => -1.9,
            'pickup_lng' => 30.0,
            'dropoff_address' => 'B',
            'dropoff_lat' => -1.9,
            'dropoff_lng' => 30.0,
        ]);

        $response1->assertStatus(201);

        // Verify available seats is now 0
        $ride->refresh();
        $this->assertEquals(0, $ride->available_seats);

        // Second passenger tries to book 1 seat (should fail because no seats left)
        $response2 = $this->actingAs($passenger2)->postJson('/api/v1/passenger/bookings', [
            'ride_id' => $ride->id,
            'seats_booked' => 1,
            'pickup_address' => 'A',
            'pickup_lat' => -1.9,
            'pickup_lng' => 30.0,
            'dropoff_address' => 'B',
            'dropoff_lat' => -1.9,
            'dropoff_lng' => 30.0,
        ]);

        $response2->assertStatus(422);

        // Verify still only 1 booking exists
        $this->assertEquals(1, Booking::count());
    }

    /** @test */
    public function booking_validation_uses_ride_policy()
    {
        // Create an ON_DEMAND ride
        $driver = \App\Models\Driver::factory()->create();
        $vehicle = \App\Models\Vehicle::factory()->create([
            'driver_id' => $driver->id,
            'vehicle_type' => 'motorcycle',
        ]);

        $ride = Ride::factory()->create([
            'driver_id' => $driver->id,
            'vehicle_id' => $vehicle->id,
            'travel_mode' => Ride::MODE_ON_DEMAND,
            'transport_type' => Ride::TRANSPORT_MOTORCYCLE,
            'status' => 'PUBLISHED',
        ]);

        $passenger = User::factory()->create(['is_approved' => true]);

        // Try to book ON_DEMAND ride (should fail - bookings only for SCHEDULED)
        $response = $this->actingAs($passenger)->postJson('/api/v1/passenger/bookings', [
            'ride_id' => $ride->id,
            'seats_booked' => 1,
            'pickup_address' => 'A',
            'pickup_lat' => -1.9,
            'pickup_lng' => 30.0,
            'dropoff_address' => 'B',
            'dropoff_lat' => -1.9,
            'dropoff_lng' => 30.0,
        ]);

        $response->assertStatus(422);
        $response->assertJsonPath('error_code', 'BOOKING_NOT_ALLOWED_FOR_TRAVEL_MODE');
    }

    /** @test */
    public function booking_prevents_departed_ride()
    {
        $driver = \App\Models\Driver::factory()->create();
        $vehicle = \App\Models\Vehicle::factory()->create([
            'driver_id' => $driver->id,
            'vehicle_type' => 'sedan',
        ]);

        // Create ride that departed 1 hour ago
        $ride = Ride::factory()->create([
            'driver_id' => $driver->id,
            'vehicle_id' => $vehicle->id,
            'travel_mode' => Ride::MODE_SCHEDULED,
            'transport_type' => Ride::TRANSPORT_CAR,
            'status' => 'PUBLISHED',
            'departure_time' => now()->subHour(),
            'available_seats' => 5,
        ]);

        $passenger = User::factory()->create(['is_approved' => true]);

        // Try to book departed ride (should fail)
        $response = $this->actingAs($passenger)->postJson('/api/v1/passenger/bookings', [
            'ride_id' => $ride->id,
            'seats_booked' => 1,
            'pickup_address' => 'A',
            'pickup_lat' => -1.9,
            'pickup_lng' => 30.0,
            'dropoff_address' => 'B',
            'dropoff_lat' => -1.9,
            'dropoff_lng' => 30.0,
        ]);

        $response->assertStatus(422);
        $response->assertJsonPath('error_code', 'RIDE_DEPARTED');
    }

    /** @test */
    public function api_contract_includes_ride_rules()
    {
        $driver = \App\Models\Driver::factory()->create();
        $vehicle = \App\Models\Vehicle::factory()->create(['driver_id' => $driver->id]);

        $ride = Ride::factory()->create([
            'driver_id' => $driver->id,
            'vehicle_id' => $vehicle->id,
            'travel_mode' => Ride::MODE_SCHEDULED,
            'transport_type' => Ride::TRANSPORT_CAR,
        ]);

        $user = User::factory()->create(['is_approved' => true]);
        $response = $this->actingAs($user)->getJson('/api/v1/passenger/rides/available');

        $response->assertStatus(200);
        // Verify ride_rules exist instead of individual flags
        $response->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'transport_type',
                    'travel_mode',
                    'ride_rules' => [
                        'can_book',
                        'can_request_trip',
                        'allowed_flow',
                    ],
                ],
            ],
        ]);
    }

    /** @test */
    public function ride_rules_correctly_indicate_scheduled_car_can_book()
    {
        $driver = \App\Models\Driver::factory()->create();
        $vehicle = \App\Models\Vehicle::factory()->create(['driver_id' => $driver->id]);

        $ride = Ride::factory()->create([
            'driver_id' => $driver->id,
            'vehicle_id' => $vehicle->id,
            'travel_mode' => Ride::MODE_SCHEDULED,
            'transport_type' => Ride::TRANSPORT_CAR,
        ]);

        $user = User::factory()->create(['is_approved' => true]);
        $response = $this->actingAs($user)->getJson("/api/v1/passenger/rides/{$ride->id}");

        $response->assertStatus(200);
        $response->assertJsonPath('data.ride_rules.can_book', true);
        $response->assertJsonPath('data.ride_rules.can_request_trip', false);
        $response->assertJsonPath('data.ride_rules.allowed_flow', 'BOOKING_ONLY');
    }

    /** @test */
    public function ride_rules_correctly_indicate_ondemand_car_can_request_trip()
    {
        $driver = \App\Models\Driver::factory()->create();
        $vehicle = \App\Models\Vehicle::factory()->create(['driver_id' => $driver->id]);

        $ride = Ride::factory()->create([
            'driver_id' => $driver->id,
            'vehicle_id' => $vehicle->id,
            'travel_mode' => Ride::MODE_ON_DEMAND,
            'transport_type' => Ride::TRANSPORT_CAR,
        ]);

        $user = User::factory()->create(['is_approved' => true]);
        $response = $this->actingAs($user)->getJson("/api/v1/passenger/rides/{$ride->id}");

        $response->assertStatus(200);
        $response->assertJsonPath('data.ride_rules.can_book', false);
        $response->assertJsonPath('data.ride_rules.can_request_trip', true);
        $response->assertJsonPath('data.ride_rules.allowed_flow', 'TRIP_ONLY');
    }
}
