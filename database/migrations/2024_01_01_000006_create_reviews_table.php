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
        Schema::create('reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('driver_id')->constrained('drivers')->onDelete('cascade');
            $table->foreignId('ride_id')->constrained()->onDelete('cascade');
            $table->integer('rating')->between(1, 5);
            $table->text('comment')->nullable();
            $table->integer('safety_rating')->between(1, 5)->nullable();
            $table->integer('punctuality_rating')->between(1, 5)->nullable();
            $table->integer('communication_rating')->between(1, 5)->nullable();
            $table->integer('vehicle_condition_rating')->between(1, 5)->nullable();
            $table->enum('reviewer_type', ['passenger', 'driver'])->default('passenger');
            $table->boolean('is_public')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reviews');
    }
};
