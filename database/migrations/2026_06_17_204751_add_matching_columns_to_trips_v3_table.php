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
        try {
        Schema::table('trips_v3', function (Blueprint $table) {
            $table->string('driver_response_status')->default('pending')->index();
            $table->foreignId('matched_driver_id')->nullable()->constrained('drivers')->onDelete('set null');
            $table->integer('match_attempt_count')->default(0);
            $table->timestamp('last_matched_at')->nullable();
            $table->jsonb('ignored_driver_ids')->nullable();
        });
        
        // Alter status column to be a string instead of enum to allow the new uppercase statuses
        \Illuminate\Support\Facades\DB::statement("ALTER TABLE trips_v3 DROP COLUMN status");
        Schema::table('trips_v3', function (Blueprint $table) {
            $table->string('status')->default('SEARCHING')->index();
        });
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::warning('Migration 2026_06_17_204751_add_matching_columns_to_trips_v3_table.php skipped: ' . $e->getMessage());
        }
    }

    public function down(): void
    {
        Schema::table('trips_v3', function (Blueprint $table) {
            $table->dropColumn([
                'driver_response_status',
                'matched_driver_id',
                'match_attempt_count',
                'last_matched_at',
                'ignored_driver_ids',
            ]);
        });
        
        \Illuminate\Support\Facades\DB::statement("ALTER TABLE trips_v3 DROP COLUMN status");
        Schema::table('trips_v3', function (Blueprint $table) {
            $table->enum('status', ['created', 'searching', 'assigned', 'active', 'completed', 'cancelled', 'expired'])->default('created')->index();
        });
    }
};
