<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('driver_availability_snapshots')) {
            return;
        }

        Schema::create('driver_availability_snapshots', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('driver_id')->constrained('drivers')->cascadeOnDelete();
            $table->string('availability_status', 32);
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->foreignId('trip_id')->nullable()->constrained('trips')->nullOnDelete();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['driver_id', 'created_at']);
            $table->index(['availability_status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('driver_availability_snapshots');
    }
};
