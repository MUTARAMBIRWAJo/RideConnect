<?php

namespace Tests\Feature\V3;

use App\Models\User;
use App\Models\Driver;
use App\Models\V3\TripV3;
use Illuminate\Support\Facades\Queue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class TripV3FeatureTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Event::fake([\App\Events\V3\TripV3StatusChanged::class]);
        Queue::fake();
    }

    public function test_motor_vehicle_trip_creation(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/v3/trips/motor-vehicle/request', [
            'pickup_location' => 'Kigali City Tower',
            'pickup_lat' => -1.94407,
            'pickup_lng' => 30.06188,
            'dropoff_location' => 'Kimi',
            'dropoff_lat' => -1.94995,
            'dropoff_lng' => 30.11273,
            'ride_mode' => 'instant',
            'payment_method' => 'cash',
        ]);

        $response->assertStatus(201);
        $response->assertJsonStructure(['success', 'data' => ['id', 'status', 'transport_type']]);

        $this->assertDatabaseHas('trips_v3', [
            'user_id' => $user->id,
            'transport_type' => 'motor_vehicle',
            'status' => 'MATCHING',
        ]);

        Event::assertDispatched(\App\Events\V3\TripV3StatusChanged::class);
    }

    public function test_private_car_trip_creation(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/v3/trips/private-car/request', [
            'pickup_location' => 'Airport',
            'pickup_lat' => -1.96863,
            'pickup_lng' => 30.13945,
            'dropoff_location' => 'Hotel',
            'dropoff_lat' => -1.95332,
            'dropoff_lng' => 30.09224,
            'car_type_preference' => 'luxury',
            'scheduled_time' => now()->addHours(2)->toDateTimeString(),
        ]);

        $response->assertStatus(201);
        
        $this->assertDatabaseHas('trips_v3', [
            'user_id' => $user->id,
            'transport_type' => 'private_car',
            'status' => 'MATCHING',
        ]);

        $trip = TripV3::first();
        $this->assertEquals('luxury', $trip->metadata['car_type_preference']);
    }

    public function test_public_bus_trip_creation(): void
    {
        $user = User::factory()->create();
        $driverUser = User::factory()->create();
        $driver = Driver::factory()->create(['user_id' => $driverUser->id]);

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/v3/trips/public-bus/request', [
            'pickup_stop' => 'Stop A',
            'pickup_lat' => -1.93698,
            'pickup_lng' => 30.13014,
            'dropoff_stop' => 'Stop B',
            'dropoff_lat' => -1.94407,
            'dropoff_lng' => 30.06188,
            'route_id' => 'ROUTE-101',
            'driver_id' => $driver->id,
            'passenger_count' => 2,
            'preferred_time' => 'now',
        ]);

        $response->assertStatus(201);

        $this->assertDatabaseHas('trips_v3', [
            'user_id' => $user->id,
            'transport_type' => 'public_bus',
            'status' => 'MATCHING',
        ]);
        
        $trip = TripV3::first();
        $this->assertEquals(2, $trip->metadata['passenger_count']);
    }
}
