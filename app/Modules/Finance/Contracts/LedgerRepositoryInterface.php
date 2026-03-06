<?php

namespace App\Modules\Finance\Contracts;

use App\Models\LedgerAccount;
use App\Models\LedgerEntry;
use App\Models\LedgerTransaction;

interface LedgerRepositoryInterface
{
    public function createTransaction(string $description, ?int $createdBy): LedgerTransaction;

    public function createEntry(array $data): LedgerEntry;

    public function findOrCreateAccount(array $criteria, array $defaults): LedgerAccount;

    public function getAccountBalance(int $accountId): float;

    public function getTransactionWithEntries(int $transactionId): LedgerTransaction;

    public function getPlatformEscrowBalance(): float;

    public function getPlatformRevenue(?\DateTimeInterface $from, ?\DateTimeInterface $to): float;
}
