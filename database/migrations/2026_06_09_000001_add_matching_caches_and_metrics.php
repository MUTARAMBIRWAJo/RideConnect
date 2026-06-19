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
        if (Schema::hasTable('motorcycle_trips')) {
            $hasMatchingDuration = Schema::hasColumn('motorcycle_trips', 'matching_duration_seconds');
            $hasCandidateCount = Schema::hasColumn('motorcycle_trips', 'candidate_count');
            $hasMatchedVia = Schema::hasColumn('motorcycle_trips', 'matched_via');
            $hasMatchingMetadata = Schema::hasColumn('motorcycle_trips', 'matching_metadata');

            if (!$hasMatchingDuration || !$hasCandidateCount || !$hasMatchedVia || !$hasMatchingMetadata) {
                Schema::table('motorcycle_trips', function (Blueprint $table) use ($hasMatchingDuration, $hasCandidateCount, $hasMatchedVia, $hasMatchingMetadata) {
                    if (!$hasMatchingDuration) {
                        $table->unsignedInteger('matching_duration_seconds')->nullable()->after('max_retries');
                    }
                    if (!$hasCandidateCount) {
                        $table->unsignedInteger('candidate_count')->nullable()->after('matching_duration_seconds');
                    }
                    if (!$hasMatchedVia) {
                        $table->string('matched_via', 32)->nullable()->after('candidate_count');
                    }
                    if (!$hasMatchingMetadata) {
                        $table->json('matching_metadata')->nullable()->after('matched_via');
                    }
                });
            }
        }

        // Task 7: public bus trip lifecycle status — add to trips table if not present.
        if (Schema::hasTable('trips')) {
            $hasDriverArrived = Schema::hasColumn('trips', 'driver_arrived_at');
            $hasPassengerWaiting = Schema::hasColumn('trips', 'passenger_waiting_at');
            $hasStarted = Schema::hasColumn('trips', 'started_at');

            if (!$hasDriverArrived || !$hasPassengerWaiting || !$hasStarted) {
                Schema::table('trips', function (Blueprint $table) use ($hasDriverArrived, $hasPassengerWaiting, $hasStarted) {
                    if (!$hasDriverArrived) {
                        $table->timestamp('driver_arrived_at')->nullable()->after('accepted_at');
                    }
                    if (!$hasPassengerWaiting) {
                        $table->timestamp('passenger_waiting_at')->nullable()->after('driver_arrived_at');
                    }
                    if (!$hasStarted) {
                        $table->timestamp('started_at')->nullable()->after('passenger_waiting_at');
                    }
                });
            }
        }

        // Idempotency guard for parallel driver notifications (Task 2 + Task 8).
        if (Schema::hasTable('trips')) {
            $hasDriverNotificationBatchId = Schema::hasColumn('trips', 'driver_notification_batch_id');
            $hasBatchDispatchedAt = Schema::hasColumn('trips', 'batch_dispatched_at');

            if (!$hasDriverNotificationBatchId || !$hasBatchDispatchedAt) {
                Schema::table('trips', function (Blueprint $table) use ($hasDriverNotificationBatchId, $hasBatchDispatchedAt) {
                    if (!$hasDriverNotificationBatchId) {
                        $table->string('driver_notification_batch_id', 64)->nullable()->unique()->after('idempotency_key');
                    }
                    if (!$hasBatchDispatchedAt) {
                        $table->timestamp('batch_dispatched_at')->nullable()->after('driver_notification_batch_id');
                    }
                });
            }
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
