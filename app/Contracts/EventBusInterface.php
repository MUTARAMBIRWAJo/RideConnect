<?php

namespace App\Contracts;

/**
 * EventBusInterface — abstraction layer over Kafka, Pulsar, or database-backed bus.
 *
 * Configure via EVENT_BUS_DRIVER in .env:
 *   database  → stores events in event_outbox table (default)
 *   kafka     → publishes to Kafka broker
 *   pulsar    → publishes to Apache Pulsar
 */
interface EventBusInterface
{
    /**
     * Publish a domain event to the bus.
     *
     * @param  array{event_id: string, event_type: string, aggregate_id: string, aggregate_type: string, payload: array, version: int, occurred_at: string, topic?: string}  $event
     */
    public function publish(array $event): void;

    /**
     * Publish multiple events atomically (fan-out).
     *
     * @param  array<int, array>  $events
     */
    public function publishBatch(array $events): void;

    /**
     * Subscribe a handler to an event type or topic.
     * Only relevant for long-running consumer processes.
     */
    public function subscribe(string $eventType, callable $handler): void;
}
