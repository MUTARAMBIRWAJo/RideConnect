<?php

namespace App\Events\Domain;

class RideCompleted extends DomainEvent
{
    public const VERSION = 1;

    public function __construct(
        public readonly int    $rideId,
        public readonly int    $driverId,
        public readonly int    $passengerId,
        public readonly float  $fareAmount,
        public readonly string $currency,
        public readonly string $completedAt,
    ) {
        parent::__construct();
    }

    public function aggregateId(): string   { return (string) $this->rideId; }
    public function aggregateType(): string { return 'ride'; }

    public function toPayload(): array
    {
        return [
            'ride_id'      => $this->rideId,
            'driver_id'    => $this->driverId,
            'passenger_id' => $this->passengerId,
            'fare_amount'  => $this->fareAmount,
            'currency'     => $this->currency,
            'completed_at' => $this->completedAt,
        ];
    }
}
