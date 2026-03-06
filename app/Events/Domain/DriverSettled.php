<?php

namespace App\Events\Domain;

class DriverSettled extends DomainEvent
{
    public const VERSION = 1;

    public function __construct(
        public readonly int    $payoutId,
        public readonly int    $driverId,
        public readonly float  $totalIncome,
        public readonly float  $commissionAmount,
        public readonly float  $taxWithheld,
        public readonly float  $netPayout,
        public readonly string $settlementDate,
        public readonly string $currency,
    ) {
        parent::__construct();
    }

    public function aggregateId(): string   { return (string) $this->payoutId; }
    public function aggregateType(): string { return 'driver_payout'; }

    public function toPayload(): array
    {
        return [
            'payout_id'        => $this->payoutId,
            'driver_id'        => $this->driverId,
            'total_income'     => $this->totalIncome,
            'commission_amount' => $this->commissionAmount,
            'tax_withheld'     => $this->taxWithheld,
            'net_payout'       => $this->netPayout,
            'settlement_date'  => $this->settlementDate,
            'currency'         => $this->currency,
        ];
    }
}
