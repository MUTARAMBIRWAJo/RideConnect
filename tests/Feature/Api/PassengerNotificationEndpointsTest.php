<?php

namespace Tests\Feature\Api;

use App\Enums\UserRole;
use App\Models\Driver;
use App\Models\MobileUser;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PassengerNotificationEndpointsTest extends TestCase
{
    use RefreshDatabase;

    public function test_passenger_can_list_only_online_approved_drivers(): void
    {
        $passenger = User::factory()->create([
            'role' => UserRole::PASSENGER->value,
            'is_approved' => true,
        ]);

        $onlineDriverUser = User::factory()->create([
            'role' => UserRole::DRIVER->value,
            'is_approved' => true,
        ]);
        $onlineDriver = Driver::create([
            'user_id' => $onlineDriverUser->id,
            'license_number' => 'DL-PN-1001',
            'license_plate' => 'RAA-111-A',
            'status' => 'approved',
            'availability_status' => 'online',
            'current_latitude' => -1.9441,
            'current_longitude' => 30.0619,
            'total_rides' => 20,
            'rating' => 4.7,
            'rating_count' => 15,
        ]);

        $offlineDriverUser = User::factory()->create([
            'role' => UserRole::DRIVER->value,
            'is_approved' => true,
        ]);
        Driver::create([
            'user_id' => $offlineDriverUser->id,
            'license_number' => 'DL-PN-1002',
            'license_plate' => 'RAA-112-A',
            'status' => 'approved',
            'availability_status' => 'offline',
            'total_rides' => 10,
            'rating' => 4.2,
            'rating_count' => 9,
        ]);

        Sanctum::actingAs($passenger, ['*']);

        $response = $this->getJson('/api/v1/passenger/drivers/online');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $onlineDriver->id);
    }

    public function test_passenger_ride_request_creates_trip_and_driver_notification(): void
    {
        $mobilePassenger = MobileUser::create([
            'first_name' => 'Passenger',
            'last_name' => 'One',
            'phone' => '+250780123456',
            'email' => 'passenger.one@example.com',
            'password' => 'password123',
            'role' => UserRole::PASSENGER->value,
            'is_verified' => true,
        ]);

        $passenger = User::factory()->create([
            'role' => UserRole::PASSENGER->value,
            'is_approved' => true,
            'mobile_user_id' => $mobilePassenger->id,
        ]);

        $driverUser = User::factory()->create([
            'role' => UserRole::DRIVER->value,
            'is_approved' => true,
        ]);

        $driver = Driver::create([
            'user_id' => $driverUser->id,
            'license_number' => 'DL-PN-2001',
            'license_plate' => 'RAA-211-A',
            'status' => 'approved',
            'availability_status' => 'online',
            'current_latitude' => -1.9441,
            'current_longitude' => 30.0619,
            'total_rides' => 12,
            'rating' => 4.5,
            'rating_count' => 12,
        ]);

        Sanctum::actingAs($passenger, ['*']);

        $response = $this->postJson('/api/v1/passenger/ride-requests', [
            'driver_id' => $driver->id,
            'pickup_location' => 'Kigali Heights',
            'pickup_lat' => -1.9536,
            'pickup_lng' => 30.0606,
            'dropoff_location' => 'Kimironko',
            'dropoff_lat' => -1.9411,
            'dropoff_lng' => 30.1098,
            'fare' => 4500,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'PENDING');

        $tripId = (int) $response->json('data.trip_id');

        $this->assertDatabaseHas('trips', [
            'id' => $tripId,
            'passenger_id' => $mobilePassenger->id,
            'driver_id' => $driver->id,
            'status' => 'PENDING',
        ]);

        $this->assertDatabaseHas('user_notifications', [
            'user_id' => $driverUser->id,
            'type' => 'ride_request_received',
        ]);
    }

    public function test_notification_endpoints_read_lifecycle(): void
    {
        $user = User::factory()->create([
            'role' => UserRole::PASSENGER->value,
            'is_approved' => true,
        ]);

        $notification = Notification::create([
            'user_id' => $user->id,
            'type' => 'ride_request_accepted',
            'title' => 'Ride Accepted',
            'message' => 'Your ride request was accepted.',
            'data' => ['trip_id' => 123],
            'is_read' => false,
        ]);

        Sanctum::actingAs($user, ['*']);

        $this->getJson('/api/v1/notifications/unread-count')
            ->assertStatus(200)
            ->assertJsonPath('data.unread_count', 1);

        $this->getJson('/api/v1/notifications')
            ->assertStatus(200)
            ->assertJsonPath('data.0.id', $notification->id);

        $this->putJson('/api/v1/notifications/' . $notification->id . '/read')
            ->assertStatus(200);

        $this->assertDatabaseHas('user_notifications', [
            'id' => $notification->id,
            'is_read' => true,
        ]);
    }

    public function test_user_can_register_and_remove_push_token(): void
    {
        $user = User::factory()->create([
            'role' => UserRole::PASSENGER->value,
            'is_approved' => true,
        ]);

        Sanctum::actingAs($user, ['*']);

        $this->postJson('/api/v1/devices/push-token', [
            'platform' => 'fcm',
            'device_token' => 'fcm-token-abc-123',
            'device_id' => 'pixel-8-device',
        ])->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('mobile_device_tokens', [
            'user_id' => $user->id,
            'platform' => 'fcm',
            'device_token' => 'fcm-token-abc-123',
        ]);

        $this->deleteJson('/api/v1/devices/push-token/fcm-token-abc-123')
            ->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertDatabaseMissing('mobile_device_tokens', [
            'user_id' => $user->id,
            'device_token' => 'fcm-token-abc-123',
        ]);
    }
}
