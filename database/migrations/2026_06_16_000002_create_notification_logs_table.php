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
        Schema::create('notification_logs', function (Blueprint $table) {
            $table->id();
            $table->string('recipient_type')->nullable(); // e.g. User, Driver
            $table->unsignedBigInteger('recipient_id')->nullable();
            $table->string('title')->nullable();
            $table->text('body')->nullable();
            $table->jsonb('payload')->nullable();
            $table->string('status')->default('pending'); // pending, sent, failed
            $table->text('failure_reason')->nullable();
            $table->string('message_id')->nullable();
            $table->timestamps();

            $table->index(['recipient_type', 'recipient_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notification_logs');
    }
};
