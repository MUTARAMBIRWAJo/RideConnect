<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            // Enable PostGIS extension
            DB::statement('CREATE EXTENSION IF NOT EXISTS postgis;');

            // Add geography point columns to trips if they do not exist
            $hasPickup = DB::selectOne("SELECT 1 FROM information_schema.columns WHERE table_name = 'trips' AND column_name = 'pickup_point'");
            if (!$hasPickup) {
                DB::statement('ALTER TABLE trips ADD COLUMN pickup_point GEOGRAPHY(Point, 4326) NULL;');
            }

            $hasDropoff = DB::selectOne("SELECT 1 FROM information_schema.columns WHERE table_name = 'trips' AND column_name = 'dropoff_point'");
            if (!$hasDropoff) {
                DB::statement('ALTER TABLE trips ADD COLUMN dropoff_point GEOGRAPHY(Point, 4326) NULL;');
            }
        } else {
            \Illuminate\Support\Facades\Schema::table('trips', function (\Illuminate\Database\Schema\Blueprint $table) {
                if (!\Illuminate\Support\Facades\Schema::hasColumn('trips', 'pickup_point')) {
                    $table->text('pickup_point')->nullable();
                }
                if (!\Illuminate\Support\Facades\Schema::hasColumn('trips', 'dropoff_point')) {
                    $table->text('dropoff_point')->nullable();
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            // Drop geography point columns
            $hasPickup = DB::selectOne("SELECT 1 FROM information_schema.columns WHERE table_name = 'trips' AND column_name = 'pickup_point'");
            if ($hasPickup) {
                DB::statement('ALTER TABLE trips DROP COLUMN pickup_point;');
            }

            $hasDropoff = DB::selectOne("SELECT 1 FROM information_schema.columns WHERE table_name = 'trips' AND column_name = 'dropoff_point'");
            if ($hasDropoff) {
                DB::statement('ALTER TABLE trips DROP COLUMN dropoff_point;');
            }
        } else {
            \Illuminate\Support\Facades\Schema::table('trips', function (\Illuminate\Database\Schema\Blueprint $table) {
                $table->dropColumn(['pickup_point', 'dropoff_point']);
            });
        }
    }
};
