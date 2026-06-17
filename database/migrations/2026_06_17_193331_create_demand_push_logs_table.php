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
        Schema::create('demand_push_logs', function (Blueprint $table) {
            $table->id();
            $table->string('zone_id');
            $table->unsignedBigInteger('driver_id');
            $table->integer('demand_count');
            $table->integer('available_drivers_count');
            $table->decimal('lat', 10, 7)->nullable();
            $table->decimal('lng', 10, 7)->nullable();
            $table->json('payload')->nullable();
            $table->timestamps();

            $table->index(['driver_id', 'zone_id', 'created_at']);
            $table->index('zone_id');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('demand_push_logs');
    }
};
