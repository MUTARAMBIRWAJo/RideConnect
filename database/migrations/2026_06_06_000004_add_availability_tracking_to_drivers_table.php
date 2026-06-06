<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Add columns to drivers table for availability tracking
        if (!Schema::hasTable('drivers')) {
            Schema::create('drivers', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id');
                $table->timestamps();
            });
        }

        if (!Schema::hasColumn('drivers', 'is_available')) {
            Schema::table('drivers', function (Blueprint $table) {
                $table->boolean('is_available')->default(true)->after('user_id');
            });
        }

        if (!Schema::hasColumn('drivers', 'current_trip_id')) {
            Schema::table('drivers', function (Blueprint $table) {
                $table->unsignedBigInteger('current_trip_id')->nullable()->after('is_available');
            });
        }

        if (!Schema::hasColumn('drivers', 'last_location_lat')) {
            Schema::table('drivers', function (Blueprint $table) {
                $table->decimal('last_location_lat', 10, 8)->nullable()->after('current_trip_id');
            });
        }

        if (!Schema::hasColumn('drivers', 'last_location_lng')) {
            Schema::table('drivers', function (Blueprint $table) {
                $table->decimal('last_location_lng', 11, 8)->nullable()->after('last_location_lat');
            });
        }

        if (!Schema::hasColumn('drivers', 'updated_at')) {
            Schema::table('drivers', function (Blueprint $table) {
                $table->timestamp('updated_at')->nullable();
            });
        }
    }

    public function down(): void
    {
        Schema::table('drivers', function (Blueprint $table) {
            if (Schema::hasColumn('drivers', 'is_available')) {
                $table->dropColumn('is_available');
            }
            if (Schema::hasColumn('drivers', 'current_trip_id')) {
                $table->dropColumn('current_trip_id');
            }
            if (Schema::hasColumn('drivers', 'last_location_lat')) {
                $table->dropColumn('last_location_lat');
            }
            if (Schema::hasColumn('drivers', 'last_location_lng')) {
                $table->dropColumn('last_location_lng');
            }
        });
    }
};
