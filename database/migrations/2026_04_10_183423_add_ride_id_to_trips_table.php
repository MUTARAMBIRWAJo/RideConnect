<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'pgsql') {
            DB::statement('ALTER TABLE trips ADD COLUMN IF NOT EXISTS ride_id BIGINT NULL');
            DB::statement('CREATE INDEX IF NOT EXISTS trips_ride_id_index ON trips (ride_id)');
            DB::statement("DO $$ BEGIN IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'trips_ride_id_foreign') THEN ALTER TABLE trips ADD CONSTRAINT trips_ride_id_foreign FOREIGN KEY (ride_id) REFERENCES rides(id) ON DELETE SET NULL; END IF; END $$;");

            return;
        }

        Schema::table('trips', function (Blueprint $table) {
            $table->foreignId('ride_id')->nullable()->after('id')->constrained('rides')->nullOnDelete();
        });

        // Backfill links for legacy rows using nearest matching ride by driver and route.
        $trips = DB::table('trips')
            ->whereNull('ride_id')
            ->whereNotNull('driver_id')
            ->get(['id', 'driver_id', 'pickup_location', 'dropoff_location', 'requested_at', 'created_at']);

        foreach ($trips as $trip) {
            $matchedRideId = DB::table('rides')
                ->where('driver_id', $trip->driver_id)
                ->where('origin_address', $trip->pickup_location)
                ->where('destination_address', $trip->dropoff_location)
                ->orderBy('departure_time')
                ->value('id');

            if ($matchedRideId) {
                DB::table('trips')
                    ->where('id', $trip->id)
                    ->update(['ride_id' => $matchedRideId]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'pgsql') {
            DB::statement('ALTER TABLE trips DROP CONSTRAINT IF EXISTS trips_ride_id_foreign');
            DB::statement('DROP INDEX IF EXISTS trips_ride_id_index');
            DB::statement('ALTER TABLE trips DROP COLUMN IF EXISTS ride_id');

            return;
        }

        Schema::table('trips', function (Blueprint $table) {
            $table->dropConstrainedForeignId('ride_id');
        });
    }
};
