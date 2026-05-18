<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('seat_reservations')) {
            return;
        }

        Schema::create('seat_reservations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('ride_id')->constrained('rides')->cascadeOnDelete();
            $table->foreignId('booking_id')->nullable()->constrained('bookings')->cascadeOnDelete();
            $table->foreignId('trip_id')->nullable()->constrained('trips')->cascadeOnDelete();
            $table->foreignId('passenger_id')->nullable()->constrained('mobile_users')->nullOnDelete();
            $table->unsignedInteger('seats');
            $table->string('status', 32)->default('reserved');
            $table->timestamp('reserved_at')->nullable();
            $table->timestamp('released_at')->nullable();
            $table->timestamps();

            $table->index(['ride_id', 'status']);
            $table->index(['booking_id', 'status']);
            $table->index(['trip_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seat_reservations');
    }
};
