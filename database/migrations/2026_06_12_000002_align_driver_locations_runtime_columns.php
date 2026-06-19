<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        try {
        if (! Schema::hasTable('driver_locations')) {
            return;
        }

        $hasLat = Schema::hasColumn('driver_locations', 'lat');
        $hasLng = Schema::hasColumn('driver_locations', 'lng');
        $hasTripId = Schema::hasColumn('driver_locations', 'trip_id');
        $hasSpeed = Schema::hasColumn('driver_locations', 'speed');
        $hasRecorded = Schema::hasColumn('driver_locations', 'recorded_at');

        if (!$hasLat || !$hasLng || !$hasTripId || !$hasSpeed || !$hasRecorded) {
            Schema::table('driver_locations', function (Blueprint $table) use ($hasLat, $hasLng, $hasTripId, $hasSpeed, $hasRecorded): void {
                if (!$hasLat) {
                    $table->decimal('lat', 10, 8)->nullable();
                }

                if (!$hasLng) {
                    $table->decimal('lng', 11, 8)->nullable();
                }

                if (!$hasTripId) {
                    $table->unsignedBigInteger('trip_id')->nullable();
                }

                if (!$hasSpeed) {
                    $table->decimal('speed', 10, 2)->nullable();
                }

                if (!$hasRecorded) {
                    $table->timestamp('recorded_at')->nullable();
                }
            });
        }

        if (Schema::hasColumn('driver_locations', 'latitude') && Schema::hasColumn('driver_locations', 'lat')) {
            DB::table('driver_locations')
                ->whereNull('lat')
                ->whereNotNull('latitude')
                ->update([
                    'lat' => DB::raw('latitude'),
                ]);
        }

        if (Schema::hasColumn('driver_locations', 'longitude') && Schema::hasColumn('driver_locations', 'lng')) {
            DB::table('driver_locations')
                ->whereNull('lng')
                ->whereNotNull('longitude')
                ->update([
                    'lng' => DB::raw('longitude'),
                ]);
        }

        if (Schema::hasColumn('driver_locations', 'speed_kmh') && Schema::hasColumn('driver_locations', 'speed')) {
            DB::table('driver_locations')
                ->whereNull('speed')
                ->whereNotNull('speed_kmh')
                ->update([
                    'speed' => DB::raw('speed_kmh'),
                ]);
        }

        if (Schema::hasColumn('driver_locations', 'updated_at') && Schema::hasColumn('driver_locations', 'recorded_at')) {
            DB::table('driver_locations')
                ->whereNull('recorded_at')
                ->whereNotNull('updated_at')
                ->update([
                    'recorded_at' => DB::raw('updated_at'),
                ]);
        }

        if (
            Schema::hasTable('motorcycle_trips')
            && Schema::hasColumn('driver_locations', 'trip_id')
            && DB::getDriverName() === 'pgsql'
        ) {
            DB::statement(<<<'SQL'
DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM pg_constraint WHERE conname = 'driver_locations_trip_id_foreign'
    ) THEN
        ALTER TABLE driver_locations
            ADD CONSTRAINT driver_locations_trip_id_foreign
            FOREIGN KEY (trip_id) REFERENCES motorcycle_trips(id) ON DELETE SET NULL;
    END IF;
END $$;
SQL);
        }
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::warning('Migration 2026_06_12_000002_align_driver_locations_runtime_columns.php skipped: ' . $e->getMessage());
        }
    }

    public function down(): void
    {
        // Additive alignment only — columns are intentionally retained.
    }
};
