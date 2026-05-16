<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trips', function (Blueprint $table): void {
            if (! Schema::hasColumn('trips', 'pickup_place_name')) {
                $table->string('pickup_place_name')->nullable()->after('pickup_lng');
            }

            if (! Schema::hasColumn('trips', 'dropoff_place_name')) {
                $table->string('dropoff_place_name')->nullable()->after('dropoff_lng');
            }
        });
    }

    public function down(): void
    {
        Schema::table('trips', function (Blueprint $table): void {
            if (Schema::hasColumn('trips', 'dropoff_place_name')) {
                $table->dropColumn('dropoff_place_name');
            }

            if (Schema::hasColumn('trips', 'pickup_place_name')) {
                $table->dropColumn('pickup_place_name');
            }
        });
    }
};
