<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        try {
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
            } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::warning('Migration 2026_06_13_120003_create_payment_reconciliation_logs_table.php skipped: ' . $e->getMessage());
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_reconciliation_logs');
    }
};
