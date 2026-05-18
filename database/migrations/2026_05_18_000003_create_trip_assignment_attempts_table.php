<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('trip_assignment_attempts')) {
            Schema::create('trip_assignment_attempts', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('trip_id')->constrained('trips')->cascadeOnDelete();
                $table->foreignId('driver_id')->constrained('drivers')->cascadeOnDelete();
                $table->decimal('score', 8, 4)->nullable();
                $table->json('score_breakdown')->nullable();
                $table->string('rejection_reason')->nullable();
                $table->string('status', 32)->default('pending');
                $table->timestamp('expires_at')->nullable();
                $table->timestamp('responded_at')->nullable();
                $table->timestamps();

                $table->index(['trip_id', 'status']);
                $table->index(['driver_id', 'status']);
                $table->index('expires_at');
            });
        }

        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement(
                "CREATE UNIQUE INDEX IF NOT EXISTS trip_assignment_attempts_one_active_per_trip
                 ON trip_assignment_attempts (trip_id)
                 WHERE status IN ('pending', 'notified')"
            );
        }
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('DROP INDEX IF EXISTS trip_assignment_attempts_one_active_per_trip');
        }

        Schema::dropIfExists('trip_assignment_attempts');
    }
};
