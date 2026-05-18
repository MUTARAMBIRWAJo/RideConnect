<?php

namespace Tests\Feature\PublicBus;

use App\Models\BusRouteAssignment;
use App\Models\CorridorStop;
use App\Models\Driver;
use App\Models\TransportCorridor;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class OfficerBusManagementTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected User $officer;
    protected Driver $driverProfile;
    protected Vehicle $bus;
    protected TransportCorridor $corridor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setupOfficerAndAssets();
    }

    protected function setupOfficerAndAssets(): void
    {
        $this->officer = User::factory()->create([
            'role' => 'OFFICER',
            'is_approved' => true,
        ]);

        $this->driverProfile = Driver::factory()->create([
            'status' => 'approved',
            'availability_status' => 'available',
        ]);

        $this->bus = Vehicle::factory()->create([
            'driver_id' => $this->driverProfile->id,
            'vehicle_type' => 'van',
        ]);

        $this->corridor = TransportCorridor::create([
            'corridor_code' => 'ROUTE-001',
            'corridor_name' => 'Route 1: Downtown-Airport',
            'transport_type' => 'BUS',
            'status' => 'active',
            'estimated_duration_minutes' => 35,
        ]);
    }

    public function test_officer_can_create_corridors(): void
    {
        Sanctum::actingAs($this->officer);

        $response = $this->postJson('/api/v1/officer/public-bus/corridors', [
            'corridor_code' => 'ROUTE-005',
            'corridor_name' => 'Route 5: Westlands-Eastlands',
            'estimated_duration_minutes' => 45,
            'transport_type' => 'BUS',
            'status' => 'active',
        ]);

        $response->assertCreated()
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'corridor_code',
                    'corridor_name',
                    'transport_type',
                    'status',
                ],
            ]);

        $this->assertDatabaseHas('transport_corridors', [
            'corridor_code' => 'ROUTE-005',
            'corridor_name' => 'Route 5: Westlands-Eastlands',
        ]);
    }

    public function test_officer_can_create_stops(): void
    {
        Sanctum::actingAs($this->officer);

        $response = $this->postJson('/api/v1/officer/public-bus/stops', [
            'corridor_id' => $this->corridor->id,
            'stop_name' => 'Central Park Stop',
            'stop_order' => 2,
            'latitude' => -1.2850,
            'longitude' => 36.8050,
            'is_major_terminal' => false,
        ]);

        $response->assertCreated()
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'corridor_id',
                    'stop_name',
                    'stop_order',
                    'latitude',
                    'longitude',
                    'is_major_terminal',
                ],
            ]);

        $this->assertDatabaseHas('corridor_stops', [
            'corridor_id' => $this->corridor->id,
            'stop_name' => 'Central Park Stop',
        ]);
    }

    public function test_officer_can_assign_driver_to_route(): void
    {
        Sanctum::actingAs($this->officer);

        $response = $this->postJson('/api/v1/officer/public-bus/assign-driver', [
            'driver_id' => $this->driverProfile->id,
            'bus_id' => $this->bus->id,
            'corridor_id' => $this->corridor->id,
        ]);

        $response->assertCreated()
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'bus_id',
                    'corridor_id',
                    'driver_id',
                    'status',
                ],
            ]);

        $this->assertDatabaseHas('bus_route_assignments', [
            'driver_id' => $this->driverProfile->id,
            'corridor_id' => $this->corridor->id,
            'bus_id' => $this->bus->id,
        ]);
    }

    public function test_officer_can_view_live_monitoring(): void
    {
        Sanctum::actingAs($this->officer);

        BusRouteAssignment::create([
            'bus_id' => $this->bus->id,
            'corridor_id' => $this->corridor->id,
            'driver_id' => $this->driverProfile->id,
            'status' => 'active',
        ]);

        $response = $this->getJson('/api/v1/officer/public-bus/live-monitoring');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'corridor' => [
                            'id',
                            'corridor_code',
                            'corridor_name',
                        ],
                        'stop_count',
                        'active_bus_count',
                        'seat_utilization',
                        'active_buses',
                    ],
                ],
            ]);
    }
}
