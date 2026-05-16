<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('driver_behaviors')) {
            Schema::create('driver_behaviors', function (Blueprint $table) {
                $table->id();
                $table->foreignId('driver_id')->nullable()->constrained('drivers')->nullOnDelete();
                $table->foreignId('trip_id')->nullable()->constrained('trips')->nullOnDelete();
                $table->decimal('rating', 3, 2)->default(0.00);
                $table->decimal('acceptance_rate', 5, 4)->default(0.00);
                $table->decimal('cancellation_rate', 5, 4)->default(0.00);
                $table->decimal('on_time_rate', 5, 4)->default(0.00);
                $table->decimal('behavior_score', 5, 4)->default(0.00);
                $table->string('notes', 255)->nullable();
                $table->timestamp('reviewed_at')->nullable();
                $table->timestamps();

                $table->index(['driver_id', 'trip_id']);
                $table->index(['behavior_score']);
            });
        }

        if (! Schema::hasTable('passenger_behaviors')) {
            Schema::create('passenger_behaviors', function (Blueprint $table) {
                $table->id();
                $table->foreignId('passenger_id')->nullable()->constrained('mobile_users')->nullOnDelete();
                $table->foreignId('trip_id')->nullable()->constrained('trips')->nullOnDelete();
                $table->decimal('reliability_score', 5, 4)->default(0.00);
                $table->decimal('cancellation_rate', 5, 4)->default(0.00);
                $table->decimal('no_show_rate', 5, 4)->default(0.00);
                $table->unsignedInteger('total_trips')->default(0);
                $table->string('notes', 255)->nullable();
                $table->timestamps();

                $table->index(['passenger_id', 'trip_id']);
                $table->index(['reliability_score']);
            });
        }

        if (! Schema::hasTable('route_states')) {
            Schema::create('route_states', function (Blueprint $table) {
                $table->id();
                $table->foreignId('trip_id')->nullable()->constrained('trips')->nullOnDelete();
                $table->decimal('pickup_lat', 10, 7)->nullable();
                $table->decimal('pickup_lng', 10, 7)->nullable();
                $table->decimal('dropoff_lat', 10, 7)->nullable();
                $table->decimal('dropoff_lng', 10, 7)->nullable();
                $table->string('route_name', 180)->nullable();
                $table->decimal('distance_km', 8, 3)->default(0.000);
                $table->unsignedInteger('estimated_duration_min')->default(0);
                $table->unsignedTinyInteger('traffic_level')->default(3);
                $table->decimal('congestion_index', 5, 4)->default(0.00);
                $table->json('route_geometry')->nullable();
                $table->timestamps();

                $table->index(['trip_id']);
                $table->index(['traffic_level']);
            });
        }

        if (! Schema::hasTable('weather_conditions')) {
            Schema::create('weather_conditions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('trip_id')->nullable()->constrained('trips')->nullOnDelete();
                $table->decimal('location_lat', 10, 7)->nullable();
                $table->decimal('location_lng', 10, 7)->nullable();
                $table->string('condition', 100)->nullable();
                $table->decimal('temperature_celsius', 5, 2)->nullable();
                $table->decimal('wind_speed_kmh', 6, 2)->nullable();
                $table->decimal('precipitation_mm', 6, 2)->nullable();
                $table->decimal('weather_factor', 5, 4)->default(1.00);
                $table->string('description', 255)->nullable();
                $table->timestamp('recorded_at')->nullable();
                $table->timestamps();

                $table->index(['trip_id']);
                $table->index(['condition']);
            });
        }

        if (Schema::hasTable('trips')) {
            Schema::table('trips', function (Blueprint $table) {
                if (! Schema::hasColumn('trips', 'driver_behavior_id')) {
                    $table->foreignId('driver_behavior_id')->nullable()->constrained('driver_behaviors')->nullOnDelete();
                }

                if (! Schema::hasColumn('trips', 'passenger_behavior_id')) {
                    $table->foreignId('passenger_behavior_id')->nullable()->constrained('passenger_behaviors')->nullOnDelete();
                }

                if (! Schema::hasColumn('trips', 'route_state_id')) {
                    $table->foreignId('route_state_id')->nullable()->constrained('route_states')->nullOnDelete();
                }

                if (! Schema::hasColumn('trips', 'weather_condition_id')) {
                    $table->foreignId('weather_condition_id')->nullable()->constrained('weather_conditions')->nullOnDelete();
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('trips')) {
            Schema::table('trips', function (Blueprint $table) {
                if (Schema::hasColumn('trips', 'driver_behavior_id')) {
                    $table->dropForeign(['driver_behavior_id']);
                    $table->dropColumn('driver_behavior_id');
                }
                if (Schema::hasColumn('trips', 'passenger_behavior_id')) {
                    $table->dropForeign(['passenger_behavior_id']);
                    $table->dropColumn('passenger_behavior_id');
                }
                if (Schema::hasColumn('trips', 'route_state_id')) {
                    $table->dropForeign(['route_state_id']);
                    $table->dropColumn('route_state_id');
                }
                if (Schema::hasColumn('trips', 'weather_condition_id')) {
                    $table->dropForeign(['weather_condition_id']);
                    $table->dropColumn('weather_condition_id');
                }
            });
        }

        Schema::dropIfExists('weather_conditions');
        Schema::dropIfExists('route_states');
        Schema::dropIfExists('passenger_behaviors');
        Schema::dropIfExists('driver_behaviors');
    }
};
