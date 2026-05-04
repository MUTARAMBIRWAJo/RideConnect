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
        // Drop the old check constraint if it exists
        DB::statement('ALTER TABLE rides DROP CONSTRAINT IF EXISTS rides_ride_type_check');

        // Convert existing data safely
        DB::statement("
            UPDATE rides
            SET ride_type = CASE
                WHEN ride_type IN ('one-way', 'round-trip', 'shared') THEN 'INTERCITY'
                ELSE 'LOCAL'
            END
        ");

        // Add the new check constraint
        DB::statement("ALTER TABLE rides ADD CONSTRAINT rides_ride_type_check CHECK (ride_type IN ('INTERCITY', 'LOCAL'))");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop the new constraint
        DB::statement('ALTER TABLE rides DROP CONSTRAINT IF EXISTS rides_ride_type_check');

        // Revert data (best effort - may not be perfect)
        DB::statement("
            UPDATE rides
            SET ride_type = CASE
                WHEN ride_type = 'INTERCITY' THEN 'one-way'
                WHEN ride_type = 'LOCAL' THEN 'one-way'
                ELSE 'one-way'
            END
        ");

        // Add back the old constraint
        DB::statement("ALTER TABLE rides ADD CONSTRAINT rides_ride_type_check CHECK (ride_type IN ('one-way', 'round-trip'))");
    }
};
