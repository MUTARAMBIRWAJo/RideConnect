<?php

namespace Tests\Feature;

use App\Domain\Ride\RidePolicy;
use App\Models\Ride;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class RideApiContractTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function ride_api_always_exposes_ride_rules(): void
    {
        $driver = \App\Models\Driver::factory()->create();
        $vehicle = \App\Models\Vehicle::factory()->create(['driver_id' => $driver->id, 'vehicle_type' => 'sedan']);

        $scheduledRide = Ride::factory()->create([
            'driver_id' => $driver->id,
            'vehicle_id' => $vehicle->id,
            'transport_type' => Ride::TRANSPORT_CAR,
            'travel_mode' => Ride::MODE_SCHEDULED,
        ]);

        $onDemandRide = Ride::factory()->create([
            'driver_id' => $driver->id,
            'vehicle_id' => $vehicle->id,
            'transport_type' => Ride::TRANSPORT_CAR,
            'travel_mode' => Ride::MODE_ON_DEMAND,
        ]);

        $user = User::factory()->create(['is_approved' => true]);
        $response = $this->actingAs($user)->getJson('/api/v1/passenger/rides/available');

        $response->assertOk();
        $response->assertJsonStructure([
            'data' => [[
                'id',
                'transport_type',
                'travel_mode',
                'ride_rules' => ['can_book', 'can_request_trip', 'allowed_flow'],
            ]],
        ]);

        $this->assertSame(RidePolicy::FLOW_BOOKING_ONLY, RidePolicy::toApiRules($scheduledRide)['allowed_flow']);
        $this->assertSame(RidePolicy::FLOW_TRIP_ONLY, RidePolicy::toApiRules($onDemandRide)['allowed_flow']);
    }
}
