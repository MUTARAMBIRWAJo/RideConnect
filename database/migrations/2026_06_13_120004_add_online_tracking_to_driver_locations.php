<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        try {
        if (!Schema::hasTable('driver_locations')) {
            return;
        }

        $hasOnline = Schema::hasColumn('driver_locations', 'is_online');
        $hasActivity = Schema::hasColumn('driver_locations', 'last_activity_at');

        if (!$hasOnline || !$hasActivity) {
            Schema::table('driver_locations', function (Blueprint $table) use ($hasOnline, $hasActivity) {
                // Add online status tracking
                if (!$hasOnline) {
                    $table->boolean('is_online')->default(false);
                }

                // Add last activity timestamp
                if (!$hasActivity) {
                    $table->timestamp('last_activity_at')->nullable();
                }

                // Add indexes for performance
                $table->index('is_online');
                $table->index('last_activity_at');
                $table->index(['is_online', 'last_activity_at']);
            });
        }
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::warning('Migration 2026_06_13_120004_add_online_tracking_to_driver_locations.php skipped: ' . $e->getMessage());
        }
    }

    public function down(): void
    {
        Schema::table('driver_locations', function (Blueprint $table) {
            $table->dropIndex(['is_online']);
            $table->dropIndex(['last_activity_at']);
            $table->dropIndex(['is_online', 'last_activity_at']);
            $table->dropColumn(['is_online', 'last_activity_at']);
        });
    }
};
