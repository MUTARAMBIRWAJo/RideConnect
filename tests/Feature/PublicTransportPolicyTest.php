<?php

namespace Tests\Feature;

use App\Domain\Ride\RidePolicy;
use App\Enums\UserRole;
use App\Models\Booking;
use App\Models\Corridor;
use App\Models\Ride;
use App\Models\TransportRoute;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PublicTransportPolicyTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function test_bus_ride_must_have_route(): void
    {
        $driver = \App\Models\Driver::factory()->create();
        $vehicle = \App\Models\Vehicle::factory()->create(['driver_id' => $driver->id, 'vehicle_type' => 'van']);

        $ride = Ride::factory()->make([
            'driver_id' => $driver->id,
            'vehicle_id' => $vehicle->id,
            'transport_type' => Ride::TRANSPORT_BUS,
            'travel_mode' => Ride::MODE_SCHEDULED,
            'route_id' => null,
        ]);

        $this->expectException(\App\Exceptions\DomainException::class);

        RidePolicy::assertBusRules($ride);
    }

    /** @test */
    public function test_cannot_create_bus_without_route(): void
    {
        $driver = \App\Models\Driver::factory()->create();
        $vehicle = \App\Models\Vehicle::factory()->create(['driver_id' => $driver->id, 'vehicle_type' => 'van']);

        $this->expectException(\InvalidArgumentException::class);

        Ride::factory()->create([
            'driver_id' => $driver->id,
            'vehicle_id' => $vehicle->id,
            'transport_type' => Ride::TRANSPORT_BUS,
            'travel_mode' => Ride::MODE_SCHEDULED,
            'route_id' => null,
        ]);
    }

    /** @test */
    public function test_passenger_can_book_bus_route(): void
    {
        $zoneId = DB::table('zones')->insertGetId([
            'name' => 'Test Zone',
            'code' => 'TST',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $corridor = Corridor::query()->create([
            'code' => 'A',
            'name' => 'Corridor A',
            'kinyarwanda_name' => 'Koridoro A',
            'start_zone_id' => $zoneId,
            'end_zone_id' => $zoneId,
            'base_fare' => 100,
            'price_per_km' => 0,
        ]);

        $route = TransportRoute::query()->create([
            'corridor_id' => $corridor->id,
            'route_code' => '102',
            'name' => 'KABUGA -> NYABUGOGO via SONATUBE',
            'via' => 'SONATUBE',
            'origin' => 'KABUGA',
            'destination' => 'NYABUGOGO',
            'is_active' => true,
        ]);

        $driver = \App\Models\Driver::factory()->create();
        $vehicle = \App\Models\Vehicle::factory()->create(['driver_id' => $driver->id, 'vehicle_type' => 'van']);

        $ride = Ride::factory()->create([
            'driver_id' => $driver->id,
            'vehicle_id' => $vehicle->id,
            'transport_type' => Ride::TRANSPORT_BUS,
            'travel_mode' => Ride::MODE_SCHEDULED,
            'corridor_id' => $corridor->id,
            'route_id' => $route->id,
            'bus_number' => $route->route_code,
            'origin_address' => $route->origin,
            'destination_address' => $route->destination,
            'available_seats' => 10,
            'status' => 'PUBLISHED',
            'departure_time' => now()->addHours(8),
        ]);

        $passenger = User::factory()->create([
            'is_approved' => true,
            'role' => UserRole::PASSENGER,
        ]);

        $response = $this->actingAs($passenger)->postJson('/api/v1/passenger/bookings', [
            'ride_id' => $ride->id,
            'seats_booked' => 1,
            'pickup_address' => 'KABUGA',
            'pickup_lat' => -1.95,
            'pickup_lng' => 30.06,
            'dropoff_address' => 'NYABUGOGO',
            'dropoff_lat' => -1.94,
            'dropoff_lng' => 30.05,
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('data.ride_id', $ride->id);

        $this->assertDatabaseHas('bookings', [
            'ride_id' => $ride->id,
            'user_id' => $passenger->id,
            'status' => 'PENDING',
        ]);

        $rideResponse = $this->actingAs($passenger)->getJson('/api/v1/passenger/rides/' . $ride->id);
        $rideResponse->assertOk();
        $rideResponse->assertJsonPath('data.transport_type', 'BUS');
        $rideResponse->assertJsonPath('data.bus_number', '102');
        $rideResponse->assertJsonPath('data.route.code', '102');
        $rideResponse->assertJsonPath('data.corridor.code', 'A');
        $rideResponse->assertJsonPath('data.ride_rules.allowed_flow', 'BOOKING_ONLY');
    }

    /** @test */
    public function test_bus_trip_created_from_booking_only(): void
    {
        $zoneId = DB::table('zones')->insertGetId([
            'name' => 'Test Zone',
            'code' => 'TST2',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $corridor = Corridor::query()->create([
            'code' => 'B',
            'name' => 'Corridor B',
            'kinyarwanda_name' => 'Koridoro B',
            'start_zone_id' => $zoneId,
            'end_zone_id' => $zoneId,
            'base_fare' => 100,
            'price_per_km' => 0,
        ]);

        $route = TransportRoute::query()->create([
            'corridor_id' => $corridor->id,
            'route_code' => '217',
            'name' => 'KABUGA -> KACYIRU',
            'origin' => 'KABUGA',
            'destination' => 'KACYIRU',
            'is_active' => true,
        ]);

        $driver = \App\Models\Driver::factory()->create();
        $vehicle = \App\Models\Vehicle::factory()->create(['driver_id' => $driver->id, 'vehicle_type' => 'van']);

        $ride = Ride::factory()->create([
            'driver_id' => $driver->id,
            'vehicle_id' => $vehicle->id,
            'transport_type' => Ride::TRANSPORT_BUS,
            'travel_mode' => Ride::MODE_SCHEDULED,
            'corridor_id' => $corridor->id,
            'route_id' => $route->id,
            'bus_number' => $route->route_code,
            'origin_address' => $route->origin,
            'destination_address' => $route->destination,
            'available_seats' => 10,
            'status' => 'PUBLISHED',
            'departure_time' => now()->addHours(8),
        ]);

        $passenger = User::factory()->create([
            'is_approved' => true,
            'role' => UserRole::PASSENGER,
        ]);

        $bookingResponse = $this->actingAs($passenger)->postJson('/api/v1/passenger/bookings', [
            'ride_id' => $ride->id,
            'seats_booked' => 1,
            'pickup_address' => 'KABUGA',
            'pickup_lat' => -1.95,
            'pickup_lng' => 30.06,
            'dropoff_address' => 'KACYIRU',
            'dropoff_lat' => -1.94,
            'dropoff_lng' => 30.05,
        ]);

        $bookingResponse->assertStatus(201);

        $booking = Booking::query()->where('ride_id', $ride->id)->latest('id')->firstOrFail();

        $directTripResponse = $this->actingAs($passenger)->postJson('/api/v1/passenger/trips', [
            'ride_id' => $ride->id,
            'pickup_location' => 'KABUGA',
            'pickup_lat' => -1.95,
            'pickup_lng' => 30.06,
            'dropoff_location' => 'KACYIRU',
            'dropoff_lat' => -1.94,
            'dropoff_lng' => 30.05,
            'fare' => 1000,
        ]);

        $directTripResponse->assertStatus(422);
        $directTripResponse->assertJsonFragment([
            'message' => 'BUS trips must be created from a booking',
        ]);

        $admin = User::factory()->create([
            'is_approved' => true,
            'role' => UserRole::SUPER_ADMIN,
        ]);

        $tripResponse = $this->actingAs($admin)->postJson('/api/v1/passenger/trips/create-from-booking', [
            'booking_id' => $booking->id,
        ]);

        $tripResponse->assertStatus(201);
        $tripResponse->assertJsonPath('data.booking_id', $booking->id);
    }

    /** @test */
    public function test_admin_can_update_routes_dynamically(): void
    {
        $zoneId = DB::table('zones')->insertGetId([
            'name' => 'Test Zone',
            'code' => 'TST3',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $corridor = Corridor::query()->create([
            'code' => 'C',
            'name' => 'Corridor C',
            'kinyarwanda_name' => 'Koridoro C',
            'start_zone_id' => $zoneId,
            'end_zone_id' => $zoneId,
            'base_fare' => 100,
            'price_per_km' => 0,
        ]);

        $route = TransportRoute::query()->create([
            'corridor_id' => $corridor->id,
            'route_code' => '301',
            'name' => 'Old Route Name',
            'origin' => 'START',
            'destination' => 'END',
            'is_active' => true,
        ]);

        $admin = User::factory()->create([
            'is_approved' => true,
            'role' => UserRole::SUPER_ADMIN,
        ]);

        TransportRoute::query()->whereKey($route->id)->update([
            'name' => 'Updated Route Name',
            'via' => 'NEW VIA',
            'is_active' => false,
        ]);

        $response = $this->actingAs($admin)->getJson('/api/v1/passenger/public-transport/routes?corridor_id=' . $corridor->id);
        $response->assertOk();
        $response->assertJsonCount(0, 'data');

        $corridor->refresh();
        $this->assertSame('C', $corridor->code);
        $this->assertSame('Corridor C', $corridor->name);
    }
}