<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Passenger profile fields
            $table->string('preferred_payment_method')->nullable()->default('card')->after('profile_photo');
            $table->string('emergency_contact_name')->nullable()->after('preferred_payment_method');
            $table->string('emergency_contact_phone')->nullable()->after('emergency_contact_name');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'preferred_payment_method',
                'emergency_contact_name',
                'emergency_contact_phone',
            ]);
        });
    }
};
