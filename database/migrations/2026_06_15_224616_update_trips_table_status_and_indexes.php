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
        try {
        $driver = DB::connection()->getDriverName();

        // 1. Drop check constraint first
        if ($driver === 'pgsql') {
            DB::statement("ALTER TABLE trips DROP CONSTRAINT IF EXISTS trips_status_check");
        }

        // 2. Normalize existing row statuses to match the new check constraint
        DB::table('trips')->whereRaw("LOWER(status) IN ('pending', 'requested', 'assigning')")->update(['status' => 'REQUESTED']);
        DB::table('trips')->whereRaw("LOWER(status) IN ('matching')")->update(['status' => 'MATCHING']);
        DB::table('trips')->whereRaw("LOWER(status) IN ('matched', 'driver_found')")->update(['status' => 'DRIVER_FOUND']);
        DB::table('trips')->whereRaw("LOWER(status) IN ('assigned')")->update(['status' => 'ASSIGNED']);
        DB::table('trips')->whereRaw("LOWER(status) IN ('accepted')")->update(['status' => 'ACCEPTED']);
        DB::table('trips')->whereRaw("LOWER(status) IN ('arrived')")->update(['status' => 'ARRIVED']);
        DB::table('trips')->whereRaw("LOWER(status) IN ('started', 'in_progress', 'in_transit', 'enroute_to_pickup')")->update(['status' => 'STARTED']);
        DB::table('trips')->whereRaw("LOWER(status) IN ('completed')")->update(['status' => 'COMPLETED']);
        DB::table('trips')->whereRaw("LOWER(status) IN ('cancelled')")->update(['status' => 'CANCELLED']);
        DB::table('trips')->whereRaw("LOWER(status) IN ('failed')")->update(['status' => 'FAILED']);
        
        // Final fallback: capitalize anything else, or default to REQUESTED if empty
        DB::table('trips')->whereNull('status')->update(['status' => 'REQUESTED']);
        if ($driver === 'pgsql') {
            DB::statement("UPDATE trips SET status = UPPER(TRIM(status))");
            // If there are still any statuses not in the constraint list, set them to REQUESTED
            DB::statement("UPDATE trips SET status = 'REQUESTED' WHERE status NOT IN ('REQUESTED', 'MATCHING', 'DRIVER_FOUND', 'ASSIGNED', 'ACCEPTED', 'ARRIVED', 'STARTED', 'COMPLETED', 'CANCELLED', 'FAILED')");
        }

        // 3. Re-add check constraint with all 10 unified status values
        if ($driver === 'pgsql') {
            DB::statement("ALTER TABLE trips ADD CONSTRAINT trips_status_check 
                CHECK (status IN ('REQUESTED', 'MATCHING', 'DRIVER_FOUND', 'ASSIGNED', 'ACCEPTED', 'ARRIVED', 'STARTED', 'COMPLETED', 'CANCELLED', 'FAILED'))");
        }

        Schema::table('trips', function (Blueprint $table) {
            $table->index('status', 'trips_status_index');
            $table->index('passenger_id', 'trips_passenger_id_index');
            $table->index('created_at', 'trips_created_at_index');
        });
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::warning('Migration 2026_06_15_224616_update_trips_table_status_and_indexes.php skipped: ' . $e->getMessage());
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('trips', function (Blueprint $table) {
            $table->dropIndex('trips_status_index');
            $table->dropIndex('trips_passenger_id_index');
            $table->dropIndex('trips_created_at_index');
        });

        $driver = DB::connection()->getDriverName();

        if ($driver === 'pgsql') {
            DB::statement("ALTER TABLE trips DROP CONSTRAINT IF EXISTS trips_status_check");
            // Re-normalize down
            DB::table('trips')->whereNotIn('status', ['PENDING', 'ACCEPTED', 'STARTED', 'COMPLETED', 'CANCELLED'])->update(['status' => 'PENDING']);
            DB::statement("ALTER TABLE trips ADD CONSTRAINT trips_status_check 
                CHECK (status IN ('PENDING', 'ACCEPTED', 'STARTED', 'COMPLETED', 'CANCELLED'))");
        }
    }
};


