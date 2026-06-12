<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Task 3: driver availability cache (DB-backed; Redis activation is a 5-line
        // config change when infra is provisioned, the cache-write paths are already here).
        if (! Schema::hasTable('driver_availability_cache')) {
            Schema::create('driver_availability_cache', function (Blueprint $table) {
                $table->id();
                $table->foreignId('driver_id')->unique()->constrained()->cascadeOnDelete();
                $table->string('vehicle_type', 32)->nullable();
                $table->decimal('current_lat', 10, 7)->nullable();
                $table->decimal('current_lng', 10, 7)->nullable();
                $table->decimal('availability_score', 5, 2)->nullable();
                $table->boolean('is_online')->default(false);
                $table->boolean('is_available')->default(false);
                $table->timestamp('last_seen_at')->nullable();
                $table->timestamp('updated_at');

                $table->index(['vehicle_type', 'is_available']);
                $table->index(['is_online', 'is_available']);
                $table->index(['current_lat', 'current_lng']);
            });
        }

        // Task 4: matching metrics on motorcycle_trips.
        if (Schema::hasTable('motorcycle_trips') && ! Schema::hasColumn('motorcycle_trips', 'matching_duration_seconds')) {
            Schema::table('motorcycle_trips', function (Blueprint $table) {
                $table->unsignedInteger('matching_duration_seconds')->nullable()->after('max_retries');
                if (! Schema::hasColumn('motorcycle_trips', 'candidate_count')) {
                    $table->unsignedInteger('candidate_count')->nullable()->after('matching_duration_seconds');
                }
                if (! Schema::hasColumn('motorcycle_trips', 'matched_via')) {
                    $table->string('matched_via', 32)->nullable()->after('candidate_count');
                }
                if (! Schema::hasColumn('motorcycle_trips', 'matching_metadata')) {
                    $table->json('matching_metadata')->nullable()->after('matched_via');
                }
            });
        }

        // Task 7: public bus trip lifecycle status — add to trips table if not present.
        if (Schema::hasTable('trips') && ! Schema::hasColumn('trips', 'passenger_waiting_at')) {
            Schema::table('trips', function (Blueprint $table) {
                if (! Schema::hasColumn('trips', 'driver_arrived_at')) {
                    $table->timestamp('driver_arrived_at')->nullable()->after('accepted_at');
                }
                if (! Schema::hasColumn('trips', 'passenger_waiting_at')) {
                    $table->timestamp('passenger_waiting_at')->nullable()->after('driver_arrived_at');
                }
                if (! Schema::hasColumn('trips', 'started_at')) {
                    $table->timestamp('started_at')->nullable()->after('passenger_waiting_at');
                }
            });
        }

        // Idempotency guard for parallel driver notifications (Task 2 + Task 8).
        if (Schema::hasTable('trips') && ! Schema::hasColumn('trips', 'driver_notification_batch_id')) {
            Schema::table('trips', function (Blueprint $table) {
                $table->string('driver_notification_batch_id', 64)->nullable()->unique()->after('idempotency_key');
                if (! Schema::hasColumn('trips', 'batch_dispatched_at')) {
                    $table->timestamp('batch_dispatched_at')->nullable()->after('driver_notification_batch_id');
                }
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('driver_availability_cache');
        Schema::table('motorcycle_trips', function (Blueprint $table) {
            $table->dropColumn([
                'matching_duration_seconds',
                'candidate_count',
                'matched_via',
                'matching_metadata',
            ]);
        });
        Schema::table('trips', function (Blueprint $table) {
            $table->dropColumn([
                'driver_arrived_at',
                'passenger_waiting_at',
                'started_at',
                'driver_notification_batch_id',
                'batch_dispatched_at',
            ]);
        });
    }
};
