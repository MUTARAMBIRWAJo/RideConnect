<?php

namespace Tests\Feature\Api;

use App\Enums\UserRole;
use App\Models\Booking;
use App\Models\Driver;
use App\Models\MobileUser;
use App\Models\Ride;
use App\Models\Trip;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RideCategoryPromotionTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_as_booking_when_departure_is_greater_than_six_hours(): void
    {
        [$passenger] = $this->createPassengerUser();
        [, , $ride] = $this->createDriverStack(now()->addHours(8));

        Sanctum::actingAs($passenger, ['*']);

        $response = $this->postJson('/api/v1/passenger/rides', [
            'ride_id' => $ride->id,
            'seats' => 1,
            'pickup_address' => 'Kigali Heights',
            'dropoff_address' => 'Kimironko Market',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.travel_type', 'BOOKING');

        $this->assertDatabaseHas('bookings', [
            'ride_id' => $ride->id,
            'user_id' => $passenger->id,
        ]);

        $this->assertSame(0, Trip::count());
    }

    public function test_create_as_trip_when_departure_is_less_or_equal_to_six_hours(): void
    {
        [$passenger, $mobilePassenger] = $this->createPassengerUser();
        [, $driver, $ride] = $this->createDriverStack(now()->addHours(5));

        Sanctum::actingAs($passenger, ['*']);

        $response = $this->postJson('/api/v1/passenger/rides', [
            'ride_id' => $ride->id,
            'seats' => 1,
            'pickup_address' => 'Kigali Heights',
            'dropoff_address' => 'Kimironko Market',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.travel_type', 'TRIP');

        $this->assertSame(0, Booking::count());

        $this->assertDatabaseHas('trips', [
            'passenger_id' => $mobilePassenger->id,
            'driver_id' => $driver->id,
            'status' => 'PENDING',
        ]);
    }

    public function test_convert_existing_booking_to_trip_when_threshold_is_crossed(): void
    {
        [$passenger, $mobilePassenger] = $this->createPassengerUser();
        [$driverUser, $driver, $ride] = $this->createDriverStack(now()->addHours(8));

        $booking = Booking::create([
            'user_id' => $passenger->id,
            'ride_id' => $ride->id,
            'seats_booked' => 1,
            'total_price' => 3500,
            'currency' => 'RWF',
            'status' => 'PENDING',
            'pickup_address' => 'Kigali Heights',
            'pickup_lat' => -1.9536,
            'pickup_lng' => 30.0606,
            'dropoff_address' => 'Kimironko Market',
            'dropoff_lat' => -1.9411,
            'dropoff_lng' => 30.1098,
        ]);

        Sanctum::actingAs($driverUser, ['*']);

        $response = $this->putJson('/api/v1/driver/rides/' . $ride->id, [
            'departure_time' => now()->addHours(5)->toIso8601String(),
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertDatabaseMissing('bookings', [
            'id' => $booking->id,
        ]);

        $this->assertDatabaseHas('trips', [
            'passenger_id' => $mobilePassenger->id,
            'driver_id' => $driver->id,
            'pickup_location' => 'Kigali Heights',
            'dropoff_location' => 'Kimironko Market',
            'fare' => 3500,
        ]);
    }

    /**
     * @return array{User, MobileUser}
     */
    private function createPassengerUser(): array
    {
        $mobilePassenger = MobileUser::create([
            'first_name' => 'Passenger',
            'last_name' => 'Tester',
            'phone' => '+250780000001',
            'email' => 'passenger.tester@example.com',
            'password' => 'password123',
            'role' => UserRole::PASSENGER->value,
            'is_verified' => true,
        ]);

        $user = User::factory()->create([
            'name' => 'Passenger Tester',
            'email' => 'passenger.user@example.com',
            'role' => UserRole::PASSENGER->value,
            'is_approved' => true,
            'mobile_user_id' => $mobilePassenger->id,
        ]);

        return [$user, $mobilePassenger];
    }

    /**
     * @return array{User, Driver, Ride}
     */
    private function createDriverStack($departureTime): array
    {
        $driverUser = User::factory()->create([
            'name' => 'Driver Tester',
            'email' => 'driver.user@example.com',
            'role' => UserRole::DRIVER->value,
            'is_approved' => true,
        ]);

        $driver = Driver::create([
            'user_id' => $driverUser->id,
            'license_number' => 'DL-TEST-0001',
            'license_plate' => 'RAC-123-A',
            'status' => 'approved',
            'total_rides' => 0,
            'rating' => 0,
            'rating_count' => 0,
            'balance' => 0,
            'approved_at' => now(),
        ]);

        $vehicle = Vehicle::create([
            'driver_id' => $driver->id,
            'make' => 'Toyota',
            'model' => 'Corolla',
            'year' => 2020,
            'color' => 'White',
            'vehicle_type' => 'sedan',
            'seats' => 4,
            'air_conditioning' => true,
            'is_active' => true,
        ]);

        $ride = Ride::create([
            'driver_id' => $driver->id,
            'vehicle_id' => $vehicle->id,
            'origin_address' => 'Kigali Heights',
            'origin_lat' => -1.9536,
            'origin_lng' => 30.0606,
            'destination_address' => 'Kimironko Market',
            'destination_lat' => -1.9411,
            'destination_lng' => 30.1098,
            'departure_time' => $departureTime,
            'available_seats' => 4,
            'price_per_seat' => 3500,
            'currency' => 'RWF',
            'status' => 'ACTIVE',
            'ride_type' => 'REGULAR',
            'luggage_allowed' => true,
            'pets_allowed' => false,
            'smoking_allowed' => false,
        ]);

        return [$driverUser, $driver, $ride];
    }
}
