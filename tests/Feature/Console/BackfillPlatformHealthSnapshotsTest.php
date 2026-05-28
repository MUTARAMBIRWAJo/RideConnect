<?php

namespace Tests\Feature\Console;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class BackfillPlatformHealthSnapshotsTest extends TestCase
{
    public function test_backfill_platform_health_snapshots_seeds_initial_row(): void
    {
        $this->artisan('migrate:fresh');

        if (! DB::getSchemaBuilder()->hasTable('platform_health_snapshots')) {
            $this->markTestSkipped('platform_health_snapshots table is not available in this test environment.');
        }

        $this->assertSame(0, DB::table('platform_health_snapshots')->count());

        Artisan::call('health:backfill-platform-snapshots');

        $this->assertGreaterThanOrEqual(1, DB::table('platform_health_snapshots')->count());
    }
}