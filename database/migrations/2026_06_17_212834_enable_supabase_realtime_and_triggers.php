<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        try {
            // 1. Enable replication for the required tables
            \Illuminate\Support\Facades\DB::statement('ALTER PUBLICATION supabase_realtime ADD TABLE trips_v3;');
            \Illuminate\Support\Facades\DB::statement('ALTER PUBLICATION supabase_realtime ADD TABLE driver_locations_v3;');
            \Illuminate\Support\Facades\DB::statement('ALTER PUBLICATION supabase_realtime ADD TABLE trip_messages_v3;');
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::warning('Skipping supabase_realtime publication: ' . $e->getMessage());
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('ALTER PUBLICATION supabase_realtime DROP TABLE trips_v3;');
        DB::statement('ALTER PUBLICATION supabase_realtime DROP TABLE driver_locations_v3;');
        DB::statement('ALTER PUBLICATION supabase_realtime DROP TABLE trip_messages_v3;');
    }
};
