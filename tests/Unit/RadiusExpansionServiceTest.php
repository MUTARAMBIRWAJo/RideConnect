<?php

namespace Tests\Unit;

use App\Services\Matching\RadiusExpansionService;
use PHPUnit\Framework\TestCase;

class RadiusExpansionServiceTest extends TestCase
{
    private RadiusExpansionService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new RadiusExpansionService();
    }

    /** @dataProvider elapsedProvider */
    public function test_radius_for_elapsed_seconds(int $seconds, float $expected): void
    {
        $this->assertSame($expected, $this->service->radiusForElapsedSeconds($seconds));
    }

    public static function elapsedProvider(): array
    {
        return [
            'just started' => [0, 5.0],
            'under 15s' => [14, 5.0],
            '15s boundary' => [15, 8.0],
            'under 30s' => [29, 8.0],
            '30s boundary' => [30, 12.0],
            'under 45s' => [44, 12.0],
            '45s boundary' => [45, 20.0],
            'under 60s' => [59, 20.0],
            '60s boundary' => [60, 30.0],
            'long wait' => [600, 30.0],
        ];
    }

    public function test_radius_for_retry_walks_the_schedule_and_caps(): void
    {
        $this->assertSame(5.0, $this->service->radiusForRetry(0));
        $this->assertSame(8.0, $this->service->radiusForRetry(1));
        $this->assertSame(12.0, $this->service->radiusForRetry(2));
        $this->assertSame(20.0, $this->service->radiusForRetry(3));
        $this->assertSame(30.0, $this->service->radiusForRetry(4));
        // Beyond the schedule stays capped at the max radius.
        $this->assertSame(30.0, $this->service->radiusForRetry(99));
        $this->assertSame(RadiusExpansionService::MAX_RADIUS_KM, $this->service->radiusForRetry(99));
    }
}
