<?php

namespace App\Services\EventSourcing;

use App\Contracts\EventBusInterface;
use App\Models\EventOutbox;
use Illuminate\Support\Facades\Log;

/**
 * EventPublisherService
 *
 * Reads pending rows from the outbox and publishes to the configured
 * EventBus driver (database | kafka | pulsar).
 *
 * Invoked by ProcessOutboxJob which runs on the 'events' queue.
 * Implements idempotent publishing via event_id deduplication.
 */
class EventPublisherService
{
    public function __construct(
        private readonly EventBusInterface $eventBus,
        private readonly OutboxService     $outbox,
    ) {}

    public function publishPending(int $batchSize = 100): array
    {
        $results = ['published' => 0, 'failed' => 0, 'skipped' => 0];

        $pending = $this->outbox->fetchPending($batchSize);

        foreach ($pending as $record) {
            $this->outbox->incrementAttempts($record->id);

            try {
                $this->eventBus->publish($record->toEventArray());
                $this->outbox->markPublished($record->id);
                $results['published']++;
            } catch (\Throwable $e) {
                $this->outbox->markFailed($record->id, $e->getMessage());
                $results['failed']++;

                Log::warning('EventPublisher: failed to publish outbox record', [
                    'id'         => $record->id,
                    'event_type' => $record->event_type,
                    'error'      => $e->getMessage(),
                ]);
            }
        }

        return $results;
    }
}
