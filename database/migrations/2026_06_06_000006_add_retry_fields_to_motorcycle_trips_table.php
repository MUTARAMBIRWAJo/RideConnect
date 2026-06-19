<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('motorcycle_trips')) {
            $hasRetryCount = Schema::hasColumn('motorcycle_trips', 'retry_count');
            $hasMaxRetries = Schema::hasColumn('motorcycle_trips', 'max_retries');
            $hasMatchingStatus = Schema::hasColumn('motorcycle_trips', 'matching_status');
            $hasLastRetryAt = Schema::hasColumn('motorcycle_trips', 'last_retry_at');
            $hasInitialRadius = Schema::hasColumn('motorcycle_trips', 'initial_search_radius_km');
            $hasCurrentRadius = Schema::hasColumn('motorcycle_trips', 'current_search_radius_km');

            if (!$hasRetryCount || !$hasMaxRetries || !$hasMatchingStatus || !$hasLastRetryAt || !$hasInitialRadius || !$hasCurrentRadius) {
                Schema::table('motorcycle_trips', function (Blueprint $table) use ($hasRetryCount, $hasMaxRetries, $hasMatchingStatus, $hasLastRetryAt, $hasInitialRadius, $hasCurrentRadius) {
                    // Retry tracking
                    if (!$hasRetryCount) {
                        $table->unsignedInteger('retry_count')->default(0)->after('status');
                    }
                    
                    if (!$hasMaxRetries) {
                        $table->unsignedInteger('max_retries')->default(5)->after('retry_count');
                    }
                    
                    if (!$hasMatchingStatus) {
                        $table->string('matching_status')->nullable()->after('max_retries');
                        // Possible values: SEARCHING, RETRYING, RETRY_SCHEDULED, NO_DRIVERS_AVAILABLE, DRIVER_FOUND
                    }
                    
                    if (!$hasLastRetryAt) {
                        $table->timestamp('last_retry_at')->nullable()->after('matching_status');
                    }
                    
                    if (!$hasInitialRadius) {
                        $table->decimal('initial_search_radius_km', 10, 2)->default(5)->after('last_retry_at');
                    }
                    
                    if (!$hasCurrentRadius) {
                        $table->decimal('current_search_radius_km', 10, 2)->default(5)->after('initial_search_radius_km');
                    }
                });
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('motorcycle_trips')) {
            Schema::table('motorcycle_trips', function (Blueprint $table) {
                $table->dropColumn([
                    'retry_count',
                    'max_retries',
                    'matching_status',
                    'last_retry_at',
                    'initial_search_radius_km',
                    'current_search_radius_km',
                ]);
            });
        }
    }
};
