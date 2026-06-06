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
        // Only create table if it doesn't already exist
        if (!Schema::hasTable('trip_requests')) {
            Schema::create('trip_requests', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('passenger_id');
                $table->unsignedBigInteger('corridor_id');
                $table->string('pickup_location');
                $table->decimal('pickup_lat', 10, 8);
                $table->decimal('pickup_lng', 11, 8);
                $table->string('dropoff_location');
                $table->decimal('dropoff_lat', 10, 8);
                $table->decimal('dropoff_lng', 11, 8);
                $table->unsignedBigInteger('matched_driver_id')->nullable();
                $table->unsignedBigInteger('matched_vehicle_id')->nullable();
                $table->decimal('distance_to_bus_km', 8, 2)->nullable();
                $table->integer('bus_eta_minutes')->nullable();
                $table->decimal('trip_distance_km', 10, 2)->nullable();
                $table->integer('trip_duration_minutes')->nullable();
                $table->decimal('estimated_fare', 10, 2);
                $table->string('currency')->default('RWF');
                $table->enum('status', [
                    'PENDING_MATCH',
                    'BUS_ASSIGNED',
                    'PASSENGER_WAITING',
                    'PASSENGER_BOARDED',
                    'IN_TRANSIT',
                    'COMPLETED',
                    'CANCELLED',
                ])->default('PENDING_MATCH');
                $table->text('notes')->nullable();
                $table->timestamps();

                // Foreign keys
                $table->foreign('passenger_id')->references('id')->on('users')->cascadeOnDelete();
                $table->foreign('corridor_id')->references('id')->on('transport_corridors')->cascadeOnDelete();
                $table->foreign('matched_driver_id')->references('id')->on('drivers')->nullableOnDelete();
                $table->foreign('matched_vehicle_id')->references('id')->on('vehicles')->nullableOnDelete();

                // Indexes for performance
                $table->index('passenger_id');
                $table->index('corridor_id');
                $table->index('status');
                $table->index('created_at');
                $table->index(['corridor_id', 'status']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('trip_requests');
    }
};
