<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            // stripe | mtn_momo | cash
            $table->string('payment_provider', 30)->nullable()->after('payment_method');
            // Transaction ID from Stripe/MTN (for deduplication)
            $table->string('provider_transaction_id')->nullable()->unique()->after('payment_provider');
            // Webhook event ID for idempotency
            $table->string('webhook_event_id')->nullable()->unique()->after('provider_transaction_id');
            // pending | verified | failed
            $table->string('verification_status', 20)->default('pending')->after('webhook_event_id');

            $table->index('payment_provider');
            $table->index('verification_status');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropIndex(['payment_provider']);
            $table->dropIndex(['verification_status']);
            $table->dropColumn([
                'payment_provider',
                'provider_transaction_id',
                'webhook_event_id',
                'verification_status',
            ]);
        });
    }
};
