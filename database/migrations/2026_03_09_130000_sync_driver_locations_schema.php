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
        if (! Schema::hasTable('driver_locations')) {
            return;
        }

        Schema::table('driver_locations', function (Blueprint $table) {
            if (! Schema::hasColumn('driver_locations', 'updated_at')) {
                $table->timestamp('updated_at')->nullable();
            }
        });

        // Normalize null values before enforcing NOT NULL expectations.
        \Illuminate\Support\Facades\DB::table('driver_locations')
            ->whereNull('updated_at')
            ->update(['updated_at' => now()]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('driver_locations')) {
            return;
        }

        if (Schema::hasColumn('driver_locations', 'updated_at')) {
            Schema::table('driver_locations', function (Blueprint $table) {
                $table->dropColumn('updated_at');
            });
        }
    }
};
