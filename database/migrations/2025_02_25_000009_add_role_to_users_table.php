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
            if (! Schema::hasColumn('users', 'role')) {
                $table->enum('role', [
                    'SUPER_ADMIN',
                    'ADMIN',
                    'ACCOUNTANT',
                    'OFFICER',
                    'DRIVER',
                    'PASSENGER',
                ])->default('PASSENGER')->after('email');
            }

            // Add reference columns for linking to mobile_users or managers
            if (! Schema::hasColumn('users', 'mobile_user_id')) {
                $table->unsignedBigInteger('mobile_user_id')->nullable()->after('role');
            }

            if (! Schema::hasColumn('users', 'manager_id')) {
                $table->unsignedBigInteger('manager_id')->nullable()->after('mobile_user_id');
            }

            // Add other useful columns
            if (! Schema::hasColumn('users', 'phone')) {
                $table->string('phone')->nullable()->after('manager_id');
            }

            if (! Schema::hasColumn('users', 'profile_photo')) {
                $table->string('profile_photo')->nullable()->after('phone');
            }

            if (! Schema::hasColumn('users', 'is_verified')) {
                $table->boolean('is_verified')->default(false)->after('profile_photo');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        foreach ([
            'role',
            'mobile_user_id',
            'manager_id',
            'phone',
            'profile_photo',
            'is_verified',
        ] as $column) {
            if (Schema::hasColumn('users', $column)) {
                Schema::table('users', function (Blueprint $table) use ($column) {
                    $table->dropColumn($column);
                });
            }
        }
    }
};
