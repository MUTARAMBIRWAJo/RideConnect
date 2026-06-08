<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Allow payments and reviews to reference motorcycle/motor-vehicle trips.
 *
 * On-demand motor-vehicle trips live in the `motorcycle_trips` table (separate
 * from `trips`), so passengers previously had no way to pay for or rate them.
 * This adds a nullable `motorcycle_trip_id` FK to both tables and relaxes the
 * legacy NOT NULL constraints on `reviews` so a review can belong to a
 * motorcycle trip instead of a booking/ride.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('payments') && ! Schema::hasColumn('payments', 'motorcycle_trip_id')) {
            Schema::table('payments', function (Blueprint $table) {
                $table->foreignId('motorcycle_trip_id')
                    ->nullable()
                    ->after('trip_id')
                    ->constrained('motorcycle_trips')
                    ->nullOnDelete();
            });
        }

        if (Schema::hasTable('reviews')) {
            if (! Schema::hasColumn('reviews', 'motorcycle_trip_id')) {
                Schema::table('reviews', function (Blueprint $table) {
                    $table->foreignId('motorcycle_trip_id')
                        ->nullable()
                        ->after('ride_id')
                        ->constrained('motorcycle_trips')
                        ->nullOnDelete();
                });
            }

            // Relax legacy NOT NULL constraints so a review can belong to a
            // motorcycle trip rather than a booking/ride. Postgres-specific.
            if (DB::getDriverName() === 'pgsql') {
                DB::statement('ALTER TABLE reviews ALTER COLUMN booking_id DROP NOT NULL');
                DB::statement('ALTER TABLE reviews ALTER COLUMN ride_id DROP NOT NULL');
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('reviews') && Schema::hasColumn('reviews', 'motorcycle_trip_id')) {
            Schema::table('reviews', function (Blueprint $table) {
                $table->dropConstrainedForeignId('motorcycle_trip_id');
            });
        }

        if (Schema::hasTable('payments') && Schema::hasColumn('payments', 'motorcycle_trip_id')) {
            Schema::table('payments', function (Blueprint $table) {
                $table->dropConstrainedForeignId('motorcycle_trip_id');
            });
        }

        // Note: NOT NULL constraints on reviews.booking_id / reviews.ride_id are
        // intentionally NOT restored — existing motorcycle-trip reviews may have
        // null values that would violate a re-added constraint.
    }
};
