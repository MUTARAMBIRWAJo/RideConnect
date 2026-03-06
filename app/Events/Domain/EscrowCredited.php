<?php

namespace App\Events\Domain;

class EscrowCredited extends DomainEvent
{
    public const VERSION = 1;

    public function __construct(
        public readonly int    $paymentId,
        public readonly int    $driverId,
        public readonly float  $amount,
        public readonly string $currency,
        public readonly string $creditedAt,
    ) {
        parent::__construct();
    }

    public function aggregateId(): string   { return (string) $this->paymentId; }
    public function aggregateType(): string { return 'payment'; }

    public function toPayload(): array
    {
        return [
            'payment_id'  => $this->paymentId,
            'driver_id'   => $this->driverId,
            'amount'      => $this->amount,
            'currency'    => $this->currency,
            'credited_at' => $this->creditedAt,
        ];
    }
}
