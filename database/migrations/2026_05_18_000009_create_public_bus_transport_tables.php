<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('transport_corridors')) {
            Schema::create('transport_corridors', function (Blueprint $table): void {
                $table->id();
                $table->string('corridor_code')->unique();
                $table->string('corridor_name');
                $table->unsignedBigInteger('start_stop_id')->nullable()->index();
                $table->unsignedBigInteger('end_stop_id')->nullable()->index();
                $table->string('transport_type', 32)->default('BUS');
                $table->string('status', 32)->default('active');
                $table->unsignedInteger('estimated_duration_minutes')->nullable();
                $table->timestamps();

                $table->index(['transport_type', 'status']);
            });
        }

        if (! Schema::hasTable('corridor_stops')) {
            Schema::create('corridor_stops', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('corridor_id')->constrained('transport_corridors')->cascadeOnDelete();
                $table->string('stop_name');
                $table->unsignedInteger('stop_order');
                $table->decimal('latitude', 10, 8)->nullable();
                $table->decimal('longitude', 11, 8)->nullable();
                $table->boolean('is_major_terminal')->default(false);
                $table->string('status', 32)->default('active');
                $table->timestamps();

                $table->unique(['corridor_id', 'stop_order']);
                $table->index(['corridor_id', 'status']);
            });
        }

        if (! Schema::hasTable('corridor_stop_times')) {
            Schema::create('corridor_stop_times', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('corridor_id')->constrained('transport_corridors')->cascadeOnDelete();
                $table->foreignId('corridor_stop_id')->constrained('corridor_stops')->cascadeOnDelete();
                $table->time('scheduled_arrival_time')->nullable();
                $table->time('scheduled_departure_time')->nullable();
                $table->unsignedTinyInteger('service_day_of_week')->nullable();
                $table->date('service_date')->nullable();
                $table->string('status', 32)->default('active');
                $table->timestamps();

                $table->index(['corridor_id', 'service_day_of_week', 'status']);
                $table->index(['corridor_stop_id', 'service_date']);
            });
        }

        if (! Schema::hasTable('bus_route_assignments')) {
            Schema::create('bus_route_assignments', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('bus_id')->constrained('vehicles')->cascadeOnDelete();
                $table->foreignId('corridor_id')->constrained('transport_corridors')->cascadeOnDelete();
                $table->foreignId('driver_id')->nullable()->constrained('drivers')->nullOnDelete();
                $table->foreignId('active_trip_id')->nullable()->constrained('trips')->nullOnDelete();
                $table->string('status', 32)->default('active');
                $table->timestamp('started_at')->nullable();
                $table->timestamp('ended_at')->nullable();
                $table->timestamps();

                $table->index(['corridor_id', 'status']);
                $table->index(['bus_id', 'status']);
            });
        }

        if (! Schema::hasTable('passenger_route_boardings')) {
            Schema::create('passenger_route_boardings', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('passenger_id')->constrained('mobile_users')->cascadeOnDelete();
                $table->foreignId('trip_id')->constrained('trips')->cascadeOnDelete();
                $table->foreignId('corridor_id')->constrained('transport_corridors')->cascadeOnDelete();
                $table->foreignId('bus_route_assignment_id')->nullable()->constrained('bus_route_assignments')->nullOnDelete();
                $table->foreignId('boarding_stop_id')->constrained('corridor_stops')->cascadeOnDelete();
                $table->foreignId('destination_stop_id')->constrained('corridor_stops')->cascadeOnDelete();
                $table->string('ticket_code')->unique();
                $table->json('qr_payload')->nullable();
                $table->unsignedInteger('seats_reserved')->default(1);
                $table->decimal('fare_amount', 10, 2)->default(0);
                $table->string('payment_status', 32)->default('pending');
                $table->string('status', 32)->default('reserved');
                $table->timestamp('boarded_at')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->timestamps();

                $table->index(['corridor_id', 'status']);
                $table->index(['bus_route_assignment_id', 'status']);
                $table->index(['passenger_id', 'status']);
                $table->index(['trip_id', 'status']);
            });
        }

        if (! Schema::hasTable('bus_position_updates')) {
            Schema::create('bus_position_updates', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('bus_route_assignment_id')->constrained('bus_route_assignments')->cascadeOnDelete();
                $table->foreignId('trip_id')->nullable()->constrained('trips')->nullOnDelete();
                $table->decimal('latitude', 10, 8);
                $table->decimal('longitude', 11, 8);
                $table->decimal('speed_kph', 8, 2)->nullable();
                $table->unsignedInteger('heading_degrees')->nullable();
                $table->foreignId('next_stop_id')->nullable()->constrained('corridor_stops')->nullOnDelete();
                $table->unsignedInteger('eta_minutes')->nullable();
                $table->decimal('route_progress_percent', 5, 2)->nullable();
                $table->timestamp('captured_at')->nullable();
                $table->timestamps();

                $table->index(['bus_route_assignment_id', 'captured_at']);
                $table->index(['trip_id', 'captured_at']);
            });
        }

        if (! Schema::hasTable('stop_arrival_events')) {
            Schema::create('stop_arrival_events', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('bus_route_assignment_id')->constrained('bus_route_assignments')->cascadeOnDelete();
                $table->foreignId('trip_id')->nullable()->constrained('trips')->nullOnDelete();
                $table->foreignId('corridor_stop_id')->constrained('corridor_stops')->cascadeOnDelete();
                $table->timestamp('arrival_time')->nullable();
                $table->timestamp('departure_time')->nullable();
                $table->boolean('is_terminal')->default(false);
                $table->json('metadata')->nullable();
                $table->timestamps();

                $table->index(['bus_route_assignment_id', 'arrival_time']);
                $table->index(['trip_id', 'arrival_time']);
            });
        }

        if (! Schema::hasTable('passenger_boarding_events')) {
            Schema::create('passenger_boarding_events', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('passenger_route_boarding_id')->constrained('passenger_route_boardings')->cascadeOnDelete();
                $table->foreignId('trip_id')->constrained('trips')->cascadeOnDelete();
                $table->foreignId('passenger_id')->constrained('mobile_users')->cascadeOnDelete();
                $table->foreignId('boarding_stop_id')->constrained('corridor_stops')->cascadeOnDelete();
                $table->foreignId('destination_stop_id')->constrained('corridor_stops')->cascadeOnDelete();
                $table->foreignId('verified_by_driver_id')->nullable()->constrained('drivers')->nullOnDelete();
                $table->string('status', 32)->default('boarded');
                $table->timestamp('boarded_at')->nullable();
                $table->timestamp('verified_at')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();

                $table->index(['trip_id', 'status']);
                $table->index(['passenger_id', 'status']);
            });
        }

        Schema::table('transport_corridors', function (Blueprint $table): void {
            if (! Schema::hasColumn('transport_corridors', 'start_stop_id')) {
                return;
            }

            try {
                $table->foreign('start_stop_id')->references('id')->on('corridor_stops')->nullOnDelete();
            } catch (\Throwable) {
                // Constraint may already exist on repeated deploys.
            }

            try {
                $table->foreign('end_stop_id')->references('id')->on('corridor_stops')->nullOnDelete();
            } catch (\Throwable) {
                // Constraint may already exist on repeated deploys.
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('passenger_boarding_events');
        Schema::dropIfExists('stop_arrival_events');
        Schema::dropIfExists('bus_position_updates');
        Schema::dropIfExists('passenger_route_boardings');
        Schema::dropIfExists('bus_route_assignments');
        Schema::dropIfExists('corridor_stop_times');
        Schema::dropIfExists('corridor_stops');
        Schema::dropIfExists('transport_corridors');
    }
};