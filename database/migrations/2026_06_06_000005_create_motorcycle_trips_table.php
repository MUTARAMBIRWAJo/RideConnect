<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('motorcycle_trips')) {
            Schema::create('motorcycle_trips', function (Blueprint $table) {
                $table->id();
                
                // Trip participants
                $table->unsignedBigInteger('passenger_id');
                $table->unsignedBigInteger('driver_id')->nullable();
                $table->unsignedBigInteger('vehicle_id')->nullable();
                
                // Location data
                $table->string('pickup_location');
                $table->decimal('pickup_lat', 10, 8);
                $table->decimal('pickup_lng', 11, 8);
                $table->string('dropoff_location');
                $table->decimal('dropoff_lat', 10, 8);
                $table->decimal('dropoff_lng', 11, 8);
                
                // Trip details
                $table->decimal('distance_km', 10, 2)->nullable();
                $table->integer('duration_minutes')->nullable();
                $table->decimal('estimated_fare', 10, 2);
                $table->decimal('actual_fare', 10, 2)->nullable();
                $table->string('currency')->default('RWF');
                
                // Status tracking
                $table->enum('status', [
                    'REQUESTED',
                    'MATCHING',
                    'MATCHING_PENDING',
                    'ASSIGNED',
                    'DRIVER_ASSIGNED',
                    'PASSENGER_WAITING',
                    'IN_PROGRESS',
                    'COMPLETED',
                    'REJECTED_BY_DRIVER',
                    'CANCELLED_BY_PASSENGER',
                    'CANCELLED_BY_DRIVER',
                    'EXPIRED'
                ])->default('REQUESTED');
                
                // Rejection tracking
                $table->unsignedBigInteger('rejected_driver_id')->nullable();
                $table->text('rejection_reason')->nullable();
                $table->json('rejected_drivers')->nullable(); // Array of driver IDs that rejected
                
                // Timestamps
                $table->timestamp('requested_at')->nullable();
                $table->timestamp('matching_started_at')->nullable();
                $table->timestamp('assigned_at')->nullable();
                $table->timestamp('accepted_at')->nullable();
                $table->timestamp('driver_arrived_at')->nullable();
                $table->timestamp('started_at')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->timestamp('rejected_at')->nullable();
                $table->timestamp('cancelled_at')->nullable();
                
                // Metadata
                $table->json('metadata')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();
                
                // Foreign keys
                $table->foreign('passenger_id')->references('id')->on('users')->cascadeOnDelete();
                $table->foreign('driver_id')->references('id')->on('drivers')->nullableOnDelete();
                $table->foreign('vehicle_id')->references('id')->on('vehicles')->nullableOnDelete();
                $table->foreign('rejected_driver_id')->references('id')->on('drivers')->nullableOnDelete();
                
                // Indexes
                $table->index('passenger_id');
                $table->index('driver_id');
                $table->index('status');
                $table->index('vehicle_id');
                $table->index('created_at');
                $table->index(['driver_id', 'status']);
                $table->index(['passenger_id', 'status']);
                $table->index(['status', 'created_at']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('motorcycle_trips');
    }
};
