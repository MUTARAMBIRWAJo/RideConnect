<?php

namespace App\Events\Domain;

class PayoutProcessed extends DomainEvent
{
    public const VERSION = 1;

    public function __construct(
        public readonly int    $payoutId,
        public readonly int    $driverId,
        public readonly float  $amount,
        public readonly string $currency,
        public readonly int    $processedBy,
        public readonly string $processedAt,
    ) {
        parent::__construct();
    }

    public function aggregateId(): string   { return (string) $this->payoutId; }
    public function aggregateType(): string { return 'driver_payout'; }

    public function toPayload(): array
    {
        return [
            'payout_id'    => $this->payoutId,
            'driver_id'    => $this->driverId,
            'amount'       => $this->amount,
            'currency'     => $this->currency,
            'processed_by' => $this->processedBy,
            'processed_at' => $this->processedAt,
        ];
    }
}
