<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Filament uses the PostgreSQL ->> operator to filter notifications by
     * "data"->>'format', which requires the column to be jsonb (or json),
     * not plain text.  This migration casts the existing text column to jsonb.
     */
    public function up(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE notifications ALTER COLUMN "data" TYPE jsonb USING "data"::jsonb');
        } elseif (DB::getDriverName() === 'sqlite') {
            // SQLite doesn't support jsonb, but json is sufficient for testing
            DB::statement('ALTER TABLE notifications RENAME COLUMN "data" TO "data_old"');
            DB::statement('ALTER TABLE notifications ADD COLUMN "data" json');
            DB::statement('UPDATE notifications SET "data" = "data_old"');
            DB::statement('ALTER TABLE notifications DROP COLUMN "data_old"');
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE notifications ALTER COLUMN "data" TYPE text USING "data"::text');
        } elseif (DB::getDriverName() === 'sqlite') {
            DB::statement('ALTER TABLE notifications RENAME COLUMN "data" TO "data_old"');
            DB::statement('ALTER TABLE notifications ADD COLUMN "data" text');
            DB::statement('UPDATE notifications SET "data" = "data_old"');
            DB::statement('ALTER TABLE notifications DROP COLUMN "data_old"');
        }
    }
};
