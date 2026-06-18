<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('driver_trip_offers', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignId('trip_id')->constrained('trips_v3')->cascadeOnDelete();
            $table->foreignId('driver_id')->constrained('drivers')->cascadeOnDelete();
            $table->string('status')->default('pending')->index();
            $table->timestamp('expires_at')->index();
            $table->timestamp('responded_at')->nullable();
            $table->string('response_reason')->nullable();
            $table->jsonb('payload')->nullable();
            $table->timestamps();

            $table->index(['trip_id', 'status']);
            $table->index(['driver_id', 'status']);
        });

        if (DB::getDriverName() === 'pgsql') {
            try {
                DB::statement('ALTER PUBLICATION supabase_realtime ADD TABLE driver_trip_offers;');
            } catch (Throwable $e) {
                report($e);
            }
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            try {
                DB::statement('ALTER PUBLICATION supabase_realtime DROP TABLE driver_trip_offers;');
            } catch (Throwable $e) {
                report($e);
            }
        }

        Schema::dropIfExists('driver_trip_offers');
    }
};
