<?php

namespace Tests\Unit;

use App\Filament\Widgets\Dashboard\OfficerOverviewStats;
use Illuminate\Support\Facades\Schema;
use Mockery;
use Tests\TestCase;

class OfficerOverviewStatsTest extends TestCase
{
    public function test_driver_status_uses_fallback_count_when_is_online_column_is_missing(): void
    {
        $rideTodayQuery = Mockery::mock();
        $rideTodayQuery->shouldReceive('count')->once()->andReturn(12);

        $rideForecastQuery = Mockery::mock();
        $rideForecastQuery->shouldReceive('count')->once()->andReturn(14);

        $rideMock = Mockery::mock('alias:App\\Models\\Ride');
        $rideMock->shouldReceive('whereDate')
            ->once()
            ->with('created_at', Mockery::type('string'))
            ->andReturn($rideTodayQuery);
        $rideMock->shouldReceive('whereDate')
            ->once()
            ->with('created_at', '>=', Mockery::type('string'))
            ->andReturn($rideForecastQuery);

        $ticketQuery = Mockery::mock();
        $ticketQuery->shouldReceive('count')->once()->andReturn(3);

        $ticketMock = Mockery::mock('alias:App\\Models\\Ticket');
        $ticketMock->shouldReceive('whereIn')
            ->once()
            ->with('status', ['OPEN', 'open'])
            ->andReturn($ticketQuery);

        $driverQuery = Mockery::mock();
        $driverQuery->shouldReceive('count')->once()->andReturn(2);

        $driverMock = Mockery::mock('alias:App\\Models\\Driver');
        $driverMock->shouldReceive('whereIn')
            ->once()
            ->with('status', ['approved', 'APPROVED', 'active', 'ACTIVE'])
            ->andReturn($driverQuery);

        Schema::shouldReceive('hasColumn')
            ->once()
            ->with('drivers', 'is_online')
            ->andReturn(false);

        $widget = new class extends OfficerOverviewStats {
            public function exposedGetStats(): array
            {
                return $this->getStats();
            }
        };

        $stats = collect($widget->exposedGetStats());

        $driverStatusStat = $stats->first(
            fn ($stat) => $stat->getLabel() === 'Driver Status'
        );

        $this->assertNotNull($driverStatusStat);
        $this->assertSame('2 online', $driverStatusStat->getValue());
    }

    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }
}
