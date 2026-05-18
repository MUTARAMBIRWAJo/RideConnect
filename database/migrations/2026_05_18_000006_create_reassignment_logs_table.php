<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('reassignment_logs')) {
            return;
        }

        Schema::create('reassignment_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('trip_id')->constrained('trips')->cascadeOnDelete();
            $table->foreignId('old_driver_id')->nullable()->constrained('drivers')->nullOnDelete();
            $table->foreignId('new_driver_id')->nullable()->constrained('drivers')->nullOnDelete();
            $table->string('reason', 255);
            $table->string('triggered_by', 64)->default('system');
            $table->timestamps();

            $table->index(['trip_id', 'created_at']);
            $table->index(['new_driver_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reassignment_logs');
    }
};
