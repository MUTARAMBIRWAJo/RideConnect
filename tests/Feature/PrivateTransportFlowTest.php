<?php

namespace Tests\Feature;

use App\Domain\Ride\RidePolicy;
use App\Domain\Trip\TripStateMachine;
use App\Models\Driver;
use App\Models\MobileUser;
use App\Models\Ride;
use App\Models\Trip;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\DriverAssignmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PrivateTransportFlowTest extends TestCase
{
    use RefreshDatabase;

    private MobileUser $passenger;
    private User $passengerUser;
    private Driver $carDriver;
    private Vehicle $carVehicle;
    private Driver $motorcycleDriver;
    private Vehicle $motorcycleVehicle;

    protected function setUp(): void
    {
        parent::setUp();

        // Create test users and drivers
        $this->passenger = MobileUser::factory()->create([
            'role' => 'PASSENGER',
            'is_verified' => true,
        ]);

        $this->passengerUser = User::factory()->create([
            'role' => 'PASSENGER',
            'mobile_user_id' => $this->passenger->id,
            'is_approved' => true,
        ]);

        // CAR driver with sedan vehicle
        $carMobileUser = MobileUser::factory()->create([
            'role' => 'DRIVER',
            'is_verified' => true,
        ]);
        $carDriverUser = User::factory()->create([
            'role' => 'DRIVER',
            'mobile_user_id' => $carMobileUser->id,
            'is_approved' => true,
        ]);
        $this->carDriver = Driver::factory()->create([
            'user_id' => $carDriverUser->id,
            'status' => 'approved',
        ]);
        $this->carVehicle = Vehicle::factory()->create([
            'driver_id' => $this->carDriver->id,
            'vehicle_type' => 'sedan',
            'is_active' => true,
        ]);

        // Create driver location for CAR driver
        DB::table('driver_locations')->insert([
            'driver_id' => $carMobileUser->id,
            'latitude' => -1.9403,
            'longitude' => 29.8739,
            'updated_at' => now(),
        ]);

        // MOTORCYCLE driver with motorbike
        $motoMobileUser = MobileUser::factory()->create([
            'role' => 'DRIVER',
            'is_verified' => true,
        ]);
        $motoDriverUser = User::factory()->create([
            'role' => 'DRIVER',
            'mobile_user_id' => $motoMobileUser->id,
            'is_approved' => true,
        ]);
        $this->motorcycleDriver = Driver::factory()->create([
            'user_id' => $motoDriverUser->id,
            'status' => 'approved',
        ]);
        $this->motorcycleVehicle = Vehicle::factory()->create([
            'driver_id' => $this->motorcycleDriver->id,
            'vehicle_type' => 'motorcycle',
            'is_active' => true,
        ]);

        // Create driver location for MOTORCYCLE driver
        DB::table('driver_locations')->insert([
            'driver_id' => $motoMobileUser->id,
            'latitude' => -1.9500,
            'longitude' => 29.8800,
            'updated_at' => now(),
        ]);
    }

    /**
     * Test: CAR SCHEDULED flow - Ride → Booking → Trip
     */
    public function test_car_scheduled_flow_with_booking()
    {
        // 1. Create a SCHEDULED CAR ride
        $ride = Ride::factory()->create([
            'driver_id' => $this->carDriver->id,
            'vehicle_id' => $this->carVehicle->id,
            'transport_type' => Ride::TRANSPORT_CAR,
            'travel_mode' => Ride::MODE_SCHEDULED,
            'status' => 'scheduled',
            'available_seats' => 4,
            'departure_time' => now()->addDays(1),
        ]);

        // 2. Verify ride rules allow booking
        $this->assertTrue(RidePolicy::canBook($ride));
        $this->assertFalse(RidePolicy::canRequestTrip($ride));
        $this->assertEquals(RidePolicy::FLOW_BOOKING_ONLY, RidePolicy::getAllowedFlow($ride));

        // 3. Passenger books the ride
        $booking = $ride->bookings()->create([
            'user_id' => $this->passengerUser->id,
            'seats_booked' => 2,
            'total_price' => 20000,
            'currency' => 'RWF',
            'status' => 'CONFIRMED',
            'pickup_address' => 'Central Station',
            'pickup_lat' => -1.9403,
            'pickup_lng' => 29.8739,
            'dropoff_address' => 'Airport',
            'dropoff_lat' => -1.9764,
            'dropoff_lng' => 30.0116,
        ]);

        $this->assertEquals('CONFIRMED', $booking->status);

        // 4. Convert booking to trip
        $trip = Trip::create([
            'booking_id' => $booking->id,
            'ride_id' => $ride->id,
            'passenger_id' => $this->passenger->id,
            'driver_id' => $this->carDriver->id,
            'pickup_location' => $booking->pickup_address,
            'pickup_lat' => $booking->pickup_lat,
            'pickup_lng' => $booking->pickup_lng,
            'dropoff_location' => $booking->dropoff_address,
            'dropoff_lat' => $booking->dropoff_lat,
            'dropoff_lng' => $booking->dropoff_lng,
            'fare' => $booking->total_price,
            'status' => 'PENDING',
            'requested_at' => now(),
        ]);

        $this->assertTrue(Trip::where('id', $trip->id)->exists());
        $this->assertEquals('PENDING', $trip->status);
        $this->assertNotNull($trip->driver_id);
    }

    /**
     * Test: CAR ON_DEMAND flow - Ride → Trip (no booking)
     */
    public function test_car_on_demand_flow_direct_trip()
    {
        // 1. Create an ON_DEMAND CAR ride
        $ride = Ride::factory()->create([
            'driver_id' => $this->carDriver->id,
            'vehicle_id' => $this->carVehicle->id,
            'transport_type' => Ride::TRANSPORT_CAR,
            'travel_mode' => Ride::MODE_ON_DEMAND,
            'status' => 'scheduled',
        ]);

        // 2. Verify ride rules only allow trip requests
        $this->assertFalse(RidePolicy::canBook($ride));
        $this->assertTrue(RidePolicy::canRequestTrip($ride));
        $this->assertEquals(RidePolicy::FLOW_TRIP_ONLY, RidePolicy::getAllowedFlow($ride));

        // 3. Passenger creates a direct trip request
        $trip = Trip::create([
            'ride_id' => $ride->id,
            'passenger_id' => $this->passenger->id,
            'pickup_location' => 'Downtown',
            'pickup_lat' => -1.9403,
            'pickup_lng' => 29.8739,
            'dropoff_location' => 'Suburbs',
            'dropoff_lat' => -1.9764,
            'dropoff_lng' => 30.0116,
            'fare' => 7500,
            'status' => 'PENDING',
            'requested_at' => now(),
        ]);

        // 4. Driver auto-assigned
        $assignmentService = app(DriverAssignmentService::class);
        $assigned = $assignmentService->autoAssign($trip, $ride);

        $this->assertNotNull($assigned);
        $this->assertNotNull($assigned->driver_id);
    }

    /**
     * Test: MOTORCYCLE ON_DEMAND flow - Ride → Trip (no booking allowed)
     */
    public function test_motorcycle_on_demand_flow()
    {
        // 1. Create an ON_DEMAND MOTORCYCLE ride
        $ride = Ride::factory()->create([
            'driver_id' => $this->motorcycleDriver->id,
            'vehicle_id' => $this->motorcycleVehicle->id,
            'transport_type' => Ride::TRANSPORT_MOTORCYCLE,
            'travel_mode' => Ride::MODE_ON_DEMAND,
            'status' => 'scheduled',
        ]);

        // 2. Verify ride rules only allow trip requests
        $this->assertFalse(RidePolicy::canBook($ride));
        $this->assertTrue(RidePolicy::canRequestTrip($ride));

        // 3. Verify booking is NOT allowed
        $this->expectException(\Exception::class);
        RidePolicy::assertBookingAllowed($ride);
    }

    /**
     * Test: Cannot create SCHEDULED MOTORCYCLE (policy violation)
     */
    public function test_cannot_create_scheduled_motorcycle()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('MOTORCYCLE must be ON_DEMAND');

        $ride = Ride::make([
            'driver_id' => $this->motorcycleDriver->id,
            'vehicle_id' => $this->motorcycleVehicle->id,
            'transport_type' => Ride::TRANSPORT_MOTORCYCLE,
            'travel_mode' => Ride::MODE_SCHEDULED,
            'ride_type' => Ride::TYPE_LOCAL,
        ]);

        $ride->validateTransportRules();
    }

    /**
     * Test: Cannot create ON_DEMAND BUS (policy violation)
     */
    public function test_cannot_create_on_demand_bus()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('BUS must be SCHEDULED');

        $ride = Ride::make([
            'transport_type' => Ride::TRANSPORT_BUS,
            'travel_mode' => Ride::MODE_ON_DEMAND,
            'ride_type' => Ride::TYPE_LOCAL,
        ]);

        $ride->validateTransportRules();
    }

    /**
     * Test: Trip requires pickup and dropoff locations
     */
    public function test_trip_requires_locations()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Pickup and dropoff locations are required');

        Trip::create([
            'passenger_id' => $this->passenger->id,
            'pickup_location' => null,  // Missing
            'dropoff_location' => 'Airport',
            'status' => 'PENDING',
        ]);
    }

    /**
     * Test: Driver auto-assignment selects compatible vehicle
     */
    public function test_driver_auto_assignment_vehicle_compatibility()
    {
        // Create CAR ride
        $carRide = Ride::factory()->create([
            'transport_type' => Ride::TRANSPORT_CAR,
            'travel_mode' => Ride::MODE_ON_DEMAND,
        ]);

        $trip = Trip::factory()->create([
            'ride_id' => $carRide->id,
            'passenger_id' => $this->passenger->id,
            'status' => 'PENDING',
            'pickup_lat' => -1.9403,
            'pickup_lng' => 29.8739,
            'driver_id' => null,
        ]);

        $assignmentService = app(DriverAssignmentService::class);
        $assigned = $assignmentService->autoAssign($trip, $carRide);

        // Should assign the CAR driver
        $this->assertNotNull($assigned);
        $this->assertEquals($this->carDriver->id, $assigned->driver_id);

        // Motorcycle driver should NOT be assigned to CAR ride
        $this->assertNotEquals($this->motorcycleDriver->id, $assigned->driver_id);
    }

    /**
     * Test: Trip state machine transitions
     */
    public function test_trip_state_machine_transitions()
    {
        // Valid transitions
        $this->assertTrue(TripStateMachine::canTransition(TripStateMachine::REQUESTED, TripStateMachine::ACCEPTED));
        $this->assertTrue(TripStateMachine::canTransition(TripStateMachine::ACCEPTED, TripStateMachine::STARTED));
        $this->assertTrue(TripStateMachine::canTransition(TripStateMachine::STARTED, TripStateMachine::COMPLETED));

        // Invalid transitions
        $this->assertFalse(TripStateMachine::canTransition(TripStateMachine::COMPLETED, TripStateMachine::STARTED));
        $this->assertFalse(TripStateMachine::canTransition(TripStateMachine::REQUESTED, TripStateMachine::COMPLETED));
    }

    /**
     * Test: Ride API response includes rules
     */
    public function test_ride_api_response_includes_rules()
    {
        $scheduledRide = Ride::factory()->create([
            'transport_type' => Ride::TRANSPORT_CAR,
            'travel_mode' => Ride::MODE_SCHEDULED,
        ]);

        $onDemandRide = Ride::factory()->create([
            'transport_type' => Ride::TRANSPORT_CAR,
            'travel_mode' => Ride::MODE_ON_DEMAND,
        ]);

        $scheduledRules = RidePolicy::toApiRules($scheduledRide);
        $this->assertTrue($scheduledRules['can_book']);
        $this->assertFalse($scheduledRules['can_request_trip']);

        $onDemandRules = RidePolicy::toApiRules($onDemandRide);
        $this->assertFalse($onDemandRules['can_book']);
        $this->assertTrue($onDemandRules['can_request_trip']);
    }
}
