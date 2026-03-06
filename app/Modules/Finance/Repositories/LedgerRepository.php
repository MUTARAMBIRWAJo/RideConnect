<?php

namespace App\Modules\Finance\Repositories;

use App\Models\LedgerAccount;
use App\Models\LedgerEntry;
use App\Models\LedgerTransaction;
use App\Modules\Finance\Contracts\LedgerRepositoryInterface;
use Illuminate\Support\Facades\DB;

class LedgerRepository implements LedgerRepositoryInterface
{
    public function createTransaction(string $description, ?int $createdBy): LedgerTransaction
    {
        return LedgerTransaction::create([
            'description' => $description,
            'created_by'  => $createdBy,
        ]);
    }

    public function createEntry(array $data): LedgerEntry
    {
        return LedgerEntry::create($data);
    }

    public function findOrCreateAccount(array $criteria, array $defaults): LedgerAccount
    {
        return LedgerAccount::firstOrCreate($criteria, $defaults);
    }

    public function getAccountBalance(int $accountId): float
    {
        $account = LedgerAccount::find($accountId);
        if (! $account) return 0.0;

        return (float) $account->getRunningBalance();
    }

    public function getTransactionWithEntries(int $transactionId): LedgerTransaction
    {
        return LedgerTransaction::with('entries.account')->findOrFail($transactionId);
    }

    public function getPlatformEscrowBalance(): float
    {
        $escrow = LedgerAccount::where('name', 'Platform Escrow')
            ->where('owner_type', 'platform')
            ->first();

        return $escrow ? (float) $escrow->getRunningBalance() : 0.0;
    }

    public function getPlatformRevenue(?\DateTimeInterface $from, ?\DateTimeInterface $to): float
    {
        $revenue = LedgerAccount::where('name', 'Platform Revenue')
            ->where('owner_type', 'platform')
            ->first();

        if (! $revenue) return 0.0;

        $query = LedgerEntry::where('account_id', $revenue->id);

        if ($from) $query->whereDate('created_at', '>=', $from->format('Y-m-d'));
        if ($to)   $query->whereDate('created_at', '<=', $to->format('Y-m-d'));

        $credit = (float) $query->sum('credit');
        $debit  = (float) $query->sum('debit');

        return $credit - $debit;
    }
}
