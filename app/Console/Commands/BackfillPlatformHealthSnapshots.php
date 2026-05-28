<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

class BackfillPlatformHealthSnapshots extends Command
{
    protected $signature = 'health:backfill-platform-snapshots';

    protected $description = 'Seed the platform health snapshots table with an initial database-backed snapshot';

    public function handle(): int
    {
        if (! Schema::hasTable('platform_health_snapshots')) {
            $this->warn('platform_health_snapshots table is missing; run migrations first.');

            return self::SUCCESS;
        }

        $existingSnapshots = (int) \Illuminate\Support\Facades\DB::table('platform_health_snapshots')->count();

        if ($existingSnapshots > 0) {
            $this->info('platform_health_snapshots already contains data; no backfill needed.');

            return self::SUCCESS;
        }

        $this->call('health:record-platform-snapshot');

        $this->info('Seeded the initial platform health snapshot.');

        return self::SUCCESS;
    }
}