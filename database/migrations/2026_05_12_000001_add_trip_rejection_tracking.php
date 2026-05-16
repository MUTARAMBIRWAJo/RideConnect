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
        // Add tracking columns to trips table
        Schema::table('trips', function (Blueprint $table) {
            if (! Schema::hasColumn('trips', 'accepted_at')) {
                $table->timestamp('accepted_at')->nullable()->after('requested_at');
            }
            if (! Schema::hasColumn('trips', 'rejected_drivers_count')) {
                $table->integer('rejected_drivers_count')->default(0)->after('status');
            }
        });

        // Create trip_rejections table to track rejection patterns
        Schema::create('trip_rejections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trip_id')->constrained('trips')->onDelete('cascade');
            $table->foreignId('driver_id')->constrained('mobile_users')->onDelete('cascade');
            $table->string('reason', 255)->default('Driver declined');
            $table->timestamps();

            // Index for analytics and pattern analysis
            $table->index(['trip_id', 'driver_id']);
            $table->index(['driver_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('trips', function (Blueprint $table) {
            $table->dropColumn('accepted_at', 'rejected_drivers_count');
        });

        Schema::dropIfExists('trip_rejections');
    }
};
