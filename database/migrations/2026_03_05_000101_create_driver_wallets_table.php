<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('driver_wallets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('driver_id')->unique()->constrained('drivers')->cascadeOnDelete();
            $table->decimal('total_earned', 12, 2)->default(0);
            $table->decimal('total_paid', 12, 2)->default(0);
            $table->decimal('total_commission_generated', 12, 2)->default(0);
            $table->decimal('current_balance', 12, 2)->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('driver_wallets');
    }
};
