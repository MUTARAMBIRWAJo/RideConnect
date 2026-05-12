<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->ensureDriverBehaviorsTable();
        $this->ensurePassengerBehaviorsTable();
        $this->ensureRouteStatesTable();
        $this->ensureWeatherConditionsTable();
        $this->ensureTripsTable();
    }

    public function down(): void
    {
        // Intentionally non-destructive. This migration only adds compatibility columns and indexes.
    }

    private function ensureDriverBehaviorsTable(): void
    {
        if (!Schema::hasTable('driver_behaviors')) {
            Schema::create('driver_behaviors', function (Blueprint $table) {
                $table->id();
                $table->foreignId('driver_id')->nullable()->constrained('drivers')->nullOnDelete();
                $table->foreignId('trip_id')->nullable()->constrained('trips')->nullOnDelete();
                $table->decimal('rating', 4, 2)->nullable();
                $table->decimal('acceptance_rate', 8, 4)->nullable();
                $table->decimal('cancellation_rate', 8, 4)->nullable();
                $table->decimal('on_time_rate', 8, 4)->nullable();
                $table->decimal('driving_score', 8, 4)->nullable();
                $table->decimal('behavior_score', 8, 4)->nullable();
                $table->text('notes')->nullable();
                $table->timestamp('reviewed_at')->nullable();
                $table->timestamps();
            });
        } else {
            Schema::table('driver_behaviors', function (Blueprint $table) {
                if (!Schema::hasColumn('driver_behaviors', 'driving_score')) {
                    $table->decimal('driving_score', 8, 4)->nullable()->after('on_time_rate');
                }

                if (!Schema::hasColumn('driver_behaviors', 'behavior_score')) {
                    $table->decimal('behavior_score', 8, 4)->nullable()->after('driving_score');
                }
            });
        }

        DB::statement('CREATE INDEX IF NOT EXISTS driver_behaviors_driver_id_idx ON driver_behaviors (driver_id)');
        DB::statement('CREATE INDEX IF NOT EXISTS driver_behaviors_trip_id_idx ON driver_behaviors (trip_id)');
    }

    private function ensurePassengerBehaviorsTable(): void
    {
        if (!Schema::hasTable('passenger_behaviors')) {
            Schema::create('passenger_behaviors', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('passenger_id')->nullable()->constrained('mobile_users')->nullOnDelete();
                $table->foreignId('trip_id')->nullable()->constrained('trips')->nullOnDelete();
                $table->decimal('rating', 4, 2)->nullable();
                $table->decimal('cancellation_rate', 8, 4)->nullable();
                $table->decimal('no_show_rate', 8, 4)->nullable();
                $table->decimal('payment_reliability', 8, 4)->nullable();
                $table->decimal('reliability_score', 8, 4)->nullable();
                $table->unsignedInteger('total_trips')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();
            });
        } else {
            Schema::table('passenger_behaviors', function (Blueprint $table) {
                if (!Schema::hasColumn('passenger_behaviors', 'user_id')) {
                    $table->foreignId('user_id')->nullable()->after('id')->constrained('users')->nullOnDelete();
                }

                if (!Schema::hasColumn('passenger_behaviors', 'rating')) {
                    $table->decimal('rating', 4, 2)->nullable()->after('trip_id');
                }

                if (!Schema::hasColumn('passenger_behaviors', 'payment_reliability')) {
                    $table->decimal('payment_reliability', 8, 4)->nullable()->after('no_show_rate');
                }
            });
        }

        DB::statement('CREATE INDEX IF NOT EXISTS passenger_behaviors_user_id_idx ON passenger_behaviors (user_id)');
        DB::statement('CREATE INDEX IF NOT EXISTS passenger_behaviors_passenger_id_idx ON passenger_behaviors (passenger_id)');
        DB::statement('CREATE INDEX IF NOT EXISTS passenger_behaviors_trip_id_idx ON passenger_behaviors (trip_id)');
    }

    private function ensureRouteStatesTable(): void
    {
        if (!Schema::hasTable('route_states')) {
            Schema::create('route_states', function (Blueprint $table) {
                $table->id();
                $table->foreignId('trip_id')->nullable()->constrained('trips')->nullOnDelete();
                $table->foreignId('route_id')->nullable()->constrained('routes')->nullOnDelete();
                $table->decimal('pickup_lat', 10, 7)->nullable();
                $table->decimal('pickup_lng', 10, 7)->nullable();
                $table->decimal('dropoff_lat', 10, 7)->nullable();
                $table->decimal('dropoff_lng', 10, 7)->nullable();
                $table->string('route_name')->nullable();
                $table->decimal('distance_km', 10, 3)->nullable();
                $table->unsignedInteger('estimated_duration_min')->nullable();
                $table->unsignedTinyInteger('traffic_level')->nullable();
                $table->string('road_condition')->nullable();
                $table->decimal('average_speed', 10, 2)->nullable();
                $table->boolean('incident_flag')->nullable();
                $table->decimal('congestion_index', 8, 4)->nullable();
                $table->json('route_geometry')->nullable();
                $table->timestamps();
            });
        } else {
            Schema::table('route_states', function (Blueprint $table) {
                if (!Schema::hasColumn('route_states', 'route_id')) {
                    $table->foreignId('route_id')->nullable()->after('trip_id')->constrained('routes')->nullOnDelete();
                }

                if (!Schema::hasColumn('route_states', 'road_condition')) {
                    $table->string('road_condition')->nullable()->after('traffic_level');
                }

                if (!Schema::hasColumn('route_states', 'average_speed')) {
                    $table->decimal('average_speed', 10, 2)->nullable()->after('road_condition');
                }

                if (!Schema::hasColumn('route_states', 'incident_flag')) {
                    $table->boolean('incident_flag')->nullable()->after('average_speed');
                }
            });
        }

        DB::statement('CREATE INDEX IF NOT EXISTS route_states_trip_id_idx ON route_states (trip_id)');
        DB::statement('CREATE INDEX IF NOT EXISTS route_states_route_id_idx ON route_states (route_id)');
    }

    private function ensureWeatherConditionsTable(): void
    {
        if (!Schema::hasTable('weather_conditions')) {
            Schema::create('weather_conditions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('trip_id')->nullable()->constrained('trips')->nullOnDelete();
                $table->decimal('location_lat', 10, 7)->nullable();
                $table->decimal('location_lng', 10, 7)->nullable();
                $table->string('weather_type')->nullable();
                $table->decimal('temperature', 6, 2)->nullable();
                $table->decimal('rain_intensity', 6, 2)->nullable();
                $table->decimal('visibility', 6, 2)->nullable();
                $table->decimal('wind_speed', 6, 2)->nullable();
                $table->string('condition')->nullable();
                $table->decimal('temperature_celsius', 6, 2)->nullable();
                $table->decimal('wind_speed_kmh', 6, 2)->nullable();
                $table->decimal('precipitation_mm', 6, 2)->nullable();
                $table->decimal('weather_factor', 8, 4)->nullable();
                $table->text('description')->nullable();
                $table->timestamp('recorded_at')->nullable();
                $table->timestamps();
            });
        } else {
            Schema::table('weather_conditions', function (Blueprint $table) {
                if (!Schema::hasColumn('weather_conditions', 'weather_type')) {
                    $table->string('weather_type')->nullable()->after('location_lng');
                }

                if (!Schema::hasColumn('weather_conditions', 'temperature')) {
                    $table->decimal('temperature', 6, 2)->nullable()->after('weather_type');
                }

                if (!Schema::hasColumn('weather_conditions', 'rain_intensity')) {
                    $table->decimal('rain_intensity', 6, 2)->nullable()->after('temperature');
                }

                if (!Schema::hasColumn('weather_conditions', 'visibility')) {
                    $table->decimal('visibility', 6, 2)->nullable()->after('rain_intensity');
                }

                if (!Schema::hasColumn('weather_conditions', 'wind_speed')) {
                    $table->decimal('wind_speed', 6, 2)->nullable()->after('visibility');
                }
            });
        }

        DB::statement('CREATE INDEX IF NOT EXISTS weather_conditions_trip_id_idx ON weather_conditions (trip_id)');
    }

    private function ensureTripsTable(): void
    {
        if (!Schema::hasTable('trips')) {
            return;
        }

        Schema::table('trips', function (Blueprint $table) {
            if (!Schema::hasColumn('trips', 'driver_behavior_id')) {
                $table->foreignId('driver_behavior_id')->nullable()->after('driver_id')->constrained('driver_behaviors')->nullOnDelete();
            }

            if (!Schema::hasColumn('trips', 'passenger_behavior_id')) {
                $table->foreignId('passenger_behavior_id')->nullable()->after('driver_behavior_id')->constrained('passenger_behaviors')->nullOnDelete();
            }

            if (!Schema::hasColumn('trips', 'route_state_id')) {
                $table->foreignId('route_state_id')->nullable()->after('passenger_behavior_id')->constrained('route_states')->nullOnDelete();
            }

            if (!Schema::hasColumn('trips', 'weather_condition_id')) {
                $table->foreignId('weather_condition_id')->nullable()->after('route_state_id')->constrained('weather_conditions')->nullOnDelete();
            }

            if (!Schema::hasColumn('trips', 'trip_quality_score')) {
                $table->decimal('trip_quality_score', 8, 4)->nullable()->after('weather_condition_id');
            }

            if (!Schema::hasColumn('trips', 'eta_deviation_minutes')) {
                $table->integer('eta_deviation_minutes')->nullable()->after('trip_quality_score');
            }
        });

        DB::statement('CREATE INDEX IF NOT EXISTS trips_driver_behavior_id_idx ON trips (driver_behavior_id)');
        DB::statement('CREATE INDEX IF NOT EXISTS trips_passenger_behavior_id_idx ON trips (passenger_behavior_id)');
        DB::statement('CREATE INDEX IF NOT EXISTS trips_route_state_id_idx ON trips (route_state_id)');
        DB::statement('CREATE INDEX IF NOT EXISTS trips_weather_condition_id_idx ON trips (weather_condition_id)');
    }
};