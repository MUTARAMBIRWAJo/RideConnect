<?php

namespace Tests\Feature\V3;

use App\Models\Driver;
use App\Models\DriverTripOffer;
use App\Models\User;
use App\Models\V3\TripV3;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class MatchingFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        Event::fake([\App\Events\V3\TripV3StatusChanged::class]);
    }

    public function test_driver_can_accept_offered_trip(): void
    {
        $user = User::factory()->create();
        $driverUser = User::factory()->create();
        $driver = Driver::factory()->create(['user_id' => $driverUser->id]);

        $trip = TripV3::create([
            'user_id' => $user->id,
            'transport_type' => 'motor_vehicle',
            'status' => 'NOTIFIED',
            'matched_driver_id' => $driver->id,
            'driver_response_status' => 'pending',
            'match_attempt_count' => 1,
        ]);
        DriverTripOffer::create([
            'trip_id' => $trip->id,
            'driver_id' => $driver->id,
            'status' => 'pending',
            'expires_at' => now()->addSeconds(30),
            'payload' => ['trip_id' => $trip->id],
        ]);

        $response = $this->actingAs($driverUser, 'sanctum')
            ->postJson("/api/v3/trips/{$trip->id}/accept");

        $response->assertStatus(200);
        
        $this->assertDatabaseHas('trips_v3', [
            'id' => $trip->id,
            'status' => 'DRIVER_ASSIGNED',
            'driver_id' => $driver->id,
            'driver_response_status' => 'accepted',
        ]);
    }

    public function test_driver_can_reject_offered_trip_and_trigger_retry(): void
    {
        $user = User::factory()->create();
        $driverUser = User::factory()->create();
        $driver = Driver::factory()->create(['user_id' => $driverUser->id]);

        // Seed second online driver near default location (-1.95, 30.06)
        $driverUser2 = User::factory()->create();
        $driver2 = Driver::factory()->create([
            'user_id' => $driverUser2->id,
            'status' => 'approved',
            'is_online' => true,
            'availability_status' => 'available',
            'current_latitude' => -1.95,
            'current_longitude' => 30.06,
        ]);
        \App\Models\Vehicle::factory()->create([
            'driver_id' => $driver2->id,
            'vehicle_type' => 'motorcycle',
            'is_active' => true,
        ]);

        $trip = TripV3::create([
            'user_id' => $user->id,
            'transport_type' => 'motor_vehicle',
            'status' => 'NOTIFIED',
            'matched_driver_id' => $driver->id,
            'driver_response_status' => 'pending',
            'match_attempt_count' => 1,
        ]);
        DriverTripOffer::create([
            'trip_id' => $trip->id,
            'driver_id' => $driver->id,
            'status' => 'pending',
            'expires_at' => now()->addSeconds(30),
            'payload' => ['trip_id' => $trip->id],
        ]);

        $response = $this->actingAs($driverUser, 'sanctum')
            ->postJson("/api/v3/trips/{$trip->id}/reject");

        $response->assertStatus(200);
        
        $this->assertDatabaseHas('trips_v3', [
            'id' => $trip->id,
            'status' => 'MATCHING',
            'matched_driver_id' => null,
            'driver_response_status' => 'rejected',
        ]);

        $trip->refresh();
        $this->assertContains($driver->id, $trip->ignored_driver_ids);
    }

    public function test_fallback_matching_assigns_patrick_after_60_seconds(): void
    {
        $user = User::factory()->create();
        $patrickUser = User::factory()->create(['email' => 'patrick.habimana@example.com']);
        $patrickDriver = Driver::factory()->create([
            'user_id' => $patrickUser->id,
            'status' => 'approved',
            'is_active' => true,
            'is_online' => true,
            'availability_status' => 'available',
        ]);

        // Create vehicle for Patrick
        \App\Models\Vehicle::factory()->create([
            'driver_id' => $patrickDriver->id,
            'vehicle_type' => 'sedan',
            'is_active' => true,
        ]);

        $trip = TripV3::create([
            'user_id' => $user->id,
            'transport_type' => 'private_car',
            'status' => 'MATCHING',
            'matching_started_at' => now()->subSeconds(65),
            'match_attempt_count' => 1,
        ]);

        $engine = app(\App\Services\V3\TripMatchingEngineV3::class);
        $engine->executeMatch($trip);

        $trip->refresh();
        $this->assertEquals($patrickDriver->id, $trip->matched_driver_id);
        $this->assertTrue((bool) $trip->fallback_match_used);
    }
}
