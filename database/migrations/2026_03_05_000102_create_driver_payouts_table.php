<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('driver_payouts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('driver_id')->constrained('drivers')->cascadeOnDelete();
            $table->date('payout_date');
            $table->decimal('total_income', 12, 2);
            $table->decimal('commission_amount', 12, 2);
            $table->decimal('payout_amount', 12, 2);
            $table->foreignId('processed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('status', ['pending', 'processed'])->default('pending');
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->unique(['driver_id', 'payout_date']);
            $table->index(['payout_date', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('driver_payouts');
    }
};
