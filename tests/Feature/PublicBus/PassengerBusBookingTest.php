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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PassengerBusBookingTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected MobileUser $passenger;
    protected TransportCorridor $corridor;
    protected TransportStop $pickupStop;
    protected TransportStop $dropoffStop;

    protected function setUp(): void
    {
        parent::setUp();
        $this->passenger = MobileUser::factory()->create(['role' => 'PASSENGER']);
        $this->setupCorridor();
    }

    protected function setupCorridor(): void
    {
        $this->corridor = TransportCorridor::factory()->create([
            'name' => 'Downtown to Airport',
            'start_point' => 'Downtown Terminal',
            'end_point' => 'International Airport',
            'is_active' => true,
        ]);

        $this->pickupStop = TransportStop::factory()->create([
            'corridor_id' => $this->corridor->id,
            'name' => 'Downtown Terminal',
            'order_index' => 1,
            'latitude' => -1.2921,
            'longitude' => 36.8219,
        ]);

        $this->dropoffStop = TransportStop::factory()->create([
            'corridor_id' => $this->corridor->id,
            'name' => 'Airport Departure',
            'order_index' => 5,
            'latitude' => -1.3521,
            'longitude' => 36.7278,
        ]);
    }

    public function test_passenger_can_list_corridors(): void
    {
        Sanctum::actingAs($this->passenger);

        $response = $this->getJson('/api/v1/passenger/public-bus/corridors');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'name',
                        'start_point',
                        'end_point',
                        'is_active',
                    ],
                ],
            ]);
    }

    public function test_passenger_can_list_stops_for_corridor(): void
    {
        Sanctum::actingAs($this->passenger);

        $response = $this->getJson("/api/v1/passenger/public-bus/corridors/{$this->corridor->id}/stops");

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'name',
                        'order_index',
                        'latitude',
                        'longitude',
                    ],
                ],
            ]);
    }

    public function test_passenger_can_view_active_buses(): void
    {
        Sanctum::actingAs($this->passenger);

        $response = $this->getJson("/api/v1/passenger/public-bus/corridors/{$this->corridor->id}/active-buses");

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'vehicle_id',
                        'status',
                        'current_position',
                        'available_seats',
                        'fare',
                    ],
                ],
            ]);
    }

    public function test_passenger_can_book_seat(): void
    {
        Sanctum::actingAs($this->passenger);

        $assignment = BusRouteAssignment::factory()->create([
            'corridor_id' => $this->corridor->id,
            'status' => 'active',
        ]);

        $response = $this->postJson('/api/v1/passenger/public-bus/book-seat', [
            'assignment_id' => $assignment->id,
            'pickup_stop_id' => $this->pickupStop->id,
            'dropoff_stop_id' => $this->dropoffStop->id,
            'fare' => 500,
        ]);

        $response->assertCreated()
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'passenger_id',
                    'assignment_id',
                    'trip_id',
                    'status',
                    'ticket_number',
                    'fare',
                    'pickup_stop',
                    'dropoff_stop',
                ],
            ]);

        $this->assertDatabaseHas('passenger_route_boardings', [
            'user_id' => $this->passenger->id,
            'assignment_id' => $assignment->id,
        ]);
    }

    public function test_passenger_can_view_current_trip(): void
    {
        Sanctum::actingAs($this->passenger);

        $booking = PassengerRouteBoarding::factory()->create([
            'user_id' => $this->passenger->id,
        ]);

        $response = $this->getJson('/api/v1/passenger/public-bus/trips/current');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'ticket_number',
                    'bus_assignment',
                    'boarding_status',
                    'pickup_stop',
                    'dropoff_stop',
                    'estimated_arrival',
                ],
            ]);
    }

    public function test_passenger_can_retrieve_ticket(): void
    {
        Sanctum::actingAs($this->passenger);

        $booking = PassengerRouteBoarding::factory()->create([
            'user_id' => $this->passenger->id,
        ]);

        $response = $this->getJson("/api/v1/passenger/public-bus/tickets/{$booking->id}");

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'ticket_number',
                    'passenger_name',
                    'corridor',
                    'pickup_stop',
                    'dropoff_stop',
                    'bus_plate',
                    'fare',
                    'booking_time',
                    'departure_time',
                    'qr_code',
                ],
            ]);
    }
}
