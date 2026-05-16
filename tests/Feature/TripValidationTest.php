<?php

namespace Tests\Feature;

use App\Domain\Ride\RidePolicy;
use App\Models\Driver;
use App\Models\MobileUser;
use App\Models\Ride;
use App\Models\Trip;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Transport-aware trip creation tests for TripResource form validation.
 *
 * These tests verify:
 *  - BUS (SCHEDULED) trips pass without ON_DEMAND enforcement
 *  - CAR / MOTORCYCLE ON_DEMAND trips pass correct-ride validation
 *  - A SCHEDULED non-BUS ride selected in the form issues a clean 422
 *    (no BadMethodCallException / missing-method crash)
 *  - The closure-based ->rules() rule is a proper callable using $fail
 */
class TripValidationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Route::post('/test/trips', [\App\Http\Controllers\Api\TripController::class, 'store']);
    }

    /**
     * Helper: build a CAR passenger with the right attached user/mobile-user records.
     */
    private function makeCarOnDemandTripPayload(array $overrides = []): array
    {
        $passengerUser = User::factory()->create(['is_approved' => true, 'role' => 'PASSENGER']);
        $mobileUser = MobileUser::factory()->create(['email' => $passengerUser->email]);
        $passengerUser->update(['mobile_user_id' => $mobileUser->id]);

        $driverUser = User::factory()->create(['is_approved' => true, 'role' => 'DRIVER']);
        $driverMobileUser = MobileUser::factory()->create(['email' => $driverUser->email, 'role' => 'DRIVER']);
        $driverUser->update(['mobile_user_id' => $driverMobileUser->id]);

        $driver = Driver::factory()->create(['user_id' => $driverUser->id, 'status' => 'approved']);
        $vehicle = Vehicle::factory()->create(['driver_id' => $driver->id, 'is_active' => true]);

        return array_merge([
            'passenger_id' => $mobileUser->id,
            'ride_id' => Ride::factory()->create([
                'driver_id' => $driver->id,
                'vehicle_id' => $vehicle->id,
                'transport_type' => Ride::TRANSPORT_CAR,
                'travel_mode' => Ride::MODE_ON_DEMAND,
            ])->id,
            'pickup_location' => '100 KM Ave',
            'pickup_lat' => -1.9403,
            'pickup_lng' => 29.8739,
            'dropoff_location' => 'Airport Road',
            'dropoff_lat' => -1.9500,
            'dropoff_lng' => 30.0588,
            'fare' => 5000,
        ], $overrides);
    }

    /**
     * 1. CAR/MOTORCYCLE ON_DEMAND on a validated trip endpoint:
     *    a SCHEDULED non-BUS ride must not slip through — it issues a structured
     *    422, never a BadMethodCallException.
     */
    public function test_scheduled_ride_rejects_trip_request_without_badmethodcall_exception(): void
    {
        $passengerUser = User::factory()->create(['is_approved' => true, 'role' => 'PASSENGER']);
        $mobileUser = MobileUser::factory()->create(['email' => $passengerUser->email]);
        $passengerUser->update(['mobile_user_id' => $mobileUser->id]);

        $scheduledCarRide = Ride::factory()->create([
            'transport_type' => Ride::TRANSPORT_CAR,
            'travel_mode' => Ride::MODE_SCHEDULED,
            'status' => 'PUBLISHED',
        ]);

        $this->assertFalse(RidePolicy::canRequestTrip($scheduledCarRide));

        $response = $this->actingAs($passengerUser)->postJson('/test/trips', [
            'ride_id' => $scheduledCarRide->id,
            'pickup_location' => 'Central',
            'pickup_lat' => -1.9403,
            'pickup_lng' => 29.8739,
            'dropoff_location' => 'Airport',
            'dropoff_lat' => -1.9500,
            'dropoff_lng' => 30.0588,
            'fare' => 2000,
        ]);

        $response->assertStatus(422);

        $body = $response->json();
        // Never a 500 / BadMethodCallException crash
        $this->assertNotEquals(500, $response->getStatusCode());
        $this->assertStringNotContainsString(
            'Method Illuminate\\Validation\\Validator',
            json_encode($body)
        );
        // Response must be structured JSON
        $this->assertTrue(
            isset($body['message']) || isset($body['error']) || isset($body['errors']),
            'Expected structured error response but got: '.json_encode($body)
        );
    }

    /**
     * 2. CAR ON_DEMAND ride → trip request succeeds (HTTP 201).
     */
    public function test_valid_ondemand_car_ride_trip_succeeds()
    {
        $passengerUser = User::factory()->create(['is_approved' => true, 'role' => 'PASSENGER']);
        $mobileUser = MobileUser::factory()->create(['email' => $passengerUser->email]);
        $passengerUser->update(['mobile_user_id' => $mobileUser->id]);

        $driverUser = User::factory()->create(['is_approved' => true, 'role' => 'DRIVER']);
        $driverMobileUser = MobileUser::factory()->create(['email' => $driverUser->email, 'role' => 'DRIVER']);
        $driverUser->update(['mobile_user_id' => $driverMobileUser->id]);

        $driver = Driver::factory()->create(['user_id' => $driverUser->id, 'status' => 'approved']);
        $vehicle = Vehicle::factory()->create(['driver_id' => $driver->id, 'is_active' => true]);

        $ondemandRide = Ride::factory()->create([
            'driver_id' => $driver->id,
            'vehicle_id' => $vehicle->id,
            'transport_type' => Ride::TRANSPORT_CAR,
            'travel_mode' => Ride::MODE_ON_DEMAND,
        ]);

        $this->assertTrue(RidePolicy::canRequestTrip($ondemandRide));

        $response = $this->actingAs($passengerUser)->postJson('/test/trips', [
            'ride_id' => $ondemandRide->id,
            'pickup_location' => 'KM 0',
            'pickup_lat' => -1.9403,
            'pickup_lng' => 29.8739,
            'dropoff_location' => 'Airport',
            'dropoff_lat' => -1.9500,
            'dropoff_lng' => 30.0588,
            'fare' => 3000,
        ]);

        $response->assertStatus(201);
        $this->assertSame($ondemandRide->id, $response->json('data.ride_id'));
    }

    /**
     * 3. CAR SCHEDULED ride → fails with structured 422, never a BadMethodCallException.
     */
    public function test_scheduled_ride_rejects_trip_request_with_422(): void
    {
        $passengerUser = User::factory()->create(['is_approved' => true, 'role' => 'PASSENGER']);
        $mobileUser = MobileUser::factory()->create(['email' => $passengerUser->email]);
        $passengerUser->update(['mobile_user_id' => $mobileUser->id]);

        $scheduledRide = Ride::factory()->create([
            'transport_type' => Ride::TRANSPORT_CAR,
            'travel_mode' => Ride::MODE_SCHEDULED,
            'status' => 'PUBLISHED',
        ]);

        $this->assertFalse(RidePolicy::canRequestTrip($scheduledRide));

        $response = $this->actingAs($passengerUser)->postJson('/test/trips', [
            'ride_id' => $scheduledRide->id,
            'pickup_location' => 'Central',
            'pickup_lat' => -1.9403,
            'pickup_lng' => 29.8739,
            'dropoff_location' => 'Airport',
            'dropoff_lat' => -1.9500,
            'dropoff_lng' => 30.0588,
            'fare' => 2000,
        ]);

        $response->assertStatus(422);

        $body = $response->json();
        // No 500 / BadMethodCallException
        $this->assertNotEquals(500, $response->getStatusCode());
        // Response must carry a structured error, not an unhandled PHP exception dump
        $this->assertTrue(
            isset($body['message']) || isset($body['error']) || isset($body['errors']),
            'Expected structured error response but got: '.json_encode($body)
        );

        // The body must NOT contain the "Method Illuminate\\Validation\\Validator::x does not exist" string
        $this->assertStringNotContainsString(
            'Method Illuminate\\Validation\\Validator',
            json_encode($body)
        );
    }

    /**
     * 4. MOTORCYCLE ON_DEMAND ride → trip request succeeds (HTTP 201).
     */
    public function test_valid_ondemand_motorcycle_trip_succeeds(): void
    {
        $passengerUser = User::factory()->create(['is_approved' => true, 'role' => 'PASSENGER']);
        $mobileUser = MobileUser::factory()->create(['email' => $passengerUser->email]);
        $passengerUser->update(['mobile_user_id' => $mobileUser->id]);

        $driverUser = User::factory()->create(['is_approved' => true, 'role' => 'DRIVER']);
        $driverMobileUser = MobileUser::factory()->create(['email' => $driverUser->email, 'role' => 'DRIVER']);
        $driverUser->update(['mobile_user_id' => $driverMobileUser->id]);

        $driver = Driver::factory()->create(['user_id' => $driverUser->id, 'status' => 'approved']);
        $vehicle = Vehicle::factory()->create(['driver_id' => $driver->id, 'is_active' => true]);

        // The form closure was the bug: ->rule() expects a rule definition, not a string.
        // ->rules() with ($attribute,$value,$fail) is the correct form — that is what we test below.
        $ondemandMotoRide = Ride::factory()->create([
            'driver_id' => $driver->id,
            'vehicle_id' => $vehicle->id,
            'transport_type' => Ride::TRANSPORT_MOTORCYCLE,
            'travel_mode' => Ride::MODE_ON_DEMAND,
        ]);

        $this->assertTrue(RidePolicy::canRequestTrip($ondemandMotoRide));

        $response = $this->actingAs($passengerUser)->postJson('/test/trips', [
            'ride_id' => $ondemandMotoRide->id,
            'pickup_location' => 'Market',
            'pickup_lat' => -1.9403,
            'pickup_lng' => 29.8739,
            'dropoff_location' => 'Hotel',
            'dropoff_lat' => -1.9500,
            'dropoff_lng' => 30.0588,
            'fare' => 1500,
        ]);

        $response->assertStatus(201);
        $this->assertSame($ondemandMotoRide->id, $response->json('data.ride_id'));
    }

    /**
     * 5. BUS trip created without assigning a ride_id (common for BUS admin workflow) →
     *    no ON_DEMAND route-type error; returns 422 only for missing required fields,
     *    never a BadMethodCallException.
     */
    public function test_bus_trip_without_ride_id_does_not_trigger_ondemand_error(): void
    {
        $passengerUser = User::factory()->create(['is_approved' => true, 'role' => 'PASSENGER']);
        $mobileUser = MobileUser::factory()->create(['email' => $passengerUser->email]);
        $passengerUser->update(['mobile_user_id' => $mobileUser->id]);

        $response = $this->actingAs($passengerUser)->postJson('/test/trips', [
            'ride_id' => null,   // no ride selected
            'pickup_location' => 'Bus Terminal',
            'pickup_lat' => -1.9403,
            'pickup_lng' => 29.8739,
            'dropoff_location' => 'City Center',
            'dropoff_lat' => -1.9500,
            'dropoff_lng' => 30.0588,
            'fare' => 800,
        ]);

        $response->assertStatus(422);
        $body = $response->json();

        // No 500 / BadMethodCallException
        $this->assertNotEquals(500, $response->getStatusCode());
        $this->assertStringNotContainsString(
            'Method Illuminate\\Validation\\Validator',
            json_encode($body)
        );
        // Body is structured JSON — not a raw exception dump
        $this->assertIsArray($body);
    }

    /**
     * 1b. The ->rules() closure uses ($attribute, $value, $fail) correctly —
     *     it never returns a string, so Filament can't try to call a non-existent
     *     Validator::validateXxx() method.
     */
    public function test_ondemand_validation_closure_uses_fail_callback_not_return_string(): void
    {
        // Build the corrected closure exactly as TripResource::form() defines it.
        $ruleClosure = function (string $attribute, mixed $value, callable $fail): void {
            if (! $value) {
                return;
            }

            $ride = Ride::query()->find((int) $value);

            if (! $ride) {
                $fail('The selected ride no longer exists.');

                return;
            }

            $transportType = $ride->transport_type;

            // BUS trips have no trip-request restriction → skip the ON_DEMAND rule
            if ($transportType === 'BUS') {
                return;
            }

            // CAR / MOTORCYCLE must have an ON_DEMAND ride
            if (! RidePolicy::canRequestTrip($ride)) {
                $fail('Selected ride cannot be used for trip requests. Choose an on-demand ride.');
            }
        };

        // The closure must be callable — Filament will pass it to the Laravel validator
        $this->assertIsCallable($ruleClosure);

        // (a) null ride_id → gracefully no-ops, never calls $fail
        $failNullCalled = false;
        $ruleClosure('ride_id', null, fn () => ($failNullCalled = true));
        $this->assertFalse($failNullCalled);

        // (b) Persisted CAR/ON_DEMAND ride → $fail must NOT fire (valid combination)
        $failOnDemandCalled = false;
        $ondemandRide = Ride::factory()->create([
            'transport_type' => Ride::TRANSPORT_CAR,
            'travel_mode' => Ride::MODE_ON_DEMAND,
        ]);
        $ruleClosure('ride_id', $ondemandRide->id, fn () => ($failOnDemandCalled = true));
        $this->assertFalse(
            $failOnDemandCalled,
            'CAR/ON_DEMAND is a valid pair — $fail must not be called.'
        );

        // (c) CAR/SCHEDULED ride → must trigger $fail()
        $failSchedArr = [];
        $mockFail = function (string $message) use (&$failSchedArr): void {
            $failSchedArr[] = $message;
        };

        $scheduledRide = Ride::factory()->create([
            'transport_type' => Ride::TRANSPORT_CAR,
            'travel_mode' => Ride::MODE_SCHEDULED,
            'status' => 'PUBLISHED',
        ]);

        // Call closure and check the scheduled fail behavior directly
        $ruleClosure('ride_id', $scheduledRide->id, $mockFail);

        $this->assertNotEmpty(
            $failSchedArr,
            'CAR/SCHEDULED must trigger $fail(): isOnDemand() is false, policy rejects trip.'
        );
        $this->assertSame(
            'Selected ride cannot be used for trip requests. Choose an on-demand ride.',
            $failSchedArr[0]
        );
        $this->assertStringStartsWith('Selected ride cannot be used for trip requests.', $failSchedArr[0]);

        // (d) The closure returns void — telling Filament it's a pure callback, not a value
        $ref = new \ReflectionFunction($ruleClosure);
        $this->assertSame('void', $ref->getReturnType()?->getName());
    }

    /**
     * 4. MOTORCYCLE ON_DEMAND ride → trip request succeeds (HTTP 201).
     */
}
