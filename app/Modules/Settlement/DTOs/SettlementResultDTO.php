<?php

namespace App\Modules\Settlement\DTOs;

readonly class SettlementResultDTO
{
    public function __construct(
        public int     $driverId,
        public string  $settlementDate,
        public float   $totalIncome,
        public float   $commissionAmount,
        public float   $payoutAmount,
        public float   $taxWithheld,
        public float   $netPayout,       // payoutAmount - taxWithheld
        public bool    $isIdempotent,    // true = already settled, skipped
        public ?int    $payoutId,
        public ?string $ledgerTransactionUuid,
    ) {}

    public function toArray(): array
    {
        return [
            'driver_id'               => $this->driverId,
            'settlement_date'         => $this->settlementDate,
            'total_income'            => $this->totalIncome,
            'commission_amount'       => $this->commissionAmount,
            'payout_amount'           => $this->payoutAmount,
            'tax_withheld'            => $this->taxWithheld,
            'net_payout'              => $this->netPayout,
            'is_idempotent'           => $this->isIdempotent,
            'payout_id'               => $this->payoutId,
            'ledger_transaction_uuid' => $this->ledgerTransactionUuid,
        ];
    }
}
