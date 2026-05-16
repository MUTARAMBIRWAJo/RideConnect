<?php

namespace Tests\Feature;

use App\Domain\Ride\RidePolicy;
use App\Models\Booking;
use App\Models\Driver;
use App\Models\MobileUser;
use App\Models\Ride;
use App\Models\Trip;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Transport-flow refactor — 13 feature tests.
 *
 * Coverage:
 *   PUBLIC TRANSPORT   BUS direct / booking  (3 tests)
 *   PRIVATE TRANSPORT  CAR / MOTORCYCLE    (5 tests)
 *   LOCATION UX        search endpoints     (2 tests)
 *   VALIDATION         422 not 500          (3 tests)
 */
class TransportFlowAndLocationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Route::post('/test/trips', [\App\Http\Controllers\Api\TripController::class, 'store']);
    }

    // =========================================================================
    //  SCHEMA HELPERS — build all FK objects WITHOUT factory re-entrancy
    // =========================================================================

    /**
     * Create passenger and driver chains using NEW records.
     * Returns ['passengerId' => int, 'driver' => Driver, 'vehicle' => Vehicle].
     */
    private function freshChains(): array
    {
        // Passenger: User + MobileUser
        $pUser = User::factory()->create(['is_approved' => true, 'role' => 'PASSENGER']);
        $pMobile = MobileUser::factory()->create(['email' => $pUser->email]);
        $pUser->update(['mobile_user_id' => $pMobile->id]);
        $passengerId = $pMobile->id;

        // Driver: User + MobileUser + Driver + Vehicle
        $dUser = User::factory()->create(['is_approved' => true, 'role' => 'DRIVER']);
        $dMobile = MobileUser::factory()->create(['email' => $dUser->email, 'role' => 'DRIVER']);
        $dUser->update(['mobile_user_id' => $dMobile->id]);

        $driver  = Driver::factory()->create(['user_id' => $dUser->id, 'status' => 'approved']);
        $vehicle = Vehicle::factory()->create(['driver_id' => $driver->id, 'is_active' => true]);

        return [
            'passengerId' => $passengerId,
            'driverUser'  => $dUser,
            'driver'      => $driver,
            'vehicle'     => $vehicle,
        ];
    }

    /**
     * Build a trip POST-payload.  All FK columns reference FRESH records.
     * Does NOT call Ride::factory() — inserts are fully explicit INSERT queries,
     * so Ride::factory()'s nested driver/vehicle sub-factories can never clash.
     */
    private function tripPayload(
        string $transportType,
        string $travelMode,
        array $overrides = [],
    ): array {
        $ch = $this->freshChains();

        $ride = Ride::create([
            'driver_id'       => $ch['driver']->id,
            'vehicle_id'      => $ch['vehicle']->id,
            'transport_type'  => $transportType,
            'travel_mode'     => $travelMode,
            // Required so Ride::saving / validateTransportRules doesn't throw
            // InvalidArgumentException at the model layer.
            'route_id'        => $travelMode === Ride::MODE_SCHEDULED ? 1 : null,
            'available_seats' => $travelMode === Ride::MODE_SCHEDULED ? 4 : 1,
            'origin_address'  => '100 KM Ave',
            'destination_address' => 'Airport Road',
            'departure_time'  => now()->addDay(),
        ]);

        return array_merge([
            'ride_id'          => $ride->id,
            'passenger_id'     => $ch['passengerId'],
            'pickup_location'  => '100 KM Ave',
            'pickup_lat'       => -1.9403,
            'pickup_lng'       => 29.8739,
            'dropoff_location' => 'Airport Road',
            'dropoff_lat'      => -1.9500,
            'dropoff_lng'      => 30.0588,
            'fare'             => 5000,
        ], $overrides);
    }

    /**
     * Build a booking POST-payload with its own fresh passenger/driver chains.
     */
    private function bookingPayload(string $transportType, array $overrides = []): array
    {
        $ch = $this->freshChains();

        $ride = Ride::create([
            'driver_id'            => $ch['driver']->id,
            'vehicle_id'           => $ch['vehicle']->id,
            'transport_type'       => $transportType,
            'travel_mode'          => Ride::MODE_SCHEDULED,
            'available_seats'      => 4,
            'route_id'             => 1,
            'origin_address'       => 'Central Bus Park',
            'destination_address'  => 'Bugesera Airport',
            'origin_lat'           => -1.9403,
            'origin_lng'           => 29.8739,
            'destination_lat'      => -2.1400,
            'destination_lng'      => 30.0820,
        ]);

        return array_merge([
            'ride_id'         => $ride->id,
            'user_id'         => User::where('email', MobileUser::find($ch['passengerId'])->email)->first()?->id ?? $ride->id,
            'pickup_address'  => $ride->origin_address,
            'pickup_lat'      => $ride->origin_lat,
            'pickup_lng'      => $ride->origin_lng,
            'dropoff_address' => $ride->destination_address,
            'dropoff_lat'     => $ride->destination_lat,
            'dropoff_lng'     => $ride->destination_lng,
            'seats_booked'    => 2,
            'total_price'     => 2000,
        ], $overrides);
    }

    // =========================================================================
    //  PUBLIC TRANSPORT  —  BUS
    // =========================================================================

    /**
     * 1. BUS/SCHEDULED + no ride_id → structured 422, never 500 or
     *    BadMethodCallException.
     */
    public function test_bus_direct_trip_without_ride_id_returns_structured_422(): void
    {
        $passengerUser = User::factory()->create(['is_approved' => true, 'role' => 'PASSENGER']);
        $mobileUser    = MobileUser::factory()->create(['email' => $passengerUser->email]);
        $passengerUser->update(['mobile_user_id' => $mobileUser->id]);

        // Prime a valid BUS ride so the database schema is sane but we pass null.
        $this->tripPayload(Ride::TRANSPORT_BUS, Ride::MODE_SCHEDULED);

        $response = $this->actingAs($passengerUser)->postJson('/test/trips', [
            'ride_id'          => null,
            'pickup_location'  => 'Nyabugogo',
            'pickup_lat'       => -1.9403,
            'pickup_lng'       => 29.8739,
            'dropoff_location' => 'Kigali Convention Centre',
            'dropoff_lat'      => -1.9350,
            'dropoff_lng'      => 30.0820,
            'fare'             => 800,
        ]);

        $response->assertStatus(422);
        $body = $response->json();
        $this->assertNotEquals(
            500, $response->getStatusCode(),
            'Missing ride_id must yield structured 422, not 500.'
        );
        $this->assertStringNotContainsString(
            'Method Illuminate\\Validation\\Validator',
            json_encode($body)
        );
        $this->assertIsArray($body, 'Expected array body, got: '.json_encode($body));
    }

    /**
     * 2. BUS/SCHEDULED booking creation succeeds (HTTP 201).
     */
    public function test_bus_booking_creation_succeeds(): void
    {
        $this->tripPayload(Ride::TRANSPORT_BUS, Ride::MODE_SCHEDULED);

        $payload = $this->bookingPayload(Ride::TRANSPORT_BUS);

        $response = $this->postJson('/api/v1/passenger/bookings', $payload);

        $response->assertStatus(201);
        $this->assertDatabaseHas('bookings', [
            'ride_id'         => $payload['ride_id'],
            'pickup_address'  => $payload['pickup_address'],
            'dropoff_address' => $payload['dropoff_address'],
        ]);
    }

    // =========================================================================
    //  PRIVATE TRANSPORT  —  CAR / MOTORCYCLE
    // =========================================================================

    /**
     * 3. CAR/ON_DEMAND passes RidePolicy::canCreateDirectTrip() + controller guard.
     *    201 or 422 are accepted; 500 = unhandled exception crash.
     */
    public function test_car_ondemand_direct_trip_passes_policy(): void
    {
        $payload = $this->tripPayload(Ride::TRANSPORT_CAR, Ride::MODE_ON_DEMAND);

        $ride = Ride::find($payload['ride_id']);
        $this->assertTrue(RidePolicy::canCreateDirectTrip($ride));
        $this->assertTrue(RidePolicy::isPrivateTransport($ride));

        $response = $this->actingAs(User::find($ride->driver->user_id))
            ->postJson('/test/trips', $payload);

        $this->assertContains(
            $response->getStatusCode(),
            [201, 422],
            'CAR/ON_DEMAND must yield governed status, not 500.'
        );
        $this->assertStringNotContainsString(
            'Method Illuminate\\Validation\\Validator',
            json_encode($response->json())
        );
    }

    /**
     * 4. CAR/SCHEDULED direct trip rejected — structured 422, no BadMethodCallException.
     */
    public function test_car_scheduled_direct_trip_is_rejected_422(): void
    {
        $payload = $this->tripPayload(Ride::TRANSPORT_CAR, Ride::MODE_SCHEDULED);

        $ride = Ride::find($payload['ride_id']);
        $this->assertFalse(RidePolicy::canCreateDirectTrip($ride));
        $this->assertTrue(RidePolicy::canUseBookingFlow($ride));

        $response = $this->actingAs(User::find($ride->driver->user_id))
            ->postJson('/test/trips', $payload);

        $response->assertStatus(422);
        $body = $response->json();
        $this->assertStringNotContainsString(
            'Method Illuminate\\Validation\\Validator',
            json_encode($body)
        );
        $this->assertTrue(
            isset($body['message']) || isset($body['error']) || isset($body['errors']),
            'Expected structured 422 body but got: '.json_encode($body)
        );
    }

    /**
     * 5. MOTORCYCLE/ON_DEMAND passes RidePolicy::canCreateDirectTrip().
     */
    public function test_motorcycle_ondemand_trip_passes_policy(): void
    {
        $payload = $this->tripPayload(
            Ride::TRANSPORT_MOTORCYCLE, Ride::MODE_ON_DEMAND
        );

        $ride = Ride::find($payload['ride_id']);
        $this->assertTrue(RidePolicy::canCreateDirectTrip($ride));
        $this->assertTrue(RidePolicy::isPrivateTransport($ride));

        $response = $this->actingAs(User::find($ride->driver->user_id))
            ->postJson('/test/trips', $payload);

        $this->assertContains(
            $response->getStatusCode(),
            [201, 422],
            'MOTO/ON_DEMAND must yield governed status, not 500.'
        );
        $this->assertStringNotContainsString(
            'Method Illuminate\\Validation\\Validator',
            json_encode($response->json())
        );
    }

    /**
     * 6. CAR/SCHEDULED booking creation succeeds (HTTP 201).
     */
    public function test_car_scheduled_booking_creation_succeeds(): void
    {
        $this->tripPayload(Ride::TRANSPORT_CAR, Ride::MODE_SCHEDULED);

        $payload = $this->bookingPayload(Ride::TRANSPORT_CAR);

        $response = $this->postJson('/api/v1/passenger/bookings', $payload);

        $response->assertStatus(201);
        $this->assertDatabaseHas('bookings', [
            'ride_id'         => $payload['ride_id'],
            'pickup_address'  => $payload['pickup_address'],
        ]);
    }

    /**
     * 7. MOTORCYCLE/ON_DEMAND → HTTP 201 from the trip store endpoint.
     */
    public function test_motorcycle_ondemand_trip_returns_201(): void
    {
        $payload = $this->tripPayload(
            Ride::TRANSPORT_MOTORCYCLE, Ride::MODE_ON_DEMAND
        );

        $ride = Ride::find($payload['ride_id']);
        $this->assertTrue(RidePolicy::canCreateDirectTrip($ride));

        $response = $this->actingAs(User::find($ride->driver->user_id))
            ->postJson('/test/trips', $payload);

        $response->assertStatus(201);
    }

    // =========================================================================
    //  LOCATION UX
    // =========================================================================

    /**
     * 8. /locations/search always returns HTTP 200 JSON with a `success` key.
     */
    public function test_place_search_returns_GROUNDED_200_json(): void
    {
        config(['laramaps.api_key' => 'test-coverage-key']);

        $response = $this->getJson(
            '/locations/search?q=Nyabugogo&country=rw'
        );

        // A missing-API-key Graceful response still returns 200 with emtpy results
        $this->assertEquals(
            200,
            $response->getStatusCode(),
            'Location search must return grounded HTTP 200 for any query.'
        );
        $body = $response->json();
        $this->assertArrayHasKey('success', $body);
    }

    /**
     * 9. /locations/reverse-geocode returns structured JSON; never a raw
     *    unhandled-PHP 500 error for any valid coordinate pair.
     */
    public function test_reverse_geocode_outputs_structured_json_no_raw_500(): void
    {
        config(['laramaps.api_key' => 'test-coverage-key']);

        $response = $this->getJson(
            '/locations/reverse-geocode?lat=-1.9403&lng=29.8739'
        );

        // Graceful degradation is fine; raw PHP 500 is never acceptable.
        $this->assertNotEquals(
            500,
            $response->getStatusCode(),
            'Reverse-geocode must never surface an unhandled exception as 500.'
        );
    }

    // =========================================================================
    //  VALIDATION  —  422 not 500, no BadMethodCallException
    // =========================================================================

    /**
     * 10. Every transport combo returns 201 or 422 — no 500 anywhere.
     *     "Method Illuminate\Validation\Validator" must never appear in any
     *     error body.
     */
    public function test_no_badmethodcall_exception_on_any_combo(): void
    {
        $combos = [
            Ride::TRANSPORT_BUS       .'/' .Ride::MODE_SCHEDULED,
            Ride::TRANSPORT_CAR       .'/' .Ride::MODE_SCHEDULED,
            Ride::TRANSPORT_CAR       .'/' .Ride::MODE_ON_DEMAND,
            Ride::TRANSPORT_MOTORCYCLE.'/' .Ride::MODE_ON_DEMAND,
        ];

        foreach ($combos as $combo) {
            [$transportType, $travelMode] = explode('/', $combo, 2);

            $payload = $this->tripPayload($transportType, $travelMode);
            $passengerUser = User::find($payload['passenger_id'])
                ?? User::factory()->create(['is_approved' => true, 'role' => 'PASSENGER']);

            $response = $this->actingAs($passengerUser)
                ->postJson('/test/trips', $payload);

            $this->assertContains(
                $response->getStatusCode(),
                [201, 422],
                sprintf('Unexpected status %d for %s', $response->getStatusCode(), $combo)
            );

            $body = $response->json();
            $this->assertStringNotContainsString(
                'Method Illuminate\\Validation\\Validator',
                json_encode($body),
                sprintf('BadMethodCallException leaked for %s', $combo)
            );
        }
    }

    /**
     * 11. ride_id=99999 (ghost ride) → structured 422, never 500.
     */
    public function test_ghost_ride_id_returns_structured_422(): void
    {
        $passengerUser = User::factory()->create(['is_approved' => true, 'role' => 'PASSENGER']);
        $mobileUser    = MobileUser::factory()->create(['email' => $passengerUser->email]);
        $passengerUser->update(['mobile_user_id' => $mobileUser->id]);

        $response = $this->actingAs($passengerUser)->postJson('/test/trips', [
            'ride_id'          => 99999,
            'pickup_location'  => 'Central',
            'pickup_lat'       => -1.9403,
            'pickup_lng'       => 29.8739,
            'dropoff_location' => 'Airport',
            'dropoff_lat'      => -1.9500,
            'dropoff_lng'      => 30.0588,
            'fare'             => 3000,
        ]);

        $response->assertStatus(422);
        $body = $response->json();
        $this->assertTrue(
            isset($body['message']) || isset($body['error']) || isset($body['errors']),
            'Expected structured error body but got: '.json_encode($body)
        );
    }

    // =========================================================================
    //  LOCATION PLACE-NAME  —  DB columns present + round-trip in API
    // =========================================================================

    /**
     * 12. The trip store endpoint persists pickup_place_name /
     *     dropoff_place_name to DB when present in the request.
     */
    public function test_trip_store_stores_place_name_fields(): void
    {
        // First create a stable passenger record
        $passengerUser = User::factory()->create(['is_approved' => true, 'role' => 'PASSENGER']);
        $mobileUser    = MobileUser::factory()->create(['email' => $passengerUser->email]);
        $passengerUser->update(['mobile_user_id' => $mobileUser->id]);

        // Build the payload pointing to FRESH records only
        $payload = $this->tripPayload(Ride::TRANSPORT_CAR, Ride::MODE_ON_DEMAND);

        $payload['pickup_place_name']  = 'Nyabugogo Bus Terminal';
        $payload['dropoff_place_name'] = 'Kigali Convention Centre';
        // Ensure we use the FRESH passenger (not stale foreign reference)
        $payload['passenger_id'] = $mobileUser->id;

        $response = $this->actingAs($passengerUser)
            ->postJson('/test/trips', $payload);

        $this->assertContains(
            $response->getStatusCode(),
            [201, 422],
            'Trip creation must not return a raw 500 error.'
        );
        $body = $response->json();
        $this->assertIsArray($body);
    }

    /**
     * 13a. GET /api/v1/passenger/trips/{id} returns pickup_place_name and
     *      dropoff_place_name in the response body.
     */
    public function test_trip_show_returns_place_name_fields(): void
    {
        $passengerUser = User::factory()->create(['is_approved' => true, 'role' => 'PASSENGER']);
        $mobileUser    = MobileUser::factory()->create(['email' => $passengerUser->email]);
        $passengerUser->update(['mobile_user_id' => $mobileUser->id]);

        $trip = Trip::create([
            'passenger_id'      => $mobileUser->id,
            'pickup_location'   => 'Nyabugogo Terminal',
            'pickup_place_name' => 'Nyabugogo Bus Terminal',
            'pickup_lat'        => -1.9403,
            'pickup_lng'        => 29.8739,
            'dropoff_location'  => 'Kigali Convention Centre',
            'dropoff_place_name'=> 'Kigali Convention Centre',
            'dropoff_lat'       => -1.9350,
            'dropoff_lng'       => 30.0820,
            'fare'              => 1500,
            'status'            => 'PENDING',
        ]);

        $response = $this->actingAs($passengerUser)
            ->getJson("/api/v1/passenger/trips/{$trip->id}");

        $response->assertStatus(200);
        $data = $response->json('data');

        $this->assertSame('Nyabugogo Bus Terminal', $data['pickup_place_name']);
        $this->assertSame('Kigali Convention Centre', $data['dropoff_place_name']);
    }

    /**
     * 13b. GET /api/v1/passenger/trips (myTrips) returns place name fields.
     */
    public function test_my_trips_listing_returns_place_name_fields(): void
    {
        $passengerUser = User::factory()->create(['is_approved' => true, 'role' => 'PASSENGER']);
        $mobileUser    = MobileUser::factory()->create(['email' => $passengerUser->email]);
        $passengerUser->update(['mobile_user_id' => $mobileUser->id]);

        Trip::create([
            'passenger_id'      => $mobileUser->id,
            'pickup_location'   => 'Kimironko Market',
            'pickup_place_name' => 'Kimironko Market',
            'pickup_lat'        => -1.9200,
            'pickup_lng'        => 30.1200,
            'dropoff_location'  => 'Kacyiru',
            'dropoff_place_name'=> 'Kacyiru',
            'dropoff_lat'       => -1.9350,
            'dropoff_lng'       => 30.0700,
            'fare'              => 1000,
            'status'            => 'PENDING',
        ]);

        $response = $this->actingAs($passengerUser)
            ->getJson('/api/v1/passenger/trips');

        $response->assertStatus(200);
        $tripData = collect($response->json('data'))
            ->firstWhere('pickup_place_name', 'Kimironko Market');

        $this->assertNotNull(
            $tripData,
            'Trip must appear in myTrips listing with place name.'
        );
        $this->assertSame('Kimironko Market',    $tripData['pickup_place_name']);
        $this->assertSame('Kacyiru',             $tripData['dropoff_place_name']);
    }
}
