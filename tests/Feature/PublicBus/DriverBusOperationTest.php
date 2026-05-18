<?php

namespace Tests\Feature\PublicBus;

use App\Models\BusRouteAssignment;
use App\Models\CorridorStop;
use App\Models\Driver;
use App\Models\MobileUser;
use App\Models\PassengerRouteBoarding;
use App\Models\StopArrivalEvent;
use App\Models\TransportCorridor;
use App\Models\Trip;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DriverBusOperationTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected User $driver;
    protected Driver $driverProfile;
    protected Vehicle $bus;
    protected BusRouteAssignment $assignment;
    protected TransportCorridor $corridor;
    protected CorridorStop $stop;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setupDriver();
    }

    protected function setupDriver(): void
    {
        $this->driver = User::factory()->create([
            'role' => 'DRIVER',
            'is_approved' => true,
        ]);

        $this->driverProfile = Driver::factory()->create([
            'user_id' => $this->driver->id,
            'status' => 'approved',
            'availability_status' => 'available',
        ]);

        $this->bus = Vehicle::factory()->create([
            'driver_id' => $this->driverProfile->id,
            'vehicle_type' => 'van',
        ]);

        $this->corridor = TransportCorridor::create([
            'corridor_code' => 'BUS-001',
            'corridor_name' => 'Downtown Loop',
            'transport_type' => 'BUS',
            'status' => 'active',
        ]);

        $this->assignment = BusRouteAssignment::create([
            'bus_id' => $this->bus->id,
            'corridor_id' => $this->corridor->id,
            'driver_id' => $this->driverProfile->id,
            'status' => 'active',
        ]);

        $this->stop = CorridorStop::create([
            'corridor_id' => $this->corridor->id,
            'stop_name' => 'Main Station',
            'stop_order' => 1,
            'latitude' => -1.2921,
            'longitude' => 36.8219,
        ]);
    }

    public function test_driver_can_post_live_location(): void
    {
        Sanctum::actingAs($this->driver);

        $response = $this->postJson('/api/v1/driver/public-bus/location', [
            'bus_route_assignment_id' => $this->assignment->id,
            'latitude' => -1.2921,
            'longitude' => 36.8219,
            'speed_kph' => 45.5,
        ]);

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'bus_route_assignment_id',
                    'latitude',
                    'longitude',
                    'speed_kph',
                    'captured_at',
                ],
            ]);

        $this->assertDatabaseHas('bus_position_updates', [
            'bus_route_assignment_id' => $this->assignment->id,
        ]);
    }

    public function test_driver_can_post_arrived_at_stop(): void
    {
        Sanctum::actingAs($this->driver);

        $response = $this->postJson('/api/v1/driver/public-bus/arrived-stop', [
            'bus_route_assignment_id' => $this->assignment->id,
            'corridor_stop_id' => $this->stop->id,
        ]);

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'bus_route_assignment_id',
                    'corridor_stop_id',
                    'arrival_time',
                    'departure_time',
                ],
            ]);

        $this->assertDatabaseHas('stop_arrival_events', [
            'bus_route_assignment_id' => $this->assignment->id,
            'corridor_stop_id' => $this->stop->id,
        ]);
    }

    public function test_driver_can_post_passenger_boarded(): void
    {
        Sanctum::actingAs($this->driver);

        $passenger = MobileUser::factory()->create(['role' => 'PASSENGER']);
        $trip = Trip::create([
            'passenger_id' => $passenger->id,
            'driver_id' => $this->driverProfile->id,
            'pickup_location' => 'Main Station',
            'dropoff_location' => 'Airport',
            'pickup_lat' => -1.2921,
            'pickup_lng' => 36.8219,
            'dropoff_lat' => -1.3521,
            'dropoff_lng' => 36.7278,
            'fare' => 250.00,
            'status' => 'PENDING',
            'requested_at' => now(),
        ]);

        $boarding = PassengerRouteBoarding::create([
            'passenger_id' => $passenger->id,
            'trip_id' => $trip->id,
            'corridor_id' => $this->corridor->id,
            'bus_route_assignment_id' => $this->assignment->id,
            'boarding_stop_id' => $this->stop->id,
            'destination_stop_id' => $this->stop->id,
            'ticket_code' => 'TEST-BOARD-001',
            'qr_payload' => ['test' => true],
            'seats_reserved' => 1,
            'fare_amount' => 250.00,
            'payment_status' => 'pending',
            'status' => 'reserved',
        ]);

        $response = $this->postJson('/api/v1/driver/public-bus/passenger-boarded', [
            'passenger_route_boarding_id' => $boarding->id,
        ]);

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'passenger_route_boarding_id',
                    'status',
                    'boarded_at',
                ],
            ]);

        $this->assertDatabaseHas('passenger_boarding_events', [
            'passenger_route_boarding_id' => $boarding->id,
        ]);
    }

    public function test_driver_can_post_passenger_completed(): void
    {
        Sanctum::actingAs($this->driver);

        $passenger = MobileUser::factory()->create(['role' => 'PASSENGER']);
        $trip = Trip::create([
            'passenger_id' => $passenger->id,
            'driver_id' => $this->driverProfile->id,
            'pickup_location' => 'Main Station',
            'dropoff_location' => 'Airport',
            'pickup_lat' => -1.2921,
            'pickup_lng' => 36.8219,
            'dropoff_lat' => -1.3521,
            'dropoff_lng' => 36.7278,
            'fare' => 250.00,
            'status' => 'PENDING',
            'requested_at' => now(),
        ]);

        $boarding = PassengerRouteBoarding::create([
            'passenger_id' => $passenger->id,
            'trip_id' => $trip->id,
            'corridor_id' => $this->corridor->id,
            'bus_route_assignment_id' => $this->assignment->id,
            'boarding_stop_id' => $this->stop->id,
            'destination_stop_id' => $this->stop->id,
            'ticket_code' => 'TEST-COMPLETE-001',
            'qr_payload' => ['test' => true],
            'seats_reserved' => 1,
            'fare_amount' => 250.00,
            'payment_status' => 'pending',
            'status' => 'boarded',
        ]);

        $response = $this->postJson('/api/v1/driver/public-bus/passenger-completed', [
            'passenger_route_boarding_id' => $boarding->id,
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
