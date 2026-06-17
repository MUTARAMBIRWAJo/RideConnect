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
            Schema::create('active_trips_v3', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->foreignUuid('trip_id')->constrained('trips_v3')->onDelete('cascade');
                $table->foreignId('driver_id')->nullable()->constrained('drivers')->onDelete('set null');
                $table->foreignId('passenger_id')->constrained('users')->onDelete('cascade');
                $table->string('status');
                $table->timestamps();
            });
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::warning('Migration create_active_trips_v3 skipped: ' . $e->getMessage());
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('active_trips_v3');
    }
};
