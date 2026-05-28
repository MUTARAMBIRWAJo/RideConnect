<?php

namespace Tests\Unit;

use App\Filament\Widgets\Dashboard\OfficerOverviewStats;
use App\Models\Driver;
use App\Models\Ride;
use App\Models\Ticket;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OfficerOverviewStatsTest extends TestCase
{
    use RefreshDatabase;
    public function test_driver_status_uses_fallback_count_when_is_online_column_is_missing(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-05-16 10:00:00'));

        Driver::factory()->create(['status' => 'approved']);
        Driver::factory()->create(['status' => 'approved']);
        Driver::factory()->pending()->create();
        // Create rides without attached drivers/vehicles to avoid creating
        // extra Driver records via related factories during the test.
        Ride::factory()->count(2)->create([
            'created_at' => now(),
            'driver_id' => null,
            'vehicle_id' => null,
        ]);
        Ticket::factory()->count(3)->create(['status' => 'open']);

        Schema::shouldReceive('hasColumn')
            ->once()
            ->with('drivers', 'is_online')
            ->andReturn(false);

        $widget = new class extends OfficerOverviewStats
        {
            public function exposedGetStats(): array
            {
                return $this->getStats();
            }
        };

        $stats = collect($widget->exposedGetStats());

        $driversOnlineStat = $stats->first(
            fn ($stat) => $stat->getLabel() === 'Drivers Online'
        );

        $this->assertNotNull($driversOnlineStat);
        $this->assertSame('2', $driversOnlineStat->getValue());
    }
}
