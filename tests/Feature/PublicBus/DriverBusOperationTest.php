<?php

namespace Tests\Feature\PublicBus;

use App\Models\BusPositionUpdate;
use App\Models\BusRouteAssignment;
use App\Models\MobileUser;
use App\Models\PassengerBoardingEvent;
use App\Models\PassengerRouteBoarding;
use App\Models\StopArrivalEvent;
use App\Models\TransportCorridor;
use App\Models\TransportStop;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DriverBusOperationTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected MobileUser $driver;
    protected Vehicle $bus;
    protected BusRouteAssignment $assignment;
    protected TransportCorridor $corridor;
    protected TransportStop $stop;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setupDriver();
    }

    protected function setupDriver(): void
    {
        $this->driver = MobileUser::factory()->create(['role' => 'driver']);
        $this->bus = Vehicle::factory()->create([
            'driver_id' => $this->driver->id,
            'type' => 'bus',
            'plate_number' => 'TXL-001A',
        ]);

        $this->corridor = TransportCorridor::factory()->create(['is_active' => true]);
        $this->assignment = BusRouteAssignment::factory()->create([
            'corridor_id' => $this->corridor->id,
            'vehicle_id' => $this->bus->id,
            'status' => 'active',
        ]);

        $this->stop = TransportStop::factory()->create([
            'corridor_id' => $this->corridor->id,
            'name' => 'Main Station',
            'order_index' => 1,
        ]);
    }

    public function test_driver_can_post_live_location(): void
    {
        Sanctum::actingAs($this->driver);

        $response = $this->postJson('/api/v1/driver/public-bus/location', [
            'assignment_id' => $this->assignment->id,
            'latitude' => -1.2921,
            'longitude' => 36.8219,
            'speed_kmh' => 45.5,
        ]);

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'assignment_id',
                    'latitude',
                    'longitude',
                    'speed_kmh',
                    'timestamp',
                ],
            ]);

        $this->assertDatabaseHas('bus_position_updates', [
            'assignment_id' => $this->assignment->id,
        ]);
    }

    public function test_driver_can_post_arrived_at_stop(): void
    {
        Sanctum::actingAs($this->driver);

        $response = $this->postJson('/api/v1/driver/public-bus/arrived-stop', [
            'assignment_id' => $this->assignment->id,
            'stop_id' => $this->stop->id,
            'arrival_time' => now()->toIso8601String(),
        ]);

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'assignment_id',
                    'stop_id',
                    'arrival_time',
                    'departure_time',
                ],
            ]);

        $this->assertDatabaseHas('stop_arrival_events', [
            'assignment_id' => $this->assignment->id,
            'stop_id' => $this->stop->id,
        ]);
    }

    public function test_driver_can_post_passenger_boarded(): void
    {
        Sanctum::actingAs($this->driver);

        $boarding = PassengerRouteBoarding::factory()->create([
            'assignment_id' => $this->assignment->id,
            'status' => 'awaiting_boarding',
        ]);

        $response = $this->postJson('/api/v1/driver/public-bus/passenger-boarded', [
            'boarding_id' => $boarding->id,
            'boarding_time' => now()->toIso8601String(),
        ]);

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'status',
                    'boarded_at',
                ],
            ]);

        $this->assertDatabaseHas('passenger_boarding_events', [
            'boarding_id' => $boarding->id,
        ]);
    }

    public function test_driver_can_post_passenger_completed(): void
    {
        Sanctum::actingAs($this->driver);

        $boarding = PassengerRouteBoarding::factory()->create([
            'assignment_id' => $this->assignment->id,
            'status' => 'on_board',
            'dropoff_stop_id' => $this->stop->id,
        ]);

        $response = $this->postJson('/api/v1/driver/public-bus/passenger-completed', [
            'boarding_id' => $boarding->id,
            'completion_time' => now()->toIso8601String(),
        ]);

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'status',
                    'completed_at',
                ],
            ]);

        $this->assertDatabaseHas('passenger_route_boardings', [
            'id' => $boarding->id,
            'status' => 'completed',
        ]);
    }
}
