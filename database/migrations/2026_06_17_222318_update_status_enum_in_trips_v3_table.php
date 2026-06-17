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
            $table->dropColumn('status');
        });
        Schema::table('trips_v3', function (Blueprint $table) {
            $table->string('status')->default('PENDING_MATCH')->index();
        });
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::warning('Migration 2026_06_17_222318_update_status_enum_in_trips_v3_table.php skipped: ' . $e->getMessage());
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('trips_v3', function (Blueprint $table) {
            //
        });
    }
};
