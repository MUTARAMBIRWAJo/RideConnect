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
        Schema::create('trips', function (Blueprint $table) {
            $table->id();
            $table->foreignId('passenger_id')->constrained('mobile_users')->onDelete('cascade');
            $table->foreignId('driver_id')->nullable()->constrained('mobile_users')->onDelete('cascade');
            $table->text('pickup_location');
            $table->text('dropoff_location');
            $table->decimal('pickup_lat', 10, 7);
            $table->decimal('pickup_lng', 10, 7);
            $table->decimal('dropoff_lat', 10, 7);
            $table->decimal('dropoff_lng', 10, 7);
            $table->decimal('fare', 10, 2);
            $table->enum('status', ['PENDING', 'ACCEPTED', 'STARTED', 'COMPLETED', 'CANCELLED'])->default('PENDING');
            $table->timestamp('requested_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('trips');
    }
};
