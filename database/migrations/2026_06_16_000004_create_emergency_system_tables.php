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
        try {
        Schema::create('emergency_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('set null');
            $table->foreignId('trip_id')->nullable()->constrained()->onDelete('set null');
            $table->decimal('latitude', 10, 8)->nullable();
            $table->decimal('longitude', 11, 8)->nullable();
            $table->string('status')->default('active'); // active, resolved, false_alarm
            $table->text('details')->nullable();
            $table->timestamps();
        });

        Schema::create('emergency_alerts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('emergency_report_id')->constrained()->onDelete('cascade');
            $table->string('severity')->default('high'); // medium, high, critical
            $table->string('status')->default('pending'); // pending, dispatched, resolved
            $table->text('message')->nullable();
            $table->timestamps();
        });
            } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::warning('Migration 2026_06_16_000004_create_emergency_system_tables.php skipped: ' . $e->getMessage());
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('emergency_alerts');
        Schema::dropIfExists('emergency_reports');
    }
};
