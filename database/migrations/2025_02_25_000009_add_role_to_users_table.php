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
        Schema::table('users', function (Blueprint $table) {
            $table->enum('role', [
                'SUPER_ADMIN', 
                'ADMIN', 
                'ACCOUNTANT', 
                'OFFICER', 
                'DRIVER', 
                'PASSENGER'
            ])->default('PASSENGER')->after('email');
            
            // Add reference columns for linking to mobile_users or managers
            $table->unsignedBigInteger('mobile_user_id')->nullable()->after('role');
            $table->unsignedBigInteger('manager_id')->nullable()->after('mobile_user_id');
            
            // Add other useful columns
            $table->string('phone')->nullable()->after('manager_id');
            $table->string('profile_photo')->nullable()->after('phone');
            $table->boolean('is_verified')->default(false)->after('profile_photo');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'role',
                'mobile_user_id',
                'manager_id',
                'phone',
                'profile_photo',
                'is_verified'
            ]);
        });
    }
};
