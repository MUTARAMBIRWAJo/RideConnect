<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('mobile_trip_e2e_use_cases')) {
            Schema::create('mobile_trip_e2e_use_cases', function (Blueprint $table): void {
                $table->id();
                $table->string('slug')->unique();
                $table->string('title');
                $table->string('transport_type', 32);
                $table->string('passenger_page');
                $table->json('passenger_flow');
                $table->json('driver_flow');
                $table->json('api_payloads');
                $table->json('api_responses');
                $table->json('expected_ui');
                $table->json('notifications');
                $table->json('matching_engine_results');
                $table->json('tracking_updates');
                $table->json('backend_validation');
                $table->json('database_validation');
                $table->json('failure_simulations');
                $table->json('correction_prompts');
                $table->json('pass_fail_validations');
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('notification_deliveries')) {
            Schema::create('notification_deliveries', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('notification_id')->constrained('user_notifications')->cascadeOnDelete();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->string('channel', 32)->default('push');
                $table->string('status', 32)->default('queued');
                $table->timestamp('delivered_at')->nullable();
                $table->timestamp('acknowledged_at')->nullable();
                $table->json('payload')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();

                $table->index(['notification_id', 'status']);
                $table->index(['user_id', 'acknowledged_at']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_deliveries');
        Schema::dropIfExists('mobile_trip_e2e_use_cases');
    }
};
