<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_reconciliation_logs', function (Blueprint $table) {
            $table->id();
            $table->uuid('reconciliation_id')->unique();
            $table->string('payment_provider', 30); // stripe, mtn_momo
            $table->date('reconciliation_date');
            $table->unsignedBigInteger('payment_id');
            $table->string('provider_transaction_id');
            $table->decimal('expected_amount', 10, 2);
            $table->decimal('actual_amount', 10, 2);
            $table->string('currency', 3)->default('RWF');
            $table->string('status', 20)->default('pending'); // pending, matched, mismatched, missing
            $table->decimal('discrepancy_amount', 10, 2)->default(0);
            $table->text('discrepancy_reason')->nullable();
            $table->json('provider_data')->nullable();
            $table->json('system_data')->nullable();
            $table->timestamp('reconciled_at')->nullable();
            $table->timestamp('reconciliation_started_at')->useCurrent();
            $table->timestamps();

            $table->index('payment_provider');
            $table->index('reconciliation_date');
            $table->index('payment_id');
            $table->index('provider_transaction_id');
            $table->index('status');

            $table->foreign('payment_id')->references('id')->on('payments')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_reconciliation_logs');
    }
};
