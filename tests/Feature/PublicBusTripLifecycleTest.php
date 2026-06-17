<?php

namespace Tests\Feature;

use App\Models\Trip;
use App\Models\User;
use App\Models\Driver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicBusTripLifecycleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Setup initial users
        $this->passenger = User::factory()->create(['role' => 'PASSENGER', 'is_approved' => true]);
        $this->driverUser = User::factory()->create(['role' => 'DRIVER', 'is_approved' => true]);
        
        $this->driver = Driver::factory()->create([
            'user_id' => $this->driverUser->id,
            'status' => 'approved',
            'is_available' => true,
        ]);
    }

    public function test_can_request_public_bus_trip()
    {
        $response = $this->actingAs($this->passenger, 'sanctum')->postJson('/api/v1/passenger/public-bus/trip-request', [
            'pickup_location' => 'Kigali Heights',
            'dropoff_location' => 'Downtown',
            'pickup_lat' => -1.954,
            'pickup_lng' => 30.096,
            'dropoff_lat' => -1.944,
            'dropoff_lng' => 30.056,
        ]);

        $response->assertStatus(201)
                 ->assertJsonPath('success', true);

        $trip = Trip::first();
        $this->assertNotNull($trip);
        $this->assertEquals('PUBLIC_BUS', $trip->transport_type);
        $this->assertEquals('BUS_ASSIGNED', $trip->status); // Instantly matched in our simple service
    }

    public function test_full_lifecycle_flow()
    {
        $response = $this->actingAs($this->passenger, 'sanctum')->postJson('/api/v1/passenger/public-bus/trip-request', [
            'pickup_location' => 'Kigali Heights',
            'dropoff_location' => 'Downtown',
            'pickup_lat' => -1.954,
            'pickup_lng' => 30.096,
            'dropoff_lat' => -1.944,
            'dropoff_lng' => 30.056,
        ]);

        $tripId = $response->json('data.id');

        // Board
        $responseBoard = $this->actingAs($this->passenger, 'sanctum')->postJson("/api/v1/passenger/trips/{$tripId}/board");
        $responseBoard->assertStatus(200);
        $this->assertEquals('PASSENGERS_BOARDING', Trip::find($tripId)->status);

        // Start
        $responseStart = $this->actingAs($this->driverUser, 'sanctum')->postJson("/api/v1/passenger/trips/{$tripId}/start");
        $responseStart->assertStatus(200);
        $this->assertEquals('STARTED', Trip::find($tripId)->status);

        // Complete
        $responseComplete = $this->actingAs($this->driverUser, 'sanctum')->postJson("/api/v1/passenger/trips/{$tripId}/complete");
        $responseComplete->assertStatus(200);
        
        $trip = Trip::find($tripId);
        $this->assertEquals('COMPLETED', $trip->status);
        $this->assertEquals(0, $trip->capacity_used);
    }
}
