<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Transactional outbox: events written in same DB transaction as business data
        // Worker picks up unprocessed rows and publishes to message broker (Kafka/Pulsar/DB)
        Schema::create('event_outbox', function (Blueprint $table) {
            $table->id();
            $table->uuid('event_id')->unique();
            $table->string('event_type', 100)->index();
            $table->string('aggregate_id', 100)->index();
            $table->string('aggregate_type', 100);
            $table->jsonb('payload');
            $table->unsignedSmallInteger('version')->default(1);
            $table->timestamp('occurred_at');
            $table->enum('status', ['pending', 'published', 'failed'])->default('pending')->index();
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->timestamp('published_at')->nullable();
            $table->text('last_error')->nullable();
            $table->string('topic', 200)->nullable(); // Kafka topic / Pulsar topic
            $table->timestamps();

            $table->index(['status', 'attempts']);
            $table->index(['occurred_at', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_outbox');
    }
};
