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
        Schema::create('trips_v3', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('driver_id')->nullable()->constrained('drivers')->onDelete('set null');
            
            $table->enum('transport_type', ['motor_vehicle', 'public_bus', 'private_car'])->index();
            $table->enum('status', ['created', 'searching', 'assigned', 'active', 'completed', 'cancelled', 'expired'])->default('created')->index();

            $table->string('pickup_location')->nullable();
            $table->string('dropoff_location')->nullable();
            
            $table->decimal('pickup_lat', 10, 7)->nullable();
            $table->decimal('pickup_lng', 10, 7)->nullable();
            $table->decimal('dropoff_lat', 10, 7)->nullable();
            $table->decimal('dropoff_lng', 10, 7)->nullable();

            $table->decimal('fare_estimate', 10, 2)->nullable();
            $table->decimal('fare_actual', 10, 2)->nullable();

            $table->jsonb('metadata')->nullable();
            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('trips_v3');
    }
};
