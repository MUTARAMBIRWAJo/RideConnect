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
        Schema::table('mobile_device_tokens', function (Blueprint $table) {
            // Add missing columns
            if (!Schema::hasColumn('mobile_device_tokens', 'is_active')) {
                $table->boolean('is_active')->default(true)->after('device_id');
            }
            
            if (!Schema::hasColumn('mobile_device_tokens', 'last_used_at')) {
                $table->timestamp('last_used_at')->nullable()->after('last_seen_at');
            }
            
            if (!Schema::hasColumn('mobile_device_tokens', 'app_version')) {
                $table->string('app_version', 20)->nullable()->after('platform');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('mobile_device_tokens', function (Blueprint $table) {
            $table->dropColumn(['is_active', 'last_used_at', 'app_version']);
        });
    }
};
