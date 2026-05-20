<?php

namespace Tests\Feature;

use App\Models\Driver;
use App\Models\MobileUser;
use App\Models\Notification;
use App\Models\Trip;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DriverSelectionFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_moto_matching_returns_driver_cards_without_seats(): void
    {
        $passenger = $this->passengerUser();
        $driver = $this->driverWithVehicle('motorcycle', 1);

        DB::table('driver_locations')->insert([
            'driver_id' => $driver->id,
            'latitude' => -1.9500,
            'longitude' => 30.0900,
            'is_online' => true,
            'last_activity_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($passenger, 'sanctum')->getJson('/api/v1/passenger/drivers/match?'.http_build_query([
            'transport_type' => 'motor_vehicle',
            'pickup_lat' => -1.9510,
            'pickup_lng' => 30.0910,
            'dropoff_lat' => -1.9700,
            'dropoff_lng' => 30.1100,
        ]));

        $response->assertOk()
            ->assertJsonPath('data.transport_type', 'motor_vehicle')
            ->assertJsonMissingPath('data.drivers.0.available_seats')
            ->assertJsonStructure([
                'data' => [
                    'drivers' => [[
                        'driver_id',
                        'driver_name',
                        'profile_photo_url',
                        'rating',
                        'behavior_score',
                        'estimated_arrival_minutes',
                        'estimated_fare',
                        'distance_km',
                        'online_status',
                        'current_location' => ['latitude', 'longitude'],
                        'vehicle' => ['vehicle_type', 'plate_number', 'color'],
                    ]],
                ],
            ]);
    }

    public function test_private_car_matching_returns_capacity_from_vehicle(): void
    {
        $passenger = $this->passengerUser();
        $driver = $this->driverWithVehicle('suv', 6);

        DB::table('driver_locations')->insert([
            'driver_id' => $driver->id,
            'latitude' => -1.9500,
            'longitude' => 30.0900,
            'is_online' => true,
            'last_activity_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($passenger, 'sanctum')->getJson('/api/v1/mobile/drivers/match?'.http_build_query([
            'transport_type' => 'private_car',
            'pickup_lat' => -1.9510,
            'pickup_lng' => 30.0910,
            'dropoff_lat' => -1.9700,
            'dropoff_lng' => 30.1100,
        ]));

        $response->assertOk()
            ->assertJsonPath('data.transport_type', 'private_car')
            ->assertJsonPath('data.drivers.0.available_seats', 6)
            ->assertJsonStructure([
                'data' => [
                    'drivers' => [[
                        'driver_id',
                        'available_seats',
                        'comfort_tags',
                        'vehicle' => ['vehicle_type', 'plate_number', 'color'],
                    ]],
                ],
            ]);
    }

    public function test_passenger_can_request_selected_moto_driver_and_driver_can_accept(): void
    {
        $passenger = $this->passengerUser();
        $driver = $this->driverWithVehicle('motorcycle', 1);
        $driverUser = $driver->user;

        $requestResponse = $this->actingAs($passenger, 'sanctum')->postJson('/api/v1/mobile/trips/request', [
            'driver_id' => $driver->id,
            'transport_type' => 'motor_vehicle',
            'pickup_location' => 'Kacyiru',
            'pickup_lat' => -1.9510,
            'pickup_lng' => 30.0910,
            'dropoff_location' => 'Kimironko',
            'dropoff_lat' => -1.9700,
            'dropoff_lng' => 30.1100,
        ]);

        $requestResponse->assertCreated()
            ->assertJsonPath('data.driver_id', $driver->id)
            ->assertJsonPath('data.driver_action_required', true);

        $tripId = $requestResponse->json('data.id');
        $this->assertDatabaseHas('user_notifications', [
            'user_id' => $driverUser->id,
            'type' => 'ride_request_received',
        ]);

        $acceptResponse = $this->actingAs($driverUser, 'sanctum')
            ->postJson("/api/v1/mobile/drivers/trips/{$tripId}/accept");

        $acceptResponse->assertOk()
            ->assertJsonPath('data.trip_state', 'ACCEPTED')
            ->assertJsonPath('data.driver_acknowledgement', 'After accepting, you cannot reject or cancel when 15 minutes or less remain before pickup.');

        $this->assertDatabaseHas('trips', [
            'id' => $tripId,
            'driver_id' => $driver->id,
            'status' => 'ACCEPTED',
        ]);
    }

    public function test_mobile_private_car_booking_can_use_selected_driver_without_ride_id(): void
    {
        $passenger = $this->passengerUser();
        $driver = $this->driverWithVehicle('sedan', 4);

        $response = $this->actingAs($passenger, 'sanctum')->postJson('/api/v1/mobile/bookings', [
            'driver_id' => $driver->id,
            'transport_type' => 'private_car',
            'seats' => 2,
            'pickup_location' => 'Kacyiru',
            'pickup_lat' => -1.9510,
            'pickup_lng' => 30.0910,
            'dropoff_location' => 'Kimironko',
            'dropoff_lat' => -1.9700,
            'dropoff_lng' => 30.1100,
            'schedule_time' => now()->addHour()->toIso8601String(),
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.status', 'PENDING');

        $this->assertDatabaseHas('rides', [
            'driver_id' => $driver->id,
            'transport_type' => 'CAR',
            'travel_mode' => 'SCHEDULED',
            'available_seats' => 4,
        ]);
        $this->assertDatabaseHas('bookings', [
            'user_id' => $passenger->id,
            'seats_booked' => 2,
            'status' => 'pending',
        ]);
    }

    private function passengerUser(): User
    {
        $mobileUser = MobileUser::factory()->create([
            'role' => 'PASSENGER',
            'is_verified' => true,
        ]);

        return User::factory()->create([
            'role' => 'PASSENGER',
            'mobile_user_id' => $mobileUser->id,
            'is_approved' => true,
        ]);
    }

    private function driverWithVehicle(string $vehicleType, int $seats): Driver
    {
        $mobileUser = MobileUser::factory()->create([
            'role' => 'DRIVER',
            'is_verified' => true,
        ]);
        $user = User::factory()->create([
            'role' => 'DRIVER',
            'mobile_user_id' => $mobileUser->id,
            'is_approved' => true,
        ]);
        $driver = Driver::factory()->create([
            'user_id' => $user->id,
            'status' => 'approved',
            'availability_status' => 'online',
            'rating' => 4.8,
        ]);

        Vehicle::factory()->create([
            'driver_id' => $driver->id,
            'vehicle_type' => $vehicleType,
            'seats' => $seats,
            'is_active' => true,
        ]);

        return $driver->fresh(['user', 'vehicles']);
    }
}
