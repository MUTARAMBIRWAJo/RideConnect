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
        $driver = DB::connection()->getDriverName();

        if ($driver === 'sqlite') {
            // SQLite doesn't support constraint syntax, skip
            return;
        }

        DB::statement('ALTER TABLE rides DROP CONSTRAINT IF EXISTS rides_status_check');
        DB::statement("ALTER TABLE rides ADD CONSTRAINT rides_status_check CHECK (status IN ('scheduled', 'in_progress', 'completed', 'cancelled', 'published'))");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'sqlite') {
            // SQLite doesn't support constraint syntax, skip
            return;
        }

        DB::statement('ALTER TABLE rides DROP CONSTRAINT IF EXISTS rides_status_check');
        DB::statement("ALTER TABLE rides ADD CONSTRAINT rides_status_check CHECK (status IN ('scheduled', 'in_progress', 'completed', 'cancelled'))");
    }
};
