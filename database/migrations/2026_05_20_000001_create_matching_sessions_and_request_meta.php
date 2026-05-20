<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('matching_sessions')) {
            Schema::create('matching_sessions', function (Blueprint $table): void {
                $table->id();
                $table->uuid('matching_session_id')->unique();
                $table->foreignId('passenger_id')->constrained('mobile_users')->cascadeOnDelete();
                $table->string('transport_type');
                $table->decimal('pickup_lat', 10, 7);
                $table->decimal('pickup_lng', 10, 7);
                $table->decimal('dropoff_lat', 10, 7);
                $table->decimal('dropoff_lng', 10, 7);
                $table->foreignId('selected_driver_id')->nullable()->constrained('drivers')->nullOnDelete();
                $table->json('payload')->nullable();
                $table->string('status', 32)->default('pending');
                $table->timestamp('expires_at')->nullable();
                $table->timestamps();

                $table->index(['passenger_id', 'status']);
                $table->index('expires_at');
            });
        }

        if (Schema::hasTable('trips') && ! Schema::hasColumn('trips', 'matching_session_id')) {
            Schema::table('trips', function (Blueprint $table): void {
                $table->uuid('matching_session_id')->nullable()->after('transport_type');
                $table->string('idempotency_key')->nullable()->after('matching_session_id');
                $table->index(['passenger_id', 'idempotency_key']);
                $table->index('matching_session_id');
            });
        }

        if (Schema::hasTable('bookings') && ! Schema::hasColumn('bookings', 'matching_session_id')) {
            Schema::table('bookings', function (Blueprint $table): void {
                $table->uuid('matching_session_id')->nullable()->after('ride_id');
                $table->string('idempotency_key')->nullable()->after('matching_session_id');
                $table->index(['user_id', 'idempotency_key']);
                $table->index('matching_session_id');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('trips') && Schema::hasColumn('trips', 'matching_session_id')) {
            Schema::table('trips', function (Blueprint $table): void {
                $table->dropIndex(['passenger_id', 'idempotency_key']);
                $table->dropIndex(['matching_session_id']);
                $table->dropColumn(['matching_session_id', 'idempotency_key']);
            });
        }

        if (Schema::hasTable('bookings') && Schema::hasColumn('bookings', 'matching_session_id')) {
            Schema::table('bookings', function (Blueprint $table): void {
                $table->dropIndex(['user_id', 'idempotency_key']);
                $table->dropIndex(['matching_session_id']);
                $table->dropColumn(['matching_session_id', 'idempotency_key']);
            });
        }

        Schema::dropIfExists('matching_sessions');
    }
};
