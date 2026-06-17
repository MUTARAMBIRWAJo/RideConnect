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
        Schema::create('driver_locations_v3', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('driver_id')->constrained('drivers')->onDelete('cascade');
            $table->double('lat');
            $table->double('lng');
            $table->float('heading')->nullable();
            $table->float('speed')->nullable();
            $table->boolean('is_online')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('driver_locations_v3');
    }
};
