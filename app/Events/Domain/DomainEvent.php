<?php

namespace App\Events\Domain;

use Illuminate\Foundation\Events\Dispatchable;

abstract class DomainEvent
{
    use Dispatchable;

    public readonly string $eventId;
    public readonly string $occurredAt;
    public readonly int    $version;

    public function __construct()
    {
        $this->eventId    = (string) \Illuminate\Support\Str::uuid();
        $this->occurredAt = now()->toIso8601String();
        $this->version    = static::VERSION;
    }

    abstract public function aggregateId(): string;

    abstract public function aggregateType(): string;

    abstract public function toPayload(): array;

    public function eventType(): string
    {
        return class_basename(static::class);
    }

    public function toOutboxRecord(string $topic = ''): array
    {
        return [
            'event_id'       => $this->eventId,
            'event_type'     => $this->eventType(),
            'aggregate_id'   => $this->aggregateId(),
            'aggregate_type' => $this->aggregateType(),
            'payload'        => $this->toPayload(),
            'version'        => $this->version,
            'occurred_at'    => $this->occurredAt,
            'topic'          => $topic ?: $this->defaultTopic(),
        ];
    }

    protected function defaultTopic(): string
    {
        return 'rideconnect.finance.' . strtolower((string) preg_replace('/(?<!^)[A-Z]/', '-$0', $this->eventType()));
    }

    public const VERSION = 1;
}
