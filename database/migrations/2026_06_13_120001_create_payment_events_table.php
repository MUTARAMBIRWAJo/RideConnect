<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('payment_events')) {
            Schema::create('payment_events', function (Blueprint $table) {
                $table->id();
                $table->uuid('event_id')->unique();
                $table->string('payment_provider', 30); // stripe, mtn_momo, cash
                $table->string('event_type', 50); // payment_intent.succeeded, charge.refunded, etc.
                $table->unsignedBigInteger('payment_id')->nullable();
                $table->unsignedBigInteger('booking_id')->nullable();
                $table->unsignedBigInteger('trip_id')->nullable();
                $table->unsignedBigInteger('motorcycle_trip_id')->nullable();
                $table->json('payload');
                $table->string('status', 20)->default('pending'); // pending, processed, failed
                $table->text('error_message')->nullable();
                $table->integer('retry_count')->default(0);
                $table->timestamp('processed_at')->nullable();
                $table->timestamps();

                $table->index('payment_provider');
                $table->index('event_type');
                $table->index('status');
                $table->index('payment_id');
                $table->index('booking_id');
                $table->index('created_at');

                $table->foreign('payment_id')->references('id')->on('payments')->nullOnDelete();
                $table->foreign('booking_id')->references('id')->on('bookings')->nullOnDelete();
                $table->foreign('trip_id')->references('id')->on('trips')->nullOnDelete();
                $table->foreign('motorcycle_trip_id')->references('id')->on('motorcycle_trips')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_events');
    }
};
