<?php

namespace Tests\Feature;

use App\Models\Driver;
use App\Models\MotorcycleTrip;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\GoogleRouteService;
use App\Services\MatchingService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MotorcycleMatchingServiceTest extends TestCase
{
    public function test_online_motorcycle_driver_is_matched_when_ml_service_is_unavailable(): void
    {
        $this->app->instance(GoogleRouteService::class, new class extends GoogleRouteService
        {
            public function computeRoute(array $origin, array $destination): array
            {
                return [
                    'success' => true,
                    'distance_meters' => 2500,
                    'duration' => '420s',
                    'distance_km' => 2.5,
                    'polyline' => null,
                    'error' => null,
                ];
            }
        });

        Http::fake([
            '*' => Http::response(['message' => 'unavailable'], 503),
        ]);

        $driver = Driver::factory()->create([
            'status' => 'approved',
            'availability_status' => 'online',
            'is_online' => true,
            'last_seen_at' => now(),
            'current_latitude' => -1.9441,
            'current_longitude' => 30.0619,
        ]);

        Vehicle::factory()->create([
            'driver_id' => $driver->id,
            'vehicle_type' => 'motorcycle',
            'is_active' => true,
        ]);

        $trip = MotorcycleTrip::query()->create([
            'passenger_id' => User::factory()->create()->id,
            'pickup_location' => 'Kigali Heights',
            'pickup_lat' => -1.9440,
            'pickup_lng' => 30.0618,
            'dropoff_location' => 'Kigali Convention Centre',
            'dropoff_lat' => -1.9536,
            'dropoff_lng' => 30.0928,
            'estimated_fare' => 1200,
            'currency' => 'RWF',
            'status' => 'MATCHING',
            'requested_at' => now(),
        ]);

        $match = app(MatchingService::class)->matchMotorcycleTrip($trip);

        $this->assertNotNull($match);
        $this->assertSame($driver->id, $match['driver_id']);
        $this->assertTrue($match['metadata']['fallback']);
    }
}
