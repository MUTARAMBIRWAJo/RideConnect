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
        Schema::table('driver_locations', function (Blueprint $table) {
            $table->decimal('speed_kmh', 5, 2)->nullable()->after('longitude');
            $table->decimal('heading', 5, 1)->nullable()->after('speed_kmh'); // degrees (0-360)
            $table->decimal('accuracy', 6, 2)->nullable()->after('heading'); // meters
            $table->timestamp('last_activity_at')->nullable()->after('updated_at');
            $table->boolean('is_online')->default(false)->after('last_activity_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('driver_locations', function (Blueprint $table) {
            $table->dropColumn(['speed_kmh', 'heading', 'accuracy', 'last_activity_at', 'is_online']);
        });
    }
};
