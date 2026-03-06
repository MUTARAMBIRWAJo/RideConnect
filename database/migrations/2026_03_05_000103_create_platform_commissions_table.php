<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_commissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('driver_id')->constrained('drivers')->cascadeOnDelete();
            $table->foreignId('ride_id')->constrained('rides')->cascadeOnDelete();
            $table->decimal('commission_amount', 12, 2);
            $table->date('date');
            $table->timestamps();

            $table->unique(['driver_id', 'ride_id', 'date']);
            $table->index(['date', 'driver_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_commissions');
    }
};
