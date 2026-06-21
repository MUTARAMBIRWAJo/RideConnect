<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('driver_locations_v3', function (Blueprint $table) {
            if (!Schema::hasColumn('driver_locations_v3', 'trip_id')) {
                $table->unsignedBigInteger('trip_id')->nullable()->after('driver_id');
            }
            if (!Schema::hasColumn('driver_locations_v3', 'latitude')) {
                $table->decimal('latitude', 10, 8)->nullable()->after('lng');
            }
            if (!Schema::hasColumn('driver_locations_v3', 'longitude')) {
                $table->decimal('longitude', 11, 8)->nullable()->after('latitude');
            }
        });
    }

    public function down(): void
    {
        Schema::table('driver_locations_v3', function (Blueprint $table) {
            $table->dropColumn(['trip_id', 'latitude', 'longitude']);
        });
    }
};
