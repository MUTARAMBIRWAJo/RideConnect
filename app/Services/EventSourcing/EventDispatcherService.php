<?php

namespace App\Services\EventSourcing;

use App\Contracts\EventBusInterface;
use App\Events\Domain\DomainEvent;
use App\Models\DomainEvent as DomainEventModel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * EventDispatcherService
 *
 * Dispatches domain events by:
 *  1. Persisting to domain_events table (immutable log)
 *  2. Writing to event_outbox (same DB transaction as business data)
 *  3. Dispatching Laravel event for in-process listeners
 *  4. Publishing via EventBus (Kafka/Pulsar/DB) after commit
 */
class EventDispatcherService
{
    public function __construct(
        private readonly EventBusInterface    $eventBus,
        private readonly OutboxService        $outbox,
    ) {}

    /**
     * Dispatch a single domain event within the current/given DB transaction.
     * Call inside the same transaction as the business operation for atomicity.
     */
    public function dispatch(DomainEvent $event, ?string $topic = null): void
    {
        // 1. Persist to immutable domain_events log
        $this->persistDomainEvent($event);

        // 2. Write to outbox (published async by worker)
        $this->outbox->enqueue($event, $topic);

        // 3. Dispatch to in-process Laravel listeners
        event($event);

        Log::debug('Domain event dispatched', [
            'event_type'     => $event->eventType(),
            'event_id'       => $event->eventId,
            'aggregate_id'   => $event->aggregateId(),
            'aggregate_type' => $event->aggregateType(),
        ]);
    }

    /**
     * Dispatch multiple events atomically (wraps in transaction if not already in one).
     *
     * @param  DomainEvent[]  $events
     */
    public function dispatchBatch(array $events, ?string $topic = null): void
    {
        DB::transaction(function () use ($events, $topic) {
            foreach ($events as $event) {
                $this->dispatch($event, $topic);
            }
        });
    }

    /**
     * Replay events from the domain_events table for an aggregate.
     * Useful for rebuilding read models or debugging.
     *
     * @return \Illuminate\Support\Collection<int, DomainEventModel>
     */
    public function replay(string $aggregateType, string $aggregateId, int $fromVersion = 1): \Illuminate\Support\Collection
    {
        return DomainEventModel::where('aggregate_type', $aggregateType)
            ->where('aggregate_id', $aggregateId)
            ->where('version', '>=', $fromVersion)
            ->orderBy('occurred_at')
            ->orderBy('version')
            ->get();
    }

    /**
     * Replay all events of a given type (for read model projections).
     *
     * @return \Illuminate\Support\LazyCollection<int, DomainEventModel>
     */
    public function replayByType(string $eventType, ?\DateTimeInterface $from = null): \Illuminate\Support\LazyCollection
    {
        return DomainEventModel::where('event_type', $eventType)
            ->when($from, fn ($q) => $q->where('occurred_at', '>=', $from))
            ->orderBy('occurred_at')
            ->lazy();
    }

    // -----------------------------------------------------------------------

    private function persistDomainEvent(DomainEvent $event): void
    {
        $payload     = $event->toPayload();
        $payloadJson = json_encode($payload, JSON_THROW_ON_ERROR);

        DomainEventModel::create([
            'event_id'       => $event->eventId,
            'event_type'     => $event->eventType(),
            'aggregate_id'   => $event->aggregateId(),
            'aggregate_type' => $event->aggregateType(),
            'payload'        => $payload,
            'version'        => $event->version,
            'occurred_at'    => $event->occurredAt,
            'processed'      => false,
            'payload_hash'   => hash('sha256', $payloadJson),
        ]);
    }
}
