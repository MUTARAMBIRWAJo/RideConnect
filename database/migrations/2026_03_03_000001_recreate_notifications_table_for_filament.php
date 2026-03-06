<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Filament's databaseNotifications() requires the standard Laravel
     * notifications table (UUID primary key + morphable notifiable columns).
     * The original custom "notifications" table is renamed to
     * "user_notifications" so its data is preserved, and a fresh
     * standard-compliant table is created in its place.
     *
     * The data column must be jsonb (not text) so that PostgreSQL's ->>
     * JSON extraction operator used by Filament works correctly.
     */
    public function up(): void
    {
        // Preserve the existing custom notifications data under a new name
        Schema::rename('notifications', 'user_notifications');

        // Create the standard Laravel/Filament notifications table
        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->morphs('notifiable');   // adds notifiable_type + notifiable_id
            $table->jsonb('data');           // must be jsonb for PostgreSQL ->> operator
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notifications');
        Schema::rename('user_notifications', 'notifications');
    }
};
