<?php

namespace Tests\Feature;

use App\Models\Driver;
use App\Models\MatchingSession;
use App\Models\MobileTripE2eUseCase;
use App\Models\MobileUser;
use App\Models\Notification;
use App\Models\NotificationDelivery;
use App\Models\Trip;
use App\Models\TripStatusEvent;
use App\Models\User;
use Database\Seeders\MobileTripE2eUseCaseSeeder;
use Illuminate\Support\Str;
use Tests\TestCase;

class MobileTripE2eUseCaseTest extends TestCase
{
    public function test_mobile_trip_e2e_use_cases_are_persisted_with_realistic_kigali_flows(): void
    {
        $this->seed(MobileTripE2eUseCaseSeeder::class);

        $this->assertDatabaseCount('mobile_trip_e2e_use_cases', 3);

        $publicBus = MobileTripE2eUseCase::query()->where('slug', 'public-bus-nyabugogo-remera')->firstOrFail();
        $private = MobileTripE2eUseCase::query()->where('slug', 'private-sedan-kcc-kacyiru')->firstOrFail();
        $moto = MobileTripE2eUseCase::query()->where('slug', 'moto-kimironko-downtown')->firstOrFail();

        $this->assertSame('Nyabugogo Bus Park', $publicBus->passenger_flow['inputs']['pickup']['label']);
        $this->assertSame('Remera', $publicBus->passenger_flow['inputs']['destination']['label']);
        $this->assertSame('Kigali Convention Centre', $private->passenger_flow['inputs']['pickup']['label']);
        $this->assertSame('Kimironko', $moto->passenger_flow['inputs']['pickup']['label']);
        $this->assertArrayHasKey('POST /api/v1/passenger/trip-requests', $publicBus->api_payloads);
        $this->assertContains('notification_deliveries', $moto->database_validation['tables']);
    }

    public function test_trip_status_matching_session_and_acknowledgements_are_database_backed(): void
    {
        $passenger = MobileUser::factory()->create([
            'first_name' => 'Aline',
            'last_name' => 'Uwimana',
            'role' => 'PASSENGER',
            'phone' => '+250788111222',
            'is_verified' => true,
        ]);
        $passengerUser = User::factory()->create([
            'name' => 'Aline Uwimana',
            'role' => 'PASSENGER',
            'mobile_user_id' => $passenger->id,
            'phone' => '+250788111222',
            'is_approved' => true,
        ]);

        $driverUser = User::factory()->create([
            'name' => 'Jean Claude',
            'role' => 'DRIVER',
            'is_approved' => true,
        ]);
        $driver = Driver::factory()->create([
            'user_id' => $driverUser->id,
            'license_plate' => 'RAA-123-B',
            'status' => 'approved',
            'availability_status' => 'available',
            'current_latitude' => -1.941612,
            'current_longitude' => 30.057944,
            'rating' => 4.8,
        ]);

        $matchingSessionId = (string) Str::uuid();
        MatchingSession::query()->create([
            'matching_session_id' => $matchingSessionId,
            'passenger_id' => $passenger->id,
            'transport_type' => 'BUS',
            'pickup_lat' => -1.939826,
            'pickup_lng' => 30.044542,
            'dropoff_lat' => -1.953564,
            'dropoff_lng' => 30.109842,
            'selected_driver_id' => $driver->id,
            'status' => 'selected',
            'payload' => [
                'selected_driver' => 'Jean Claude',
                'ranking_score' => 0.8842,
                'estimated_fare' => 2500,
                'eta_minutes' => 12,
            ],
            'expires_at' => now()->addMinutes(5),
        ]);

        $trip = Trip::factory()->create([
            'passenger_id' => $passenger->id,
            'driver_id' => $driver->id,
            'transport_type' => 'BUS',
            'matching_session_id' => $matchingSessionId,
            'pickup_location' => 'Nyabugogo Bus Park',
            'pickup_lat' => -1.939826,
            'pickup_lng' => 30.044542,
            'dropoff_location' => 'Remera',
            'dropoff_lat' => -1.953564,
            'dropoff_lng' => 30.109842,
            'fare' => 2500,
            'status' => 'ACCEPTED',
            'assignment_status' => 'accepted',
            'payment_status' => 'pending',
            'requested_at' => now()->subMinutes(2),
            'accepted_at' => now()->subMinute(),
            'started_at' => null,
            'completed_at' => null,
        ]);

        $notification = Notification::query()->create([
            'user_id' => $passengerUser->id,
            'type' => 'ride_request_accepted',
            'title' => 'Driver Accepted',
            'message' => 'Jean Claude accepted your public transport trip.',
            'data' => ['trip_id' => $trip->id, 'driver_id' => $driver->id],
            'is_read' => false,
        ]);

        $this->actingAs($passengerUser, 'sanctum')
            ->getJson("/api/v1/passenger/trips/{$trip->id}/status")
            ->assertOk()
            ->assertJsonPath('data.trip_status', 'ACCEPTED')
            ->assertJsonPath('data.driver_id', $driver->id)
            ->assertJsonPath('data.timeline.3.checked', true);

        $this->actingAs($passengerUser, 'sanctum')
            ->getJson("/api/v1/passenger/trips/{$trip->id}/matching-session")
            ->assertOk()
            ->assertJsonPath('data.matching_session_id', $matchingSessionId)
            ->assertJsonPath('data.selected_driver_id', $driver->id)
            ->assertJsonPath('data.payload.estimated_fare', 2500);

        $this->actingAs($passengerUser, 'sanctum')
            ->postJson("/api/v1/trips/{$trip->id}/acknowledge", [
                'acknowledgement_type' => 'passenger_driver_selected',
                'source' => 'flutter',
                'metadata' => ['page' => 'PublicTransportTripPage'],
            ])
            ->assertOk()
            ->assertJsonPath('data.acknowledgement_type', 'passenger_driver_selected');

        $this->actingAs($passengerUser, 'sanctum')
            ->postJson("/api/v1/notifications/{$notification->id}/acknowledged", [
                'source' => 'fcm',
                'device_id' => 'aline-pixel-8',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'acknowledged');

        $this->assertDatabaseHas('trip_status_events', [
            'trip_id' => $trip->id,
            'new_status' => 'ACCEPTED',
        ]);
        $this->assertSame(1, TripStatusEvent::query()->where('trip_id', $trip->id)->count());
        $this->assertDatabaseHas('notification_deliveries', [
            'notification_id' => $notification->id,
            'user_id' => $passengerUser->id,
            'status' => 'acknowledged',
        ]);
        $this->assertTrue(NotificationDelivery::query()->where('notification_id', $notification->id)->firstOrFail()->acknowledged_at !== null);
    }
}
