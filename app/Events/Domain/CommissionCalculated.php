<?php

namespace App\Events\Domain;

class CommissionCalculated extends DomainEvent
{
    public const VERSION = 1;

    public function __construct(
        public readonly int    $driverId,
        public readonly int    $referenceId,
        public readonly string $referenceType,
        public readonly float  $grossAmount,
        public readonly float  $commissionAmount,
        public readonly float  $commissionRate,
        public readonly string $currency,
        public readonly string $calculatedAt,
    ) {
        parent::__construct();
    }

    public function aggregateId(): string   { return "{$this->referenceType}:{$this->referenceId}"; }
    public function aggregateType(): string { return 'commission'; }

    public function toPayload(): array
    {
        return [
            'driver_id'        => $this->driverId,
            'reference_id'     => $this->referenceId,
            'reference_type'   => $this->referenceType,
            'gross_amount'     => $this->grossAmount,
            'commission_amount' => $this->commissionAmount,
            'commission_rate'  => $this->commissionRate,
            'currency'         => $this->currency,
            'calculated_at'    => $this->calculatedAt,
        ];
    }
}
