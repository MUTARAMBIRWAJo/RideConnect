<?php

namespace Tests\Unit;

use App\Models\Trip;
use App\Services\Location\TripLocationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TripLocationServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    #[Test]
    public function it_updates_and_reads_current_location(): void
    {
        $trip = Trip::factory()->create();

        $service = app(TripLocationService::class);
        $payload = $service->updateLocation($trip, -1.95, 30.06, ['polyline' => 'abc']);

        $this->assertSame(-1.95, $payload['current_lat']);
        $this->assertSame(30.06, $payload['current_lng']);

        $current = $service->getCurrentLocation($trip);
        $this->assertSame(-1.95, $current['current_lat']);
        $this->assertSame(30.06, $current['current_lng']);
        $this->assertSame(['polyline' => 'abc'], $current['route_snapshot']);

        $path = 'trip-location-stream/'.$trip->id.'.jsonl';
        $this->assertTrue(Storage::disk('local')->exists($path));
        $this->assertStringContainsString('"trip_id":'.$trip->id, Storage::disk('local')->get($path));
    }
}
