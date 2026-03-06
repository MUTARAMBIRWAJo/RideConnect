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
        Schema::create('vehicles_v2', function (Blueprint $table) {
            $table->id();
            $table->foreignId('driver_id')->constrained('mobile_users')->onDelete('cascade');
            $table->string('plate_number');
            $table->string('vehicle_type');
            $table->string('brand');
            $table->string('model');
            $table->string('color');
            $table->integer('capacity');
            $table->enum('status', ['ACTIVE', 'INACTIVE', 'SUSPENDED'])->default('ACTIVE');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vehicles_v2');
    }
};
