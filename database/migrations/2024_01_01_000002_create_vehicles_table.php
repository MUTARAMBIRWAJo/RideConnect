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
        Schema::create('vehicles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('driver_id')->constrained('drivers')->onDelete('cascade');
            $table->string('make');
            $table->string('model');
            $table->integer('year');
            $table->string('color');
            $table->enum('vehicle_type', ['sedan', 'suv', 'hatchback', 'van', 'motorcycle', 'compact']);
            $table->integer('seats')->default(4);
            $table->boolean('air_conditioning')->default(true);
            $table->boolean('is_active')->default(true);
            $table->string('photo_url')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vehicles');
    }
};
