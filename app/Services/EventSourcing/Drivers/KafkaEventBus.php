<?php

namespace App\Services\EventSourcing\Drivers;

use App\Contracts\EventBusInterface;
use Illuminate\Support\Facades\Log;

/**
 * KafkaEventBus — Kafka-compatible EventBus driver.
 *
 * Requires: ext-rdkafka or enlightn/kafka, configured via:
 *   KAFKA_BROKER=kafka:9092
 *   KAFKA_SASL_USERNAME=...
 *   KAFKA_SASL_PASSWORD=...
 *   KAFKA_SECURITY_PROTOCOL=SASL_SSL  (or PLAINTEXT for dev)
 *
 * Topic naming convention: rideconnect.finance.<event-type-kebab>
 *   e.g. rideconnect.finance.payment-captured
 *
 * Swap EventBusInterface binding in FinanceServiceProvider to use Kafka.
 */
class KafkaEventBus implements EventBusInterface
{
    private ?\RdKafka\Producer $producer = null;

    public function __construct()
    {
        if (! extension_loaded('rdkafka')) {
            Log::warning('KafkaEventBus: rdkafka extension not loaded. Events will be dropped.');
            return;
        }
        $this->producer = $this->buildProducer();
    }

    public function publish(array $event): void
    {
        if (! $this->producer) return;

        $topic  = $event['topic'] ?? $this->resolveTopic($event['event_type']);
        $handle = $this->producer->newTopic($topic);

        $handle->produce(
            \RD_KAFKA_PARTITION_UA,
            0,
            json_encode($event, JSON_THROW_ON_ERROR),
            $event['aggregate_id']     // partition key for ordering
        );

        $this->producer->poll(0);
        $this->producer->flush(1000);

        Log::info('KafkaEventBus: event published', [
            'event_id'  => $event['event_id'],
            'topic'     => $topic,
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
        // Consumer setup is done outside this service (via Artisan command / worker).
        throw new \RuntimeException('KafkaEventBus::subscribe() must be used in a dedicated consumer process.');
    }

    private function buildProducer(): \RdKafka\Producer
    {
        $conf = new \RdKafka\Conf();
        $conf->set('metadata.broker.list', (string) config('event_bus.kafka.broker', 'kafka:9092'));
        $conf->set('security.protocol',    (string) config('event_bus.kafka.security_protocol', 'PLAINTEXT'));

        $saslUser = config('event_bus.kafka.sasl_username');
        $saslPass = config('event_bus.kafka.sasl_password');

        if ($saslUser && $saslPass) {
            $conf->set('sasl.mechanisms',  'PLAIN');
            $conf->set('sasl.username',    (string) $saslUser);
            $conf->set('sasl.password',    (string) $saslPass);
        }

        return new \RdKafka\Producer($conf);
    }

    private function resolveTopic(string $eventType): string
    {
        $kebab = strtolower((string) preg_replace('/(?<!^)[A-Z]/', '-$0', $eventType));
        return "rideconnect.finance.{$kebab}";
    }
}
