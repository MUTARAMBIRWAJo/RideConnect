<?php

namespace App\Modules\Tax\DTOs;

readonly class TaxBreakdownDTO
{
    /** @param array<int, array{rule_name: string, percentage: float, amount: float, jurisdiction: string}> $lineItems */
    public function __construct(
        public float  $grossAmount,
        public string $appliesTo,      // 'ride' | 'commission' | 'payout'
        public string $jurisdiction,
        public float  $totalTax,
        public float  $netAmount,      // grossAmount - totalTax
        public array  $lineItems,      // one entry per applied rule
    ) {}

    public function toArray(): array
    {
        return [
            'gross_amount'  => $this->grossAmount,
            'applies_to'    => $this->appliesTo,
            'jurisdiction'  => $this->jurisdiction,
            'total_tax'     => $this->totalTax,
            'net_amount'    => $this->netAmount,
            'line_items'    => $this->lineItems,
        ];
    }

    public function toJson(): string
    {
        return json_encode($this->toArray(), JSON_THROW_ON_ERROR);
    }
}
