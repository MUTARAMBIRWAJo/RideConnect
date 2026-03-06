<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Event Bus Driver
    |--------------------------------------------------------------------------
    | Supported: "database", "kafka", "pulsar"
    */
    'driver' => env('EVENT_BUS_DRIVER', 'database'),

    /*
    |--------------------------------------------------------------------------
    | Kafka Configuration
    |--------------------------------------------------------------------------
    */
    'kafka' => [
        'broker'            => env('KAFKA_BROKER', 'kafka:9092'),
        'security_protocol' => env('KAFKA_SECURITY_PROTOCOL', 'PLAINTEXT'),
        'sasl_mechanisms'   => env('KAFKA_SASL_MECHANISMS', 'PLAIN'),
        'sasl_username'     => env('KAFKA_SASL_USERNAME'),
        'sasl_password'     => env('KAFKA_SASL_PASSWORD'),
        'topic_prefix'      => env('KAFKA_TOPIC_PREFIX', 'rideconnect.finance'),
        'group_id'          => env('KAFKA_GROUP_ID', 'rideconnect-backend'),
        'compression'       => env('KAFKA_COMPRESSION', 'snappy'),
        'batch_size'        => (int) env('KAFKA_BATCH_SIZE', 100),
    ],

    /*
    |--------------------------------------------------------------------------
    | Apache Pulsar Configuration
    |--------------------------------------------------------------------------
    */
    'pulsar' => [
        'broker'    => env('PULSAR_BROKER', 'pulsar://pulsar:6650'),
        'tenant'    => env('PULSAR_TENANT', 'rideconnect'),
        'namespace' => env('PULSAR_NAMESPACE', 'finance'),
        'token'     => env('PULSAR_TOKEN'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Outbox Configuration
    |--------------------------------------------------------------------------
    */
    'outbox' => [
        'batch_size'    => (int) env('OUTBOX_BATCH_SIZE', 50),
        'max_attempts'  => (int) env('OUTBOX_MAX_ATTEMPTS', 5),
        'retry_after'   => (int) env('OUTBOX_RETRY_AFTER', 300), // seconds
    ],

];
