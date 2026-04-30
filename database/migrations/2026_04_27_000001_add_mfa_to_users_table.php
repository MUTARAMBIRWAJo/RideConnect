<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Google OAuth & MFA columns
            $table->string('google_id')->nullable()->unique()->after('id');
            $table->boolean('two_factor_enabled')->default(false)->after('google_id');
            $table->string('two_factor_secret')->nullable()->after('two_factor_enabled');
            $table->timestamp('two_factor_confirmed_at')->nullable()->after('two_factor_secret');
            $table->json('two_factor_backup_codes')->nullable()->after('two_factor_confirmed_at');
            
            // Brute force protection
            $table->integer('mfa_attempts')->default(0)->after('two_factor_backup_codes');
            $table->timestamp('mfa_locked_until')->nullable()->after('mfa_attempts');
            
            // Session security
            $table->string('last_login_ip')->nullable()->after('mfa_locked_until');
            $table->string('last_login_user_agent')->nullable()->after('last_login_ip');
            $table->timestamp('last_login_at')->nullable()->after('last_login_user_agent');
        });
    }

    public function rollback(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'google_id',
                'two_factor_enabled',
                'two_factor_secret',
                'two_factor_confirmed_at',
                'two_factor_backup_codes',
                'mfa_attempts',
                'mfa_locked_until',
                'last_login_ip',
                'last_login_user_agent',
                'last_login_at',
            ]);
        });
    }
};
