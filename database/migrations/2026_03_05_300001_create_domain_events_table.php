<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('domain_events', function (Blueprint $table) {
            $table->id();
            $table->uuid('event_id')->unique();
            $table->string('event_type', 100)->index();
            $table->string('aggregate_id', 100)->index();
            $table->string('aggregate_type', 100)->index();
            $table->jsonb('payload');
            $table->unsignedSmallInteger('version')->default(1);
            $table->timestamp('occurred_at')->index();
            $table->boolean('processed')->default(false)->index();
            $table->timestamp('processed_at')->nullable();
            $table->string('processor_id', 100)->nullable();   // idempotency key per consumer
            $table->unsignedSmallInteger('retry_count')->default(0);
            $table->text('last_error')->nullable();
            $table->timestamps();

            $table->index(['event_type', 'processed']);
            $table->index(['aggregate_type', 'aggregate_id', 'version']);
            $table->index(['occurred_at', 'processed']);

            // Immutability enforced by payload hash
            $table->string('payload_hash', 64)->nullable();
        });

        // Prevent UPDATE on domain_events (append-only log)
        DB::statement(<<<'SQL'
            CREATE OR REPLACE FUNCTION prevent_domain_event_mutation()
            RETURNS TRIGGER LANGUAGE plpgsql AS $$
            BEGIN
                IF OLD.event_id IS NOT NULL THEN
                    RAISE EXCEPTION 'domain_events rows are immutable (event_id=%). Only processed/processed_at may be updated via explicit function.', OLD.event_id;
                END IF;
                RETURN NEW;
            END;
            $$;
        SQL);

        DB::statement(<<<'SQL'
            CREATE TRIGGER trg_domain_events_immutable
            BEFORE UPDATE OF payload, event_type, aggregate_id, aggregate_type, occurred_at, payload_hash
            ON domain_events
            FOR EACH ROW EXECUTE FUNCTION prevent_domain_event_mutation();
        SQL);
    }

    public function down(): void
    {
        DB::statement('DROP TRIGGER IF EXISTS trg_domain_events_immutable ON domain_events');
        DB::statement('DROP FUNCTION IF EXISTS prevent_domain_event_mutation()');
        Schema::dropIfExists('domain_events');
    }
};
