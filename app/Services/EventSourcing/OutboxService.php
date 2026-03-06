<?php

namespace App\Services\EventSourcing;

use App\Events\Domain\DomainEvent;
use App\Models\EventOutbox;
use Illuminate\Support\Facades\Log;

/**
 * OutboxService — implements the Transactional Outbox Pattern.
 *
 * Writes domain events to the event_outbox table within the same
 * database transaction as business data, guaranteeing at-least-once
 * delivery to the message broker (Kafka / Pulsar).
 *
 * A separate worker (ProcessOutboxJob) polls for pending rows and
 * publishes them to the configured EventBus.
 */
class OutboxService
{
    public function enqueue(DomainEvent $event, ?string $topic = null): EventOutbox
    {
        return EventOutbox::create($event->toOutboxRecord($topic ?? ''));
    }

    /**
     * Enqueue multiple events in one round-trip.
     *
     * @param  DomainEvent[]  $events
     */
    public function enqueueBatch(array $events, ?string $topic = null): void
    {
        $rows = array_map(
            fn (DomainEvent $e) => array_merge($e->toOutboxRecord($topic ?? ''), [
                'created_at' => now(),
                'updated_at' => now(),
            ]),
            $events
        );

        EventOutbox::insert($rows);
    }

    /**
     * Fetch a batch of pending outbox records for publishing.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, EventOutbox>
     */
    public function fetchPending(int $batchSize = 100): \Illuminate\Database\Eloquent\Collection
    {
        return EventOutbox::where('status', 'pending')
            ->where('attempts', '<', 5)
            ->orderBy('occurred_at')
            ->limit($batchSize)
            ->get();
    }

    public function markPublished(int $id): void
    {
        EventOutbox::where('id', $id)->update([
            'status'       => 'published',
            'published_at' => now(),
        ]);
    }

    public function markFailed(int $id, string $error): void
    {
        EventOutbox::where('id', $id)->update([
            'status'     => 'failed',
            'last_error' => $error,
        ]);

        Log::error('Outbox event publish failed', ['outbox_id' => $id, 'error' => $error]);
    }

    public function incrementAttempts(int $id): void
    {
        EventOutbox::where('id', $id)->increment('attempts');
    }
}
