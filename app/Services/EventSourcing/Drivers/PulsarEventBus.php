<?php

namespace App\Services\EventSourcing\Drivers;

use App\Contracts\EventBusInterface;
use Illuminate\Support\Facades\Log;

/**
 * PulsarEventBus — Apache Pulsar-compatible EventBus driver.
 *
 * Requires: skywinder/pulsar-client or similar PHP Pulsar client.
 * Configure via:
 *   PULSAR_BROKER=pulsar://pulsar:6650
 *   PULSAR_TENANT=rideconnect
 *   PULSAR_NAMESPACE=finance
 *
 * Topic format: persistent://rideconnect/finance/<event-type-kebab>
 */
class PulsarEventBus implements EventBusInterface
{
    public function publish(array $event): void
    {
        $topic = $this->resolveTopic($event['event_type'], $event['topic'] ?? null);

        Log::info('PulsarEventBus: event published (stub)', [
            'event_id' => $event['event_id'],
            'topic'    => $topic,
        ]);

        // Actual Pulsar client integration:
        // $client  = new \Pulsar\Client(['serviceUrl' => config('event_bus.pulsar.broker')]);
        // $producer = $client->createProducer($topic);
        // $producer->send(json_encode($event), ['key' => $event['aggregate_id']]);
        // $producer->close();
        // $client->close();
    }

    public function publishBatch(array $events): void
    {
        foreach ($events as $event) {
            $this->publish($event);
        }
    }

    public function subscribe(string $eventType, callable $handler): void
    {
        throw new \RuntimeException('PulsarEventBus::subscribe() must be used in a dedicated consumer process.');
    }

    private function resolveTopic(string $eventType, ?string $explicitTopic): string
    {
        if ($explicitTopic) return $explicitTopic;

        $tenant    = config('event_bus.pulsar.tenant', 'rideconnect');
        $namespace = config('event_bus.pulsar.namespace', 'finance');
        $kebab     = strtolower((string) preg_replace('/(?<!^)[A-Z]/', '-$0', $eventType));

        return "persistent://{$tenant}/{$namespace}/{$kebab}";
    }
}
