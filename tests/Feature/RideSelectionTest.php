<?php

namespace Tests\Feature;

use App\Models\Ride;
use App\Models\Driver;
use App\Models\Vehicle;
use App\Models\User;
use Tests\TestCase;

class RideSelectionTest extends TestCase
{
    /**
     * Test that rides are returned sorted alphabetically by origin address
     */
    public function test_rides_are_sorted_alphabetically(): void
    {
        $driver = Driver::factory()->create();
        
        // Create rides in non-alphabetical order
        $rideHuye = Ride::factory()->create([
            'driver_id' => $driver->id,
            'origin_address' => 'Huye',
            'destination_address' => 'Kigali',
            'transport_type' => 'BUS',
            'travel_mode' => 'SCHEDULED',
            'route_id' => $this->createRouteId(),
        ]);
        
        $rideKigali = Ride::factory()->create([
            'driver_id' => $driver->id,
            'origin_address' => 'Kigali',
            'destination_address' => 'Musanze',
            'transport_type' => 'BUS',
            'travel_mode' => 'SCHEDULED',
            'route_id' => $this->createRouteId(),
        ]);
        
        $rideGitarama = Ride::factory()->create([
            'driver_id' => $driver->id,
            'origin_address' => 'Gitarama',
            'destination_address' => 'Muhanga',
            'transport_type' => 'CAR',
            'travel_mode' => 'ON_DEMAND',
        ]);
        
        // API endpoint
        $response = $this->get('/api/rides');
        
        $response->assertStatus(200);
        $data = $response->json('data');
        
        // Assert alphabetical ordering
        $this->assertEquals('Gitarama', $data[0]['origin']['address']);
        $this->assertEquals('Huye', $data[1]['origin']['address']);
        $this->assertEquals('Kigali', $data[2]['origin']['address']);
    }
    
    /**
     * Test filtering rides by transport type
     */
    public function test_filter_by_transport_type(): void
    {
        $driver = Driver::factory()->create();
        
        $busBide = Ride::factory()->create([
            'driver_id' => $driver->id,
            'transport_type' => 'BUS',
            'travel_mode' => 'SCHEDULED',
            'route_id' => $this->createRouteId(),
        ]);
        
        $carRide = Ride::factory()->create([
            'driver_id' => $driver->id,
            'transport_type' => 'CAR',
            'travel_mode' => 'ON_DEMAND',
        ]);
        
        $motorcycleRide = Ride::factory()->create([
            'driver_id' => $driver->id,
            'transport_type' => 'MOTORCYCLE',
            'travel_mode' => 'ON_DEMAND',
        ]);
        
        // Filter by BUS
        $response = $this->get('/api/rides?transport_type=BUS');
        $response->assertStatus(200);
        $data = $response->json('data');
        
        $this->assertCount(1, $data);
        $this->assertEquals('BUS', $data[0]['transport_type']);
        
        // Filter by CAR
        $response = $this->get('/api/rides?transport_type=CAR');
        $response->assertStatus(200);
        $data = $response->json('data');
        
        $this->assertCount(1, $data);
        $this->assertEquals('CAR', $data[0]['transport_type']);
        
        // Filter by MOTORCYCLE
        $response = $this->get('/api/rides?transport_type=MOTORCYCLE');
        $response->assertStatus(200);
        $data = $response->json('data');
        
        $this->assertCount(1, $data);
        $this->assertEquals('MOTORCYCLE', $data[0]['transport_type']);
    }
    
    /**
     * Test that transport type and travel mode are included in API responses
     */
    public function test_api_includes_transport_type_and_travel_mode(): void
    {
        $driver = Driver::factory()->create();
        
        $ride = Ride::factory()->create([
            'driver_id' => $driver->id,
            'transport_type' => 'BUS',
            'travel_mode' => 'SCHEDULED',
            'route_id' => $this->createRouteId(),
        ]);
        
        $response = $this->get('/api/rides');
        
        $response->assertStatus(200);
        $data = $response->json('data');
        
        $this->assertNotEmpty($data);
        $this->assertArrayHasKey('transport_type', $data[0]);
        $this->assertArrayHasKey('travel_mode', $data[0]);
        $this->assertEquals('BUS', $data[0]['transport_type']);
        $this->assertEquals('SCHEDULED', $data[0]['travel_mode']);
    }
    
    /**
     * Test that rides are sorted by destination when origins are the same
     */
    public function test_rides_sorted_by_destination_when_origins_same(): void
    {
        $driver = Driver::factory()->create();
        
        // Create rides with same origin but different destinations
        $rideZurich = Ride::factory()->create([
            'driver_id' => $driver->id,
            'origin_address' => 'Kigali',
            'destination_address' => 'Zurich',
            'transport_type' => 'CAR',
            'travel_mode' => 'ON_DEMAND',
        ]);
        
        $rideAntwerp = Ride::factory()->create([
            'driver_id' => $driver->id,
            'origin_address' => 'Kigali',
            'destination_address' => 'Antwerp',
            'transport_type' => 'CAR',
            'travel_mode' => 'ON_DEMAND',
        ]);
        
        $rideMuhanga = Ride::factory()->create([
            'driver_id' => $driver->id,
            'origin_address' => 'Kigali',
            'destination_address' => 'Muhanga',
            'transport_type' => 'CAR',
            'travel_mode' => 'ON_DEMAND',
        ]);
        
        $response = $this->get('/api/rides');
        
        $response->assertStatus(200);
        $data = $response->json('data');
        
        // Find rides from Kigali
        $kigaliRides = array_filter($data, fn($ride) => $ride['origin']['address'] === 'Kigali');
        
        // Assert alphabetical ordering of destinations
        $destinations = array_map(fn($ride) => $ride['destination']['address'], $kigaliRides);
        $this->assertEquals(['Antwerp', 'Muhanga', 'Zurich'], array_values($destinations));
    }
    
    /**
     * Test API response structure for Flutter client
     */
    public function test_api_response_structure_for_flutter(): void
    {
        $driver = Driver::factory()->create();
        
        $ride = Ride::factory()->create([
            'driver_id' => $driver->id,
            'transport_type' => 'BUS',
            'travel_mode' => 'SCHEDULED',
            'route_id' => $this->createRouteId(),
        ]);
        
        $response = $this->get('/api/rides');
        
        $response->assertStatus(200);
        $data = $response->json('data');
        
        $this->assertNotEmpty($data);
        $ride = $data[0];
        
        // Check required fields for Flutter
        $this->assertArrayHasKey('id', $ride);
        $this->assertArrayHasKey('origin', $ride);
        $this->assertArrayHasKey('destination', $ride);
        $this->assertArrayHasKey('transport_type', $ride);
        $this->assertArrayHasKey('travel_mode', $ride);
        $this->assertArrayHasKey('available_seats', $ride);
        $this->assertArrayHasKey('price_per_seat', $ride);
        $this->assertArrayHasKey('ride_rules', $ride);
        
        // Check nested structure
        $this->assertArrayHasKey('address', $ride['origin']);
        $this->assertArrayHasKey('lat', $ride['origin']);
        $this->assertArrayHasKey('lng', $ride['origin']);
        $this->assertArrayHasKey('address', $ride['destination']);
        $this->assertArrayHasKey('lat', $ride['destination']);
        $this->assertArrayHasKey('lng', $ride['destination']);
    }

    private function createRouteId(): int
    {
        $zoneId = \Illuminate\Support\Facades\DB::table('zones')->insertGetId([
            'name' => 'Selection Zone ' . uniqid(),
            'code' => 'SZ' . substr(uniqid(), -6),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $corridor = \App\Models\Corridor::query()->create([
            'code' => 'SC' . substr(uniqid(), -6),
            'name' => 'Selection Corridor',
            'kinyarwanda_name' => 'Selection Corridor',
            'start_zone_id' => $zoneId,
            'end_zone_id' => $zoneId,
            'base_fare' => 100,
            'price_per_km' => 0,
        ]);

        return \App\Models\TransportRoute::query()->create([
            'corridor_id' => $corridor->id,
            'route_code' => 'SR' . substr(uniqid(), -6),
            'name' => 'Selection Route',
            'origin' => 'Origin',
            'destination' => 'Destination',
            'is_active' => true,
        ])->id;
    }
}
