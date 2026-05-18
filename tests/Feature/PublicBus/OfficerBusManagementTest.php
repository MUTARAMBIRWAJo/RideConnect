<?php

namespace Tests\Feature\PublicBus;

use App\Models\BusRouteAssignment;
use App\Models\MobileUser;
use App\Models\TransportCorridor;
use App\Models\TransportStop;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class OfficerBusManagementTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected MobileUser $officer;
    protected MobileUser $driver;
    protected Vehicle $bus;
    protected TransportCorridor $corridor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setupOfficerAndAssets();
    }

    protected function setupOfficerAndAssets(): void
    {
        $this->officer = MobileUser::factory()->create(['role' => 'OFFICER']);
        $this->driver = MobileUser::factory()->create(['role' => 'DRIVER']);
        $this->bus = Vehicle::factory()->create([
            'driver_id' => $this->driver->id,
            'type' => 'bus',
            'plate_number' => 'TXL-002B',
            'capacity' => 45,
        ]);

        $this->corridor = TransportCorridor::factory()->create([
            'name' => 'Route 1: Downtown-Airport',
            'is_active' => true,
        ]);
    }

    public function test_officer_can_create_corridors(): void
    {
        Sanctum::actingAs($this->officer);

        $response = $this->postJson('/api/v1/officer/public-bus/corridors', [
            'name' => 'Route 5: Westlands-Eastlands',
            'start_point' => 'Westlands Commercial Centre',
            'end_point' => 'Eastlands Mall',
            'estimated_duration_minutes' => 45,
            'is_active' => true,
        ]);

        $response->assertCreated()
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'name',
                    'start_point',
                    'end_point',
                    'is_active',
                ],
            ]);

        $this->assertDatabaseHas('transport_corridors', [
            'name' => 'Route 5: Westlands-Eastlands',
        ]);
    }

    public function test_officer_can_create_stops(): void
    {
        Sanctum::actingAs($this->officer);

        $response = $this->postJson('/api/v1/officer/public-bus/stops', [
            'corridor_id' => $this->corridor->id,
            'name' => 'Central Park Stop',
            'latitude' => -1.2850,
            'longitude' => 36.8050,
            'order_index' => 2,
        ]);

        $response->assertCreated()
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'corridor_id',
                    'name',
                    'latitude',
                    'longitude',
                    'order_index',
                ],
            ]);

        $this->assertDatabaseHas('transport_stops', [
            'corridor_id' => $this->corridor->id,
            'name' => 'Central Park Stop',
        ]);
    }

    public function test_officer_can_assign_driver_to_route(): void
    {
        Sanctum::actingAs($this->officer);

        $response = $this->postJson('/api/v1/officer/public-bus/assign-driver', [
            'driver_id' => $this->driver->id,
            'vehicle_id' => $this->bus->id,
            'corridor_id' => $this->corridor->id,
            'start_date' => now()->toDateString(),
            'end_date' => now()->addMonth()->toDateString(),
        ]);

        $response->assertCreated()
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'driver_id',
                    'vehicle_id',
                    'corridor_id',
                    'status',
                    'start_date',
                    'end_date',
                ],
            ]);

        $this->assertDatabaseHas('bus_route_assignments', [
            'driver_id' => $this->driver->id,
            'corridor_id' => $this->corridor->id,
        ]);
    }

    public function test_officer_can_view_live_monitoring(): void
    {
        Sanctum::actingAs($this->officer);

        $assignment = BusRouteAssignment::factory()->create([
            'corridor_id' => $this->corridor->id,
            'vehicle_id' => $this->bus->id,
            'status' => 'active',
        ]);

        $response = $this->getJson('/api/v1/officer/public-bus/live-monitoring');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'assignment_id',
                        'vehicle_plate',
                        'driver_name',
                        'corridor_name',
                        'current_position',
                        'boarding_count',
                        'available_seats',
                        'status',
                    ],
                ],
            ]);
    }
}
