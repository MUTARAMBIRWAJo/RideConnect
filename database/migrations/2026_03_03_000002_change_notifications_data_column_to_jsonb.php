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
        DB::statement('ALTER TABLE notifications ALTER COLUMN "data" TYPE jsonb USING "data"::jsonb');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE notifications ALTER COLUMN "data" TYPE text USING "data"::text');
    }
};
