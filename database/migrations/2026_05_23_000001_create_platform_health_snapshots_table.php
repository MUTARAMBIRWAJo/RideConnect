<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_health_snapshots', function (Blueprint $table): void {
            $table->id();
            $table->string('snapshot_type', 40)->default('platform');
            $table->string('overall_status', 20)->nullable();
            $table->string('database_status', 20)->nullable();
            $table->string('queue_status', 20)->nullable();
            $table->string('cache_status', 20)->nullable();
            $table->unsignedInteger('queue_pending')->nullable();
            $table->unsignedInteger('database_connections')->nullable();
            $table->unsignedSmallInteger('ai_prediction_response_time_ms')->nullable();
            $table->unsignedSmallInteger('successful_checks')->default(0);
            $table->unsignedSmallInteger('total_checks')->default(0);
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['snapshot_type', 'created_at']);
            $table->index(['overall_status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_health_snapshots');
    }
};