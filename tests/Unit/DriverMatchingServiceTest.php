<?php

namespace Tests\Unit;

use App\Domain\Matching\DriverMatchingService;
use App\Models\Driver;
use App\Models\Ride;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Tests\TestCase;

class DriverMatchingServiceTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_returns_null_when_no_eligible_driver(): void
    {
        $ride = Ride::factory()->create([
            'transport_type' => Ride::TRANSPORT_MOTORCYCLE,
            'travel_mode' => Ride::MODE_ON_DEMAND,
        ]);

        $driver = Driver::factory()->create(['availability_status' => 'offline']);
        Vehicle::factory()->create(['driver_id' => $driver->id, 'vehicle_type' => 'sedan']);

        $result = app(DriverMatchingService::class)->findBestDriver($ride, new Collection([$driver]));

        $this->assertNull($result);
    }

    /** @test */
    public function it_selects_compatible_online_driver_with_placeholder_scores(): void
    {
        $ride = Ride::factory()->create([
            'transport_type' => Ride::TRANSPORT_CAR,
            'travel_mode' => Ride::MODE_ON_DEMAND,
            'origin_lat' => -1.95,
            'origin_lng' => 30.06,
        ]);

        $farDriver = Driver::factory()->create([
            'availability_status' => 'online',
            'current_latitude' => -1.90,
            'current_longitude' => 30.00,
        ]);
        Vehicle::factory()->create(['driver_id' => $farDriver->id, 'vehicle_type' => 'sedan']);

        $nearDriver = Driver::factory()->create([
            'availability_status' => 'online',
            'current_latitude' => -1.95,
            'current_longitude' => 30.06,
        ]);
        Vehicle::factory()->create(['driver_id' => $nearDriver->id, 'vehicle_type' => 'sedan']);

        $result = app(DriverMatchingService::class)->findBestDriver($ride, new Collection([$farDriver, $nearDriver]));

        $this->assertNotNull($result);
        $this->assertSame(0.0, $result['driver_score']);
        $this->assertSame(0.0, $result['passenger_behavior_score']);
        $this->assertSame(0.0, $result['route_efficiency_score']);
        $this->assertSame($nearDriver->id, $result['driver']->id);
    }
}
