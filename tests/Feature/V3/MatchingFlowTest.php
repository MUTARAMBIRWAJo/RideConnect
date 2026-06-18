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
            'status' => 'MATCHING',
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

        $trip = TripV3::create([
            'user_id' => $user->id,
            'transport_type' => 'motor_vehicle',
            'status' => 'MATCHING',
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

        Queue::assertPushed(\App\Jobs\V3\ProcessTripMatchingV3::class);
    }
}
