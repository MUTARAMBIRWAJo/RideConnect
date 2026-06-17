<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $driver = DB::connection()->getDriverName();

        if ($driver !== 'pgsql') {
            return;
        }

        try {
            // Fix the status enum to include all values used by the application
            // The trips table uses uppercase enum, but code may use lowercase values
            DB::statement("ALTER TABLE trips DROP CONSTRAINT IF EXISTS trips_status_check");

            // Add check constraint that accepts both the original enum values and normalized values
            DB::statement("ALTER TABLE trips ADD CONSTRAINT trips_status_check 
                CHECK (status IN ('PENDING', 'ACCEPTED', 'STARTED', 'COMPLETED', 'CANCELLED'))");
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::warning('Skipping trips status enum fix: ' . $e->getMessage());
        }
    }

    public function down(): void
    {
        $driver = DB::connection()->getDriverName();

        if ($driver !== 'pgsql') {
            return;
        }

        DB::statement("ALTER TABLE trips DROP CONSTRAINT IF EXISTS trips_status_check");

        DB::statement("ALTER TABLE trips ADD CONSTRAINT trips_status_check 
            CHECK (status IN ('PENDING', 'ACCEPTED', 'STARTED', 'COMPLETED', 'CANCELLED'))");
    }
};