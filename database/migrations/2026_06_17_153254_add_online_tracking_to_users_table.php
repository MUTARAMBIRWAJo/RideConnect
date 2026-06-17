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
            $table->timestamp('last_seen_at')->nullable();
            $table->boolean('is_online')->default(false);
            $table->string('current_device_id')->nullable();
            $table->foreignId('current_token_id')->nullable()->constrained('personal_access_tokens')->nullOnDelete();
        });
        
        Schema::table('drivers', function (Blueprint $table) {
            $table->timestamp('last_seen_at')->nullable();
            $table->boolean('is_online')->default(false);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['current_token_id']);
            $table->dropColumn([
                'last_seen_at',
                'is_online',
                'current_device_id',
                'current_token_id'
            ]);
        });
        
        Schema::table('drivers', function (Blueprint $table) {
            $table->dropColumn([
                'last_seen_at',
                'is_online'
            ]);
        });
    }
};
