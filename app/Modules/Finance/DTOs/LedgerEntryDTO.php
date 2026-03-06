<?php

namespace App\Modules\Finance\DTOs;

readonly class LedgerEntryDTO
{
    public function __construct(
        public int     $accountId,
        public float   $debit,
        public float   $credit,
        public ?string $referenceType = null,
        public ?int    $referenceId   = null,
        public ?string $description   = null,
    ) {}

    public function toArray(): array
    {
        return [
            'account_id'     => $this->accountId,
            'debit'          => $this->debit,
            'credit'         => $this->credit,
            'reference_type' => $this->referenceType,
            'reference_id'   => $this->referenceId,
            'description'    => $this->description,
        ];
    }
}
