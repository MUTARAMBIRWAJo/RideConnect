<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('driver_locations')) {
            Schema::create('driver_locations', function (Blueprint $table) {
                $table->id();
                
                // Driver reference
                $table->unsignedBigInteger('driver_id');
                $table->unsignedBigInteger('trip_id')->nullable();
                
                // Location data
                $table->decimal('lat', 10, 8);
                $table->decimal('lng', 11, 8);
                
                // Movement data
                $table->decimal('speed', 10, 2)->nullable(); // km/h
                $table->integer('heading')->nullable(); // 0-360 degrees
                $table->decimal('accuracy', 10, 2)->nullable(); // meters
                
                // Timestamps
                $table->timestamp('recorded_at')->useCurrent();
                $table->timestamps();
                
                // Foreign keys
                $table->foreign('driver_id')->references('id')->on('drivers')->cascadeOnDelete();
                $table->foreign('trip_id')->references('id')->on('motorcycle_trips')->nullableOnDelete();
                
                // Indexes for performance
                $table->index('driver_id');
                $table->index('trip_id');
                $table->index('recorded_at');
                $table->index(['driver_id', 'trip_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('driver_locations');
    }
};
