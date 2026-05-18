<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('transport_tickets')) {
            return;
        }

        Schema::create('transport_tickets', function (Blueprint $table): void {
            $table->id();
            $table->string('ticket_code', 64)->unique();
            $table->json('qr_payload');
            $table->foreignId('ride_id')->nullable()->constrained('rides')->nullOnDelete();
            $table->foreignId('trip_id')->nullable()->constrained('trips')->cascadeOnDelete();
            $table->foreignId('booking_id')->nullable()->constrained('bookings')->nullOnDelete();
            $table->foreignId('passenger_id')->nullable()->constrained('mobile_users')->nullOnDelete();
            $table->foreignId('driver_id')->nullable()->constrained('drivers')->nullOnDelete();
            $table->unsignedInteger('seat_count')->default(1);
            $table->string('payment_status', 32)->default('pending');
            $table->string('status', 32)->default('issued');
            $table->timestamp('issued_at')->nullable();
            $table->timestamp('validated_at')->nullable();
            $table->timestamps();

            $table->index(['trip_id', 'status']);
            $table->index(['passenger_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transport_tickets');
    }
};
