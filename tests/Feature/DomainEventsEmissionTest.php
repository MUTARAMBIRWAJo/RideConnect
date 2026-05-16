<?php

namespace Tests\Feature;

use App\Events\Domain\BookingCreated;
use App\Events\Domain\RideCreated;
use App\Events\Domain\TripCompleted;
use App\Events\Domain\TripMatched;
use App\Events\Domain\TripStarted;
use App\Models\Ride;
use App\Models\Trip;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class DomainEventsEmissionTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function booking_and_trip_events_are_emitted(): void
    {
        Event::fake([
            BookingCreated::class,
            TripMatched::class,
            TripStarted::class,
            TripCompleted::class,
        ]);

        $driverUser = User::factory()->create(['is_approved' => true, 'role' => \App\Enums\UserRole::DRIVER]);
        $driver = \App\Models\Driver::factory()->create(['user_id' => $driverUser->id, 'availability_status' => 'online']);
        $vehicle = \App\Models\Vehicle::factory()->create(['driver_id' => $driver->id, 'vehicle_type' => 'sedan']);

        $passenger = User::factory()->create(['is_approved' => true]);

        $ride = Ride::factory()->create([
            'driver_id' => $driver->id,
            'vehicle_id' => $vehicle->id,
            'travel_mode' => Ride::MODE_SCHEDULED,
            'transport_type' => Ride::TRANSPORT_CAR,
            'status' => 'scheduled',
            'available_seats' => 2,
            'departure_time' => now()->addHours(2),
        ]);

        $this->actingAs($passenger)->postJson('/api/v1/passenger/bookings', [
            'ride_id' => $ride->id,
            'seats_booked' => 1,
            'pickup_address' => 'A',
            'pickup_lat' => -1.9,
            'pickup_lng' => 30.0,
            'dropoff_address' => 'B',
            'dropoff_lat' => -1.9,
            'dropoff_lng' => 30.0,
        ])->assertStatus(201);

        Event::assertDispatched(BookingCreated::class);

        $mobileUser = \App\Models\MobileUser::factory()->create(['email' => $passenger->email]);
        $passenger->update(['mobile_user_id' => $mobileUser->id]);

        $trip = Trip::factory()->create([
            'passenger_id' => $mobileUser->id,
            'ride_id' => $ride->id,
            'driver_id' => null,
            'status' => 'PENDING',
        ]);

        Route::put('/test/trips/{id}/accept', [\App\Http\Controllers\Api\TripController::class, 'accept']);
        Route::put('/test/trips/{id}/start', [\App\Http\Controllers\Api\TripController::class, 'start']);
        Route::put('/test/trips/{id}/complete', [\App\Http\Controllers\Api\TripController::class, 'complete']);

        $this->actingAs($driverUser)->putJson('/test/trips/'.$trip->id.'/accept')->assertStatus(200);
        $this->actingAs($driverUser)->putJson('/test/trips/'.$trip->id.'/start')->assertStatus(200);
        $this->actingAs($driverUser)->putJson('/test/trips/'.$trip->id.'/complete')->assertStatus(200);

        Event::assertDispatched(TripMatched::class);
        Event::assertDispatched(TripStarted::class);
        Event::assertDispatched(TripCompleted::class);
    }

    /** @test */
    public function ride_created_event_is_emitted(): void
    {
        Event::fake([RideCreated::class]);

        $admin = User::factory()->create(['is_approved' => true, 'role' => \App\Enums\UserRole::SUPER_ADMIN]);
        $driver = \App\Models\Driver::factory()->create();
        $vehicle = \App\Models\Vehicle::factory()->create(['driver_id' => $driver->id]);
        $zoneId = DB::table('zones')->insertGetId([
            'name' => 'Kigali Central',
            'code' => 'KGL-C',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $corridorId = DB::table('corridors')->insertGetId([
            'name' => 'Central Corridor',
            'start_zone_id' => $zoneId,
            'end_zone_id' => $zoneId,
            'base_fare' => 1000,
            'price_per_km' => 200,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($admin)->postJson('/api/v1/admin/rides/corridor', [
            'driver_id' => $driver->id,
            'vehicle_id' => $vehicle->id,
            'zone_id' => $zoneId,
            'corridor_id' => $corridorId,
            'origin_lat' => -1.95,
            'origin_lng' => 30.06,
            'destination_lat' => -1.94,
            'destination_lng' => 30.07,
            'departure_time' => now()->addDay()->toDateTimeString(),
            'available_seats' => 3,
        ]);

        $response->assertStatus(201);

        Event::assertDispatched(RideCreated::class);
    }
}
