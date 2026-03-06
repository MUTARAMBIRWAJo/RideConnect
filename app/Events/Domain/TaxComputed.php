<?php

namespace App\Events\Domain;

class TaxComputed extends DomainEvent
{
    public const VERSION = 1;

    public function __construct(
        public readonly int    $referenceId,
        public readonly string $referenceType,
        public readonly string $appliesTo,
        public readonly float  $grossAmount,
        public readonly float  $taxAmount,
        public readonly float  $netAmount,
        public readonly string $jurisdiction,
        public readonly array  $lineItems,
        public readonly string $currency,
    ) {
        parent::__construct();
    }

    public function aggregateId(): string   { return "{$this->referenceType}:{$this->referenceId}"; }
    public function aggregateType(): string { return 'tax'; }

    public function toPayload(): array
    {
        return [
            'reference_id'   => $this->referenceId,
            'reference_type' => $this->referenceType,
            'applies_to'     => $this->appliesTo,
            'gross_amount'   => $this->grossAmount,
            'tax_amount'     => $this->taxAmount,
            'net_amount'     => $this->netAmount,
            'jurisdiction'   => $this->jurisdiction,
            'line_items'     => $this->lineItems,
            'currency'       => $this->currency,
        ];
    }
}
