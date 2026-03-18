<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('ride_requests')) {
            Schema::create('ride_requests', function (Blueprint $table) {
                $table->id();
                $table->foreignId('trip_id')->nullable()->constrained('trips')->nullOnDelete();
                $table->foreignId('passenger_id')->nullable()->constrained('mobile_users')->nullOnDelete();
                $table->decimal('pickup_lat', 10, 7)->nullable();
                $table->decimal('pickup_lng', 10, 7)->nullable();
                $table->decimal('dropoff_lat', 10, 7)->nullable();
                $table->decimal('dropoff_lng', 10, 7)->nullable();
                $table->timestamp('request_time')->nullable();
                $table->string('status', 40)->nullable();
                $table->timestamps();

                $table->index(['request_time']);
            });
        }

        if (!Schema::hasTable('passenger_locations')) {
            Schema::create('passenger_locations', function (Blueprint $table) {
                $table->id();
                $table->foreignId('passenger_id')->constrained('mobile_users')->cascadeOnDelete();
                $table->decimal('latitude', 10, 7);
                $table->decimal('longitude', 10, 7);
                $table->timestamp('updated_at')->useCurrent();

                $table->unique('passenger_id');
            });
        }

        if (!Schema::hasTable('ride_events')) {
            Schema::create('ride_events', function (Blueprint $table) {
                $table->id();
                $table->foreignId('trip_id')->nullable()->constrained('trips')->nullOnDelete();
                $table->foreignId('driver_id')->nullable()->constrained('mobile_users')->nullOnDelete();
                $table->foreignId('passenger_id')->nullable()->constrained('mobile_users')->nullOnDelete();
                $table->string('event_type', 60);
                $table->json('metadata')->nullable();
                $table->timestamp('event_time')->useCurrent();
                $table->timestamps();

                $table->index(['event_type', 'event_time']);
            });
        }

        if (!Schema::hasTable('ride_cancellations')) {
            Schema::create('ride_cancellations', function (Blueprint $table) {
                $table->id();
                $table->foreignId('trip_id')->nullable()->constrained('trips')->nullOnDelete();
                $table->foreignId('driver_id')->nullable()->constrained('mobile_users')->nullOnDelete();
                $table->foreignId('passenger_id')->nullable()->constrained('mobile_users')->nullOnDelete();
                $table->foreignId('cancelled_by_user_id')->nullable()->constrained('mobile_users')->nullOnDelete();
                $table->string('reason', 255)->nullable();
                $table->timestamp('cancelled_at')->useCurrent();
                $table->timestamps();

                $table->index(['cancelled_at']);
            });
        }

        if (!Schema::hasTable('traffic_events')) {
            Schema::create('traffic_events', function (Blueprint $table) {
                $table->id();
                $table->decimal('latitude', 10, 7)->nullable();
                $table->decimal('longitude', 10, 7)->nullable();
                $table->unsignedTinyInteger('traffic_level')->default(2);
                $table->string('event_type', 80)->nullable();
                $table->float('weather_factor')->nullable();
                $table->timestamp('event_time')->useCurrent();
                $table->timestamps();

                $table->index(['event_time', 'traffic_level']);
            });
        }

        if (!Schema::hasTable('demand_logs')) {
            Schema::create('demand_logs', function (Blueprint $table) {
                $table->id();
                $table->foreignId('trip_id')->nullable()->constrained('trips')->nullOnDelete();
                $table->string('zone_key', 80)->nullable();
                $table->decimal('pickup_lat', 10, 7)->nullable();
                $table->decimal('pickup_lng', 10, 7)->nullable();
                $table->timestamp('request_time')->nullable();
                $table->timestamps();

                $table->index(['zone_key', 'request_time']);
            });
        }

        if (!Schema::hasTable('ai_prediction_logs')) {
            Schema::create('ai_prediction_logs', function (Blueprint $table) {
                $table->id();
                $table->string('prediction_type', 60);
                $table->unsignedBigInteger('trip_id')->nullable();
                $table->json('request_payload')->nullable();
                $table->json('response_payload')->nullable();
                $table->unsignedSmallInteger('response_time_ms')->nullable();
                $table->boolean('success')->default(true);
                $table->timestamp('requested_at')->useCurrent();
                $table->timestamps();

                $table->index(['prediction_type', 'requested_at']);
            });
        }

        if (!Schema::hasTable('ai_model_metrics')) {
            Schema::create('ai_model_metrics', function (Blueprint $table) {
                $table->id();
                $table->string('model_name', 120);
                $table->string('metric_name', 60);
                $table->float('metric_value');
                $table->timestamp('evaluated_at')->useCurrent();
                $table->timestamps();

                $table->index(['model_name', 'metric_name', 'evaluated_at']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_model_metrics');
        Schema::dropIfExists('ai_prediction_logs');
        Schema::dropIfExists('demand_logs');
        Schema::dropIfExists('traffic_events');
        Schema::dropIfExists('ride_cancellations');
        Schema::dropIfExists('ride_events');
        Schema::dropIfExists('passenger_locations');
        Schema::dropIfExists('ride_requests');
    }
};
