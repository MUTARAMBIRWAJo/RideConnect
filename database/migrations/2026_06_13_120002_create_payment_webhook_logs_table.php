<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        try {
        Schema::create('payment_webhook_logs', function (Blueprint $table) {
            $table->id();
            $table->uuid('log_id')->unique();
            $table->string('payment_provider', 30); // stripe, mtn_momo
            $table->string('webhook_id')->nullable(); // Stripe event ID, MTN financialTransactionId
            $table->string('event_type', 50)->nullable();
            $table->ipAddress('source_ip')->nullable();
            $table->string('signature')->nullable();
            $table->boolean('signature_valid')->default(false);
            $table->json('headers');
            $table->json('payload');
            $table->integer('http_status_code')->nullable();
            $table->string('response_body')->nullable();
            $table->text('error_message')->nullable();
            $table->string('processing_status', 20)->default('received'); // received, processing, completed, failed
            $table->timestamp('processed_at')->nullable();
            $table->timestamp('received_at')->useCurrent();
            $table->timestamps();

            $table->index('payment_provider');
            $table->index('webhook_id');
            $table->index('event_type');
            $table->index('processing_status');
            $table->index('received_at');
        });
            } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::warning('Migration 2026_06_13_120002_create_payment_webhook_logs_table.php skipped: ' . $e->getMessage());
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_webhook_logs');
    }
};
