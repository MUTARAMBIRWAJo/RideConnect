<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Drop old table to clean up legacy schema issues
        Schema::dropIfExists('passenger_locations');

        Schema::create('passenger_locations', function (Blueprint $table) {
            $table->id();
            
            // Passenger reference (User)
            $table->unsignedBigInteger('user_id')->unique();
            $table->unsignedBigInteger('trip_id')->nullable();
            
            // Location data
            $table->decimal('lat', 10, 8);
            $table->decimal('lng', 11, 8);
            $table->decimal('latitude', 10, 8);
            $table->decimal('longitude', 11, 8);
            
            // Movement data
            $table->decimal('speed', 10, 2)->nullable();
            $table->integer('heading')->nullable();
            $table->decimal('accuracy', 10, 2)->nullable();
            
            // Status / Timestamps
            $table->boolean('is_online')->default(true);
            $table->timestamp('recorded_at')->useCurrent();
            $table->timestamps();
            
            // Foreign keys & Indexes
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->index('user_id');
            $table->index('trip_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('passenger_locations');
    }
};
