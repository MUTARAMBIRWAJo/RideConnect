<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('trip_status_events')) {
            return;
        }

        try {
            Schema::create('trip_status_events', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('trip_id')->constrained('trips')->cascadeOnDelete();
                $table->string('actor_type')->nullable();
                $table->unsignedBigInteger('actor_id')->nullable();
                $table->string('old_status', 32)->nullable();
                $table->string('new_status', 32);
                $table->json('metadata')->nullable();
                $table->timestamp('created_at')->useCurrent();

                $table->index(['trip_id', 'created_at']);
                $table->index(['actor_type', 'actor_id']);
            });
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::warning('Skipping trip_status_events creation: ' . $e->getMessage());
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('trip_status_events');
    }
};
