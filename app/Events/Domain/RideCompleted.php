<?php

namespace App\Events\Domain;

class RideCompleted extends DomainEvent
{
    public const VERSION = 1;

    public readonly string $completedAt;

    public function __construct(
        public readonly string $rideId,
        public readonly int    $driverId,
        public readonly int    $passengerId,
        public readonly float  $fareAmount,
        public readonly string $currency = 'RWF',
        ?string $completedAt = null,
    ) {
        $this->completedAt = $completedAt ?? now()->toIso8601String();

        parent::__construct();
    }

    public function aggregateId(): string   { return $this->rideId; }
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
