<?php

namespace App\Services\EventSourcing\Drivers;

use App\Contracts\EventBusInterface;
use App\Models\DomainEvent as DomainEventModel;
use Illuminate\Support\Facades\Log;

/**
 * DatabaseEventBus — default EventBus driver.
 *
 * Stores published events back into domain_events with processed=true.
 * Suitable for development, single-service deployments, and testing.
 * Swap for KafkaEventBus / PulsarEventBus in production.
 */
class DatabaseEventBus implements EventBusInterface
{
    public function publish(array $event): void
    {
        DomainEventModel::where('event_id', $event['event_id'])
            ->update([
                'processed'    => true,
                'processed_at' => now(),
            ]);

        Log::info('DatabaseEventBus: event marked processed', [
            'event_id'   => $event['event_id'],
            'event_type' => $event['event_type'],
        ]);
    }

    public function publishBatch(array $events): void
    {
        foreach ($events as $event) {
            $this->publish($event);
        }
    }

    public function subscribe(string $eventType, callable $handler): void
    {
        // Database bus uses Laravel's event system for in-process subscriptions.
        // For cross-service consumption, use Kafka/Pulsar bus.
    }
}
