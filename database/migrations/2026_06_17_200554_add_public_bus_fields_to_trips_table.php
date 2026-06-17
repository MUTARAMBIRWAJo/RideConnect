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
        Schema::table('trips', function (Blueprint $table) {
            $table->integer('capacity_used')->default(0)->after('fare');
            $table->integer('capacity_total')->default(0)->after('capacity_used');
            $table->unsignedBigInteger('route_id')->nullable()->after('capacity_total');
            $table->unsignedBigInteger('bus_id')->nullable()->after('route_id');
            $table->jsonb('boarding_status')->nullable()->after('bus_id');
        });
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::warning('Migration 2026_06_17_200554_add_public_bus_fields_to_trips_table.php skipped: ' . $e->getMessage());
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('trips', function (Blueprint $table) {
            $table->dropColumn([
                'capacity_used',
                'capacity_total',
                'route_id',
                'bus_id',
                'boarding_status',
            ]);
        });
    }
};
