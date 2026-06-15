<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Ride;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class RideTransportClassificationTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function cannot_create_booking_for_on_demand_ride()
    {
        $user = User::factory()->create(['is_approved' => true]);
        $driver = \App\Models\Driver::factory()->create(['user_id' => $user->id]);
        $vehicle = \App\Models\Vehicle::factory()->create(['driver_id' => $driver->id]);
        $ride = Ride::factory()->create([
            'id' => random_int(1000000, 1999999),
            'driver_id' => $driver->id,
            'vehicle_id' => $vehicle->id,
            'travel_mode' => Ride::MODE_ON_DEMAND,
            'status' => 'scheduled',
            'available_seats' => 4,
            'price_per_seat' => 1000,
        ]);

        $response = $this->actingAs($user)->postJson('/api/v1/passenger/bookings', [
            'ride_id' => $ride->id,
            'seats_booked' => 1,
            'pickup_address' => 'Test Pickup',
            'pickup_lat' => -1.9441,
            'pickup_lng' => 30.0619,
            'dropoff_address' => 'Test Dropoff',
            'dropoff_lat' => -1.9441,
            'dropoff_lng' => 30.0619,
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'Bookings are only allowed on SCHEDULED rides',
            ]);
    }

    #[Test]
    public function can_create_booking_for_scheduled_ride()
    {
        $user = User::factory()->create(['is_approved' => true]);
        $driver = \App\Models\Driver::factory()->create(['user_id' => $user->id]);
        $vehicle = \App\Models\Vehicle::factory()->create(['driver_id' => $driver->id]);
        $ride = Ride::factory()->create([
            'id' => random_int(1000000, 1999999),
            'driver_id' => $driver->id,
            'vehicle_id' => $vehicle->id,
            'travel_mode' => Ride::MODE_SCHEDULED,
            'status' => 'scheduled',
            'available_seats' => 4,
            'price_per_seat' => 1000,
            'departure_time' => now()->addHours(2),
        ]);

        $response = $this->actingAs($user)->postJson('/api/v1/passenger/bookings', [
            'ride_id' => $ride->id,
            'seats_booked' => 1,
            'pickup_address' => 'Test Pickup',
            'pickup_lat' => -1.9441,
            'pickup_lng' => 30.0619,
            'dropoff_address' => 'Test Dropoff',
            'dropoff_lat' => -1.9441,
            'dropoff_lng' => 30.0619,
        ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'Booking created successfully',
            ]);

        $this->assertDatabaseHas('bookings', [
            'user_id' => $user->id,
            'ride_id' => $ride->id,
            'seats_booked' => 1,
            'status' => 'pending',
        ]);
    }

    #[Test]
    public function can_create_trip_directly_for_on_demand_ride()
    {
        $user = User::factory()->create(['is_approved' => true, 'role' => \App\Enums\UserRole::PASSENGER]);
        $mobileUser = \App\Models\MobileUser::factory()->create(['email' => $user->email]);
        $user->update(['mobile_user_id' => $mobileUser->id]);

        $driver = \App\Models\Driver::factory()->create(['availability_status' => 'online']);
        $driver->user()->update(['is_approved' => true]);

        $response = $this->actingAs($user)->postJson('/api/v1/passenger/ride-requests', [
            'driver_id' => $driver->id,
            'pickup_location' => 'Test Pickup',
            'pickup_lat' => -1.9441,
            'pickup_lng' => 30.0619,
            'dropoff_location' => 'Test Dropoff',
            'dropoff_lat' => -1.9441,
            'dropoff_lng' => 30.0619,
            'fare' => 5000,
        ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'Ride request sent to driver',
            ]);

        $this->assertDatabaseHas('trips', [
            'passenger_id' => $user->mobile_user_id,
            'driver_id' => $driver->id,
            'pickup_location' => 'Test Pickup',
            'dropoff_location' => 'Test Dropoff',
            'fare' => 5000,
            'status' => 'REQUESTED',
        ]);
    }

    #[Test]
    public function trip_from_booking_works_correctly()
    {
        $admin = User::factory()->create(['is_approved' => true, 'role' => \App\Enums\UserRole::SUPER_ADMIN]);
        $passenger = User::factory()->create(['is_approved' => true, 'role' => \App\Enums\UserRole::PASSENGER]);
        $passengerMobileUser = \App\Models\MobileUser::factory()->create(['email' => $passenger->email]);
        $passenger->update(['mobile_user_id' => $passengerMobileUser->id]);

        $driver = \App\Models\Driver::factory()->create();
        $vehicle = \App\Models\Vehicle::factory()->create(['driver_id' => $driver->id]);
        $ride = Ride::factory()->create([
            'id' => random_int(1000000, 1999999),
            'driver_id' => $driver->id,
            'vehicle_id' => $vehicle->id,
            'travel_mode' => Ride::MODE_SCHEDULED,
            'status' => 'scheduled',
        ]);
        $booking = Booking::factory()->create([
            'user_id' => $passenger->id,
            'ride_id' => $ride->id,
        ]);

        $response = $this->actingAs($admin)->postJson('/api/v1/passenger/trips/create-from-booking', [
            'booking_id' => $booking->id,
        ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'Trip created from booking successfully',
            ]);

        $this->assertDatabaseHas('trips', [
            'booking_id' => $booking->id,
            'ride_id' => $ride->id,
            'status' => 'REQUESTED',
        ]);
    }

    #[Test]
    public function ride_classification_works_correctly()
    {
        $busRide = Ride::factory()->create([
            'transport_type' => Ride::TRANSPORT_BUS,
            'travel_mode' => Ride::MODE_SCHEDULED,
            'route_id' => $this->createRouteId(),
        ]);

        $carScheduledRide = Ride::factory()->create([
            'transport_type' => Ride::TRANSPORT_CAR,
            'travel_mode' => Ride::MODE_SCHEDULED,
        ]);

        $carOnDemandRide = Ride::factory()->create([
            'transport_type' => Ride::TRANSPORT_CAR,
            'travel_mode' => Ride::MODE_ON_DEMAND,
        ]);

        $motorcycleRide = Ride::factory()->create([
            'transport_type' => Ride::TRANSPORT_MOTORCYCLE,
            'travel_mode' => Ride::MODE_ON_DEMAND,
        ]);

        // Test bus
        $this->assertTrue($busRide->isBus());
        $this->assertTrue($busRide->isScheduled());
        $this->assertFalse($busRide->isOnDemand());

        // Test car scheduled
        $this->assertTrue($carScheduledRide->isCar());
        $this->assertTrue($carScheduledRide->isScheduled());

        // Test car on-demand
        $this->assertTrue($carOnDemandRide->isCar());
        $this->assertTrue($carOnDemandRide->isOnDemand());

        // Test motorcycle
        $this->assertTrue($motorcycleRide->isMotorcycle());
        $this->assertTrue($motorcycleRide->isOnDemand());
    }

    #[Test]
    public function ride_validation_rules_work()
    {
        // Valid combinations should not throw exceptions
        $busRide = new Ride([
            'transport_type' => Ride::TRANSPORT_BUS,
            'travel_mode' => Ride::MODE_SCHEDULED,
            'route_id' => $this->createRouteId(),
        ]);
        $busRide->validateTransportRules(); // Should not throw

        $carScheduledRide = new Ride([
            'transport_type' => Ride::TRANSPORT_CAR,
            'travel_mode' => Ride::MODE_SCHEDULED,
        ]);
        $carScheduledRide->validateTransportRules(); // Should not throw

        $carOnDemandRide = new Ride([
            'transport_type' => Ride::TRANSPORT_CAR,
            'travel_mode' => Ride::MODE_ON_DEMAND,
        ]);
        $carOnDemandRide->validateTransportRules(); // Should not throw

        $motorcycleRide = new Ride([
            'transport_type' => Ride::TRANSPORT_MOTORCYCLE,
            'travel_mode' => Ride::MODE_ON_DEMAND,
        ]);
        $motorcycleRide->validateTransportRules(); // Should not throw

        // Invalid combinations should throw exceptions
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('BUS must be SCHEDULED');

        $invalidBusRide = new Ride([
            'transport_type' => Ride::TRANSPORT_BUS,
            'travel_mode' => Ride::MODE_ON_DEMAND,
            'route_id' => $this->createRouteId(),
        ]);
        $invalidBusRide->validateTransportRules();
    }

    private function createRouteId(): int
    {
        $zoneId = \Illuminate\Support\Facades\DB::table('zones')->insertGetId([
            'name' => 'Route Zone '.uniqid(),
            'code' => 'RZ'.substr(uniqid(), -6),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $corridor = \App\Models\Corridor::query()->create([
            'code' => 'C'.substr(uniqid(), -6),
            'name' => 'Route Corridor',
            'kinyarwanda_name' => 'Route Corridor',
            'start_zone_id' => $zoneId,
            'end_zone_id' => $zoneId,
            'base_fare' => 100,
            'price_per_km' => 0,
        ]);

        return \App\Models\TransportRoute::query()->create([
            'corridor_id' => $corridor->id,
            'route_code' => 'R'.substr(uniqid(), -6),
            'name' => 'Test Route',
            'origin' => 'Origin',
            'destination' => 'Destination',
            'is_active' => true,
        ])->id;
    }
}
