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
        \Illuminate\Support\Facades\DB::statement('ALTER PUBLICATION supabase_realtime ADD TABLE trip_events_v3;');
        \Illuminate\Support\Facades\DB::statement('ALTER PUBLICATION supabase_realtime ADD TABLE active_trips_v3;');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        \Illuminate\Support\Facades\DB::statement('ALTER PUBLICATION supabase_realtime DROP TABLE trip_events_v3;');
        \Illuminate\Support\Facades\DB::statement('ALTER PUBLICATION supabase_realtime DROP TABLE active_trips_v3;');
    }
};
