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
            Schema::create('trip_events_v3', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->foreignId('trip_id')->constrained('trips_v3')->onDelete('cascade');
                $table->string('event_type');
                $table->jsonb('payload')->nullable();
                $table->timestamps();
            });
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::warning('Migration create_trip_events_v3 skipped: ' . $e->getMessage());
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('trip_events_v3');
    }
};
