<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('rides')) {
            return;
        }

        Schema::table('rides', function (Blueprint $table) {
            if (! Schema::hasColumn('rides', 'pickup_lat')) {
                $table->decimal('pickup_lat', 10, 7)->nullable()->after('origin_lng');
            }

            if (! Schema::hasColumn('rides', 'pickup_lng')) {
                $table->decimal('pickup_lng', 10, 7)->nullable()->after('pickup_lat');
            }

            if (! Schema::hasColumn('rides', 'dropoff_lat')) {
                $table->decimal('dropoff_lat', 10, 7)->nullable()->after('destination_lng');
            }

            if (! Schema::hasColumn('rides', 'dropoff_lng')) {
                $table->decimal('dropoff_lng', 10, 7)->nullable()->after('dropoff_lat');
            }

            if (! Schema::hasColumn('rides', 'request_time')) {
                $table->timestamp('request_time')->nullable()->after('dropoff_lng');
            }

            if (! Schema::hasColumn('rides', 'driver_assigned_time')) {
                $table->timestamp('driver_assigned_time')->nullable()->after('request_time');
            }

            if (! Schema::hasColumn('rides', 'pickup_time')) {
                $table->timestamp('pickup_time')->nullable()->after('driver_assigned_time');
            }

            if (! Schema::hasColumn('rides', 'dropoff_time')) {
                $table->timestamp('dropoff_time')->nullable()->after('pickup_time');
            }

            if (! Schema::hasColumn('rides', 'ride_duration')) {
                $table->unsignedInteger('ride_duration')->nullable()->after('dropoff_time');
            }

            if (! Schema::hasColumn('rides', 'ride_distance')) {
                $table->decimal('ride_distance', 10, 3)->nullable()->after('ride_duration');
            }

            if (! Schema::hasColumn('rides', 'ride_status')) {
                $table->string('ride_status', 40)->nullable()->after('ride_distance');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('rides')) {
            return;
        }

        Schema::table('rides', function (Blueprint $table) {
            foreach ([
                'pickup_lat',
                'pickup_lng',
                'dropoff_lat',
                'dropoff_lng',
                'request_time',
                'driver_assigned_time',
                'pickup_time',
                'dropoff_time',
                'ride_duration',
                'ride_distance',
                'ride_status',
            ] as $column) {
                if (Schema::hasColumn('rides', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
