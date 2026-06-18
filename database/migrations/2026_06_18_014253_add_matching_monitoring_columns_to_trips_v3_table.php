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
        Schema::table('trips_v3', function (Blueprint $table) {
            $table->timestamp('matching_started_at')->nullable();
            $table->timestamp('matching_timeout_at')->nullable();
            $table->boolean('fallback_match_used')->default(false);
            $table->timestamp('matched_at')->nullable();
        });
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
