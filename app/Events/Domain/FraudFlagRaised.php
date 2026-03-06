<?php

namespace App\Events\Domain;

class FraudFlagRaised extends DomainEvent
{
    public const VERSION = 1;

    public function __construct(
        public readonly int    $flagId,
        public readonly string $entityType,
        public readonly int    $entityId,
        public readonly string $reason,
        public readonly string $severity,
        public readonly array  $metadata,
    ) {
        parent::__construct();
    }

    public function aggregateId(): string   { return "{$this->entityType}:{$this->entityId}"; }
    public function aggregateType(): string { return 'fraud_flag'; }

    public function toPayload(): array
    {
        return [
            'flag_id'     => $this->flagId,
            'entity_type' => $this->entityType,
            'entity_id'   => $this->entityId,
            'reason'      => $this->reason,
            'severity'    => $this->severity,
            'metadata'    => $this->metadata,
        ];
    }
}
