<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('payments') && Schema::hasColumn('payments', 'booking_id')) {
            if (DB::getDriverName() === 'pgsql') {
                DB::statement('ALTER TABLE payments ALTER COLUMN booking_id DROP NOT NULL');
            } else {
                Schema::table('payments', function (Blueprint $table): void {
                    $table->unsignedBigInteger('booking_id')->nullable()->change();
                });
            }
        }

        if (Schema::hasTable('reviews')) {
            if (DB::getDriverName() === 'pgsql') {
                DB::statement('ALTER TABLE reviews ALTER COLUMN booking_id DROP NOT NULL');
                DB::statement('ALTER TABLE reviews ALTER COLUMN ride_id DROP NOT NULL');
            } else {
                Schema::table('reviews', function (Blueprint $table): void {
                    if (Schema::hasColumn('reviews', 'booking_id')) {
                        $table->unsignedBigInteger('booking_id')->nullable()->change();
                    }

                    if (Schema::hasColumn('reviews', 'ride_id')) {
                        $table->unsignedBigInteger('ride_id')->nullable()->change();
                    }
                });
            }
        }
    }

    public function down(): void
    {
        // Intentionally not restored: existing trip-only payments/reviews may have null booking_id/ride_id.
    }
};
