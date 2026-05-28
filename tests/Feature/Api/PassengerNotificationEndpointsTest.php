<?php

namespace Tests\Feature\Api;

use App\Enums\UserRole;
use App\Jobs\DeliverPushNotificationJob;
use App\Models\Driver;
use App\Models\MobileUser;
use App\Models\Notification;
use App\Models\NotificationDelivery;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PassengerNotificationEndpointsTest extends TestCase
{
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
        $suffix = Str::lower(Str::random(8));

        $mobilePassenger = MobileUser::create([
            'first_name' => 'Passenger',
            'last_name' => 'One',
            'phone' => '+25078'.random_int(1000000, 9999999),
            'email' => "passenger.one.{$suffix}@example.com",
            'password' => 'password123',
            'role' => UserRole::PASSENGER->value,
            'is_verified' => true,
        ]);

        $passenger = User::factory()->create([
            'email' => "passenger.user.{$suffix}@example.com",
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
            'license_number' => 'DL-PN-'.random_int(2000, 9999),
            'license_plate' => 'RAA-'.random_int(200, 999).'-'.chr(random_int(65, 90)),
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

        $this->putJson('/api/v1/notifications/'.$notification->id.'/read')
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

    public function test_clear_actioned_notifications_keeps_non_actioned_notifications(): void
    {
        $user = User::factory()->create([
            'role' => UserRole::PASSENGER->value,
            'is_approved' => true,
        ]);

        $accepted = Notification::create([
            'user_id' => $user->id,
            'type' => 'ride_request_accepted',
            'title' => 'Accepted',
            'message' => 'Your request was accepted.',
            'data' => ['trip_id' => 1001, 'status' => 'ACCEPTED'],
            'is_read' => false,
        ]);

        $cancelled = Notification::create([
            'user_id' => $user->id,
            'type' => 'trip_cancelled',
            'title' => 'Cancelled',
            'message' => 'Your trip was cancelled.',
            'data' => ['trip_id' => 1002, 'status' => 'CANCELLED'],
            'is_read' => false,
        ]);

        $pending = Notification::create([
            'user_id' => $user->id,
            'type' => 'ride_request_received',
            'title' => 'Pending Action',
            'message' => 'Please accept or reject.',
            'data' => ['trip_id' => 1003],
            'is_read' => false,
        ]);

        Sanctum::actingAs($user, ['*']);

        $this->deleteJson('/api/v1/notifications/clear-actioned')
            ->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.deleted_count', 2)
            ->assertJsonPath('data.kept_count', 1);

        $this->assertDatabaseMissing('user_notifications', ['id' => $accepted->id]);
        $this->assertDatabaseMissing('user_notifications', ['id' => $cancelled->id]);
        $this->assertDatabaseHas('user_notifications', ['id' => $pending->id]);
    }

    public function test_cannot_delete_non_actioned_notification_but_can_delete_actioned(): void
    {
        $user = User::factory()->create([
            'role' => UserRole::PASSENGER->value,
            'is_approved' => true,
        ]);

        $pending = Notification::create([
            'user_id' => $user->id,
            'type' => 'ride_request_received',
            'title' => 'Pending Action',
            'message' => 'Please accept or reject.',
            'data' => ['trip_id' => 3001],
            'is_read' => false,
        ]);

        $actioned = Notification::create([
            'user_id' => $user->id,
            'type' => 'ride_request_accepted',
            'title' => 'Accepted',
            'message' => 'Driver accepted.',
            'data' => ['trip_id' => 3002, 'status' => 'ACCEPTED'],
            'is_read' => false,
        ]);

        Sanctum::actingAs($user, ['*']);

        $this->deleteJson('/api/v1/notifications/'.$pending->id)
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error_code', 'notification_not_actioned');

        $this->assertDatabaseHas('user_notifications', ['id' => $pending->id]);

        $this->deleteJson('/api/v1/notifications/'.$actioned->id)
            ->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertDatabaseMissing('user_notifications', ['id' => $actioned->id]);
    }

    public function test_notifications_list_exposes_can_be_cleared_flag(): void
    {
        $user = User::factory()->create([
            'role' => UserRole::PASSENGER->value,
            'is_approved' => true,
        ]);

        $pending = Notification::create([
            'user_id' => $user->id,
            'type' => 'ride_request_received',
            'title' => 'Pending Action',
            'message' => 'Please accept or reject.',
            'data' => ['trip_id' => 8001],
            'is_read' => false,
            'created_at' => now()->subMinute(),
            'updated_at' => now()->subMinute(),
        ]);

        $actioned = Notification::create([
            'user_id' => $user->id,
            'type' => 'trip_cancelled',
            'title' => 'Cancelled',
            'message' => 'Trip was cancelled.',
            'data' => ['trip_id' => 8002, 'status' => 'CANCELLED'],
            'is_read' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Sanctum::actingAs($user, ['*']);

        $response = $this->getJson('/api/v1/notifications');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.0.id', $actioned->id)
            ->assertJsonPath('data.0.can_be_cleared', true)
            ->assertJsonPath('data.1.id', $pending->id)
            ->assertJsonPath('data.1.can_be_cleared', false);
    }

    public function test_notifications_can_filter_only_clearable(): void
    {
        $user = User::factory()->create([
            'role' => UserRole::PASSENGER->value,
            'is_approved' => true,
        ]);

        $actioned = Notification::create([
            'user_id' => $user->id,
            'type' => 'trip_cancelled',
            'title' => 'Cancelled',
            'message' => 'Trip was cancelled.',
            'data' => ['trip_id' => 9001, 'status' => 'CANCELLED'],
            'is_read' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Notification::create([
            'user_id' => $user->id,
            'type' => 'ride_request_received',
            'title' => 'Pending Action',
            'message' => 'Please accept or reject.',
            'data' => ['trip_id' => 9002],
            'is_read' => false,
            'created_at' => now()->subMinute(),
            'updated_at' => now()->subMinute(),
        ]);

        Sanctum::actingAs($user, ['*']);

        $response = $this->getJson('/api/v1/notifications?only_clearable=true');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('pagination.total', 1)
            ->assertJsonPath('data.0.id', $actioned->id)
            ->assertJsonPath('data.0.can_be_cleared', true);
    }

    public function test_notifications_can_filter_only_action_required(): void
    {
        $user = User::factory()->create([
            'role' => UserRole::PASSENGER->value,
            'is_approved' => true,
        ]);

        Notification::create([
            'user_id' => $user->id,
            'type' => 'trip_cancelled',
            'title' => 'Cancelled',
            'message' => 'Trip was cancelled.',
            'data' => ['trip_id' => 9101, 'status' => 'CANCELLED'],
            'is_read' => false,
            'created_at' => now()->subMinute(),
            'updated_at' => now()->subMinute(),
        ]);

        $pending = Notification::create([
            'user_id' => $user->id,
            'type' => 'ride_request_received',
            'title' => 'Pending Action',
            'message' => 'Please accept or reject.',
            'data' => ['trip_id' => 9102],
            'is_read' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Sanctum::actingAs($user, ['*']);

        $response = $this->getJson('/api/v1/notifications?only_action_required=true');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('pagination.total', 1)
            ->assertJsonPath('data.0.id', $pending->id)
            ->assertJsonPath('data.0.can_be_cleared', false);
    }

    public function test_mobile_notification_dispatch_enqueues_push_delivery_queue_and_tracks_delivery(): void
    {
        config()->set('services.push.fcm_server_key', 'test-fcm-key');
        Http::fake([
            'https://fcm.googleapis.com/fcm/send' => Http::response(['results' => [['message_id' => 'abc123']]], 200),
        ]);

        Queue::fake();

        $user = User::factory()->create([
            'role' => UserRole::PASSENGER->value,
            'is_approved' => true,
        ]);

        $user->mobileDeviceTokens()->create([
            'platform' => 'fcm',
            'device_token' => 'fcm-test-token-001',
            'device_id' => 'pixel-test',
            'last_seen_at' => now(),
        ]);

        $notification = app(\App\Services\MobileNotificationService::class)->sendToUserId(
            $user->id,
            'ride_request_received',
            'New Ride Request',
            'A driver is looking for your ride.',
            ['trip_id' => 1234]
        );

        Queue::assertPushed(DeliverPushNotificationJob::class, function (DeliverPushNotificationJob $job) use ($notification) {
            return $job->notificationId === $notification->id;
        });

        $job = new DeliverPushNotificationJob($notification->id);
        $job->handle(app(\App\Services\PushDeliveryBridge::class));

        $this->assertDatabaseHas('notification_deliveries', [
            'notification_id' => $notification->id,
            'status' => 'delivered',
            'platform' => 'fcm',
        ]);
    }

    public function test_notification_acknowledgement_endpoint_marks_delivery_acknowledged(): void
    {
        $user = User::factory()->create([
            'role' => UserRole::PASSENGER->value,
            'is_approved' => true,
        ]);

        $notification = Notification::create([
            'user_id' => $user->id,
            'type' => 'ride_request_accepted',
            'title' => 'Ride Accepted',
            'message' => 'Your ride is on the way.',
            'data' => ['trip_id' => 555],
            'is_read' => false,
        ]);

        $delivery = NotificationDelivery::create([
            'notification_id' => $notification->id,
            'user_id' => $user->id,
            'platform' => 'fcm',
            'status' => 'delivered',
            'sent_at' => now(),
        ]);

        Sanctum::actingAs($user, ['*']);

        $this->putJson('/api/v1/notifications/'.$notification->id.'/acknowledge')
            ->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.acknowledged', true);

        $this->assertDatabaseHas('user_notifications', [
            'id' => $notification->id,
            'is_read' => true,
        ]);

        $this->assertDatabaseHas('notification_deliveries', [
            'id' => $delivery->id,
            'status' => 'acknowledged',
        ]);
    }
}
