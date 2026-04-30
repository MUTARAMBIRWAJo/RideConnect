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
        Schema::table('rides', function (Blueprint $table) {
            $table->enum('transport_type', ['BUS', 'CAR', 'MOTORCYCLE'])->default('CAR')->after('id');
            $table->enum('travel_mode', ['SCHEDULED', 'ON_DEMAND'])->default('ON_DEMAND')->after('transport_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('rides', function (Blueprint $table) {
            $table->dropColumn(['transport_type', 'travel_mode']);
        });
    }
};
