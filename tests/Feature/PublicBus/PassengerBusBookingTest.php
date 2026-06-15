<?php

namespace Tests\Feature\PublicBus;

use App\Models\BusRouteAssignment;
use App\Models\CorridorStop;
use App\Models\Driver;
use App\Models\MobileUser;
use App\Models\PassengerRouteBoarding;
use App\Models\TransportCorridor;
use App\Models\Trip;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PassengerBusBookingTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected User $passengerUser;
    protected MobileUser $passengerMobile;
    protected TransportCorridor $corridor;
    protected CorridorStop $pickupStop;
    protected CorridorStop $dropoffStop;
    protected Driver $driverProfile;
    protected Vehicle $bus;
    protected BusRouteAssignment $assignment;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setupPassengerAndCorridor();
        $this->setupActiveBus();
    }

    protected function setupPassengerAndCorridor(): void
    {
        $this->passengerMobile = MobileUser::factory()->create([
            'role' => 'PASSENGER',
        ]);

        $this->passengerUser = User::factory()->create([
            'role' => 'PASSENGER',
            'mobile_user_id' => $this->passengerMobile->id,
            'email' => $this->passengerMobile->email,
            'phone' => $this->passengerMobile->phone,
            'is_verified' => true,
            'is_approved' => true,
        ]);

        $this->corridor = TransportCorridor::create([
            'corridor_code' => 'BUS-100',
            'corridor_name' => 'Downtown to Airport',
            'transport_type' => 'BUS',
            'status' => 'active',
            'estimated_duration_minutes' => 35,
        ]);

        $this->pickupStop = CorridorStop::create([
            'corridor_id' => $this->corridor->id,
            'stop_name' => 'Downtown Terminal',
            'stop_order' => 1,
            'latitude' => -1.2921,
            'longitude' => 36.8219,
        ]);

        $this->dropoffStop = CorridorStop::create([
            'corridor_id' => $this->corridor->id,
            'stop_name' => 'Airport Departure',
            'stop_order' => 5,
            'latitude' => -1.3521,
            'longitude' => 36.7278,
        ]);
    }

    protected function setupActiveBus(): void
    {
        $this->driverProfile = Driver::factory()->create([
            'status' => 'approved',
            'availability_status' => 'available',
        ]);

        $this->bus = Vehicle::factory()->create([
            'driver_id' => $this->driverProfile->id,
            'vehicle_type' => 'van',
            'make' => 'Toyota',
            'model' => 'Coaster',
            'year' => 2020,
            'color' => 'White',
        ]);

        $this->assignment = BusRouteAssignment::create([
            'bus_id' => $this->bus->id,
            'corridor_id' => $this->corridor->id,
            'driver_id' => $this->driverProfile->id,
            'status' => 'active',
        ]);
    }

    public function test_passenger_can_list_corridors(): void
    {
        Sanctum::actingAs($this->passengerUser);

        $response = $this->getJson('/api/v1/passenger/public-bus/corridors');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'corridor_code',
                        'corridor_name',
                        'transport_type',
                        'status',
                    ],
                ],
            ]);
    }

    public function test_passenger_can_list_stops_for_corridor(): void
    {
        Sanctum::actingAs($this->passengerUser);

        $response = $this->getJson("/api/v1/passenger/public-bus/corridors/{$this->corridor->id}/stops");

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'corridor' => [
                        'id',
                        'corridor_code',
                        'corridor_name',
                    ],
                    'stops' => [
                        '*' => [
                            'id',
                            'stop_name',
                            'stop_order',
                            'latitude',
                            'longitude',
                            'is_major_terminal',
                        ],
                    ],
                ],
            ]);
    }

    public function test_passenger_can_view_active_buses(): void
    {
        Sanctum::actingAs($this->passengerUser);

        $response = $this->getJson("/api/v1/passenger/public-bus/corridors/{$this->corridor->id}/active-buses");

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'assignment_id',
                        'bus_id',
                        'driver',
                        'bus',
                        'route',
                        'available_seats',
                        'status',
                        'location',
                    ],
                ],
            ]);
    }

    public function test_passenger_can_book_seat_with_selected_bus_assignment(): void
    {
        Sanctum::actingAs($this->passengerUser);

        $response = $this->postJson('/api/v1/passenger/public-bus/book-seat', [
            'corridor_id' => $this->corridor->id,
            'boarding_stop_id' => $this->pickupStop->id,
            'destination_stop_id' => $this->dropoffStop->id,
            'bus_route_assignment_id' => $this->assignment->id,
            'seats_reserved' => 1,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.bus.id', $this->bus->id)
            ->assertJsonPath('data.bus.display_name', sprintf('%s %s %s', $this->bus->year, $this->bus->make, $this->bus->model));

        $this->assertDatabaseHas('passenger_route_boardings', [
            'passenger_id' => $this->passengerUser->id,
            'corridor_id' => $this->corridor->id,
            'bus_route_assignment_id' => $this->assignment->id,
        ]);
    }

    public function test_passenger_can_book_seat(): void
    {
        Sanctum::actingAs($this->passengerUser);

        $response = $this->postJson('/api/v1/passenger/public-bus/book-seat', [
            'corridor_id' => $this->corridor->id,
            'boarding_stop_id' => $this->pickupStop->id,
            'destination_stop_id' => $this->dropoffStop->id,
            'seats_reserved' => 1,
        ]);

        $response->assertCreated()
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'ticket_code',
                    'corridor',
                    'bus',
                    'boarding_stop',
                    'destination_stop',
                    'status',
                ],
            ]);

        $this->assertDatabaseHas('passenger_route_boardings', [
            'passenger_id' => $this->passengerUser->id,
            'corridor_id' => $this->corridor->id,
        ]);
    }

    public function test_unapproved_passenger_cannot_book_seat(): void
    {
        $mobile = MobileUser::factory()->create([
            'role' => 'PASSENGER',
        ]);

        $user = User::factory()->create([
            'role' => 'PASSENGER',
            'mobile_user_id' => $mobile->id,
            'email' => $mobile->email,
            'phone' => $mobile->phone,
            'is_verified' => true,
            'is_approved' => false,
        ]);

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/passenger/public-bus/book-seat', [
            'corridor_id' => $this->corridor->id,
            'boarding_stop_id' => $this->pickupStop->id,
            'destination_stop_id' => $this->dropoffStop->id,
            'seats_reserved' => 1,
        ]);

        $response->assertForbidden()
            ->assertJsonPath('message', 'Your account must be approved to book a bus seat')
            ->assertJsonPath('error_code', 'PASSENGER_NOT_APPROVED');
    }

    public function test_passenger_can_view_current_trip(): void
    {
        Sanctum::actingAs($this->passengerUser);

        $trip = Trip::create([
            'passenger_id' => $this->passengerUser->id,
            'driver_id' => $this->driverProfile->id,
            'pickup_location' => 'Downtown Terminal',
            'dropoff_location' => 'Airport Departure',
            'pickup_lat' => $this->pickupStop->latitude,
            'pickup_lng' => $this->pickupStop->longitude,
            'dropoff_lat' => $this->dropoffStop->latitude,
            'dropoff_lng' => $this->dropoffStop->longitude,
            'fare' => 500.00,
            'status' => 'PENDING',
            'requested_at' => now(),
        ]);

        $booking = PassengerRouteBoarding::create([
            'passenger_id' => $this->passengerUser->id,
            'trip_id' => $trip->id,
            'corridor_id' => $this->corridor->id,
            'bus_route_assignment_id' => $this->assignment->id,
            'boarding_stop_id' => $this->pickupStop->id,
            'destination_stop_id' => $this->dropoffStop->id,
            'ticket_code' => 'BUS-TICKET-001',
            'qr_payload' => ['test' => true],
            'seats_reserved' => 1,
            'fare_amount' => 500.00,
            'payment_status' => 'pending',
            'status' => 'reserved',
        ]);

        $response = $this->getJson('/api/v1/passenger/public-bus/trips/current');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'ticket_code',
                    'corridor',
                    'bus',
                    'boarding_stop',
                    'destination_stop',
                    'status',
                ],
            ]);
    }

    public function test_passenger_can_retrieve_ticket(): void
    {
        Sanctum::actingAs($this->passengerUser);

        $trip = Trip::create([
            'passenger_id' => $this->passengerUser->id,
            'driver_id' => $this->driverProfile->id,
            'pickup_location' => 'Downtown Terminal',
            'dropoff_location' => 'Airport Departure',
            'pickup_lat' => $this->pickupStop->latitude,
            'pickup_lng' => $this->pickupStop->longitude,
            'dropoff_lat' => $this->dropoffStop->latitude,
            'dropoff_lng' => $this->dropoffStop->longitude,
            'fare' => 500.00,
            'status' => 'PENDING',
            'requested_at' => now(),
        ]);

        $booking = PassengerRouteBoarding::create([
            'passenger_id' => $this->passengerUser->id,
            'trip_id' => $trip->id,
            'corridor_id' => $this->corridor->id,
            'bus_route_assignment_id' => $this->assignment->id,
            'boarding_stop_id' => $this->pickupStop->id,
            'destination_stop_id' => $this->dropoffStop->id,
            'ticket_code' => 'BUS-TICKET-002',
            'qr_payload' => ['test' => true],
            'seats_reserved' => 1,
            'fare_amount' => 500.00,
            'payment_status' => 'pending',
            'status' => 'reserved',
        ]);

        $response = $this->getJson("/api/v1/passenger/public-bus/tickets/{$booking->ticket_code}");

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'ticket_code',
                    'corridor',
                    'boarding_stop',
                    'destination_stop',
                    'bus',
                    'fare_amount',
                    'status',
                    'ticket_qr',
                ],
            ]);
    }
}
