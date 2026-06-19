<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $hasMatchingStarted = Schema::hasColumn('trips_v3', 'matching_started_at');
        $hasMatchingTimeout = Schema::hasColumn('trips_v3', 'matching_timeout_at');
        $hasFallbackMatch = Schema::hasColumn('trips_v3', 'fallback_match_used');
        $hasMatched = Schema::hasColumn('trips_v3', 'matched_at');

        if (!$hasMatchingStarted || !$hasMatchingTimeout || !$hasFallbackMatch || !$hasMatched) {
            Schema::table('trips_v3', function (Blueprint $table) use ($hasMatchingStarted, $hasMatchingTimeout, $hasFallbackMatch, $hasMatched) {
                if (!$hasMatchingStarted) {
                    $table->timestamp('matching_started_at')->nullable();
                }
                if (!$hasMatchingTimeout) {
                    $table->timestamp('matching_timeout_at')->nullable();
                }
                if (!$hasFallbackMatch) {
                    $table->boolean('fallback_match_used')->default(false);
                }
                if (!$hasMatched) {
                    $table->timestamp('matched_at')->nullable();
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('trips_v3', function (Blueprint $table) {
            $table->dropColumn([
                'matching_started_at',
                'matching_timeout_at',
                'fallback_match_used',
                'matched_at',
            ]);
        });
    }
};
