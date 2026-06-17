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
        try {
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
            } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::warning('Migration 2026_06_16_000002_create_notification_logs_table.php skipped: ' . $e->getMessage());
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notification_logs');
    }
};
