<?php

namespace App\Services;

use App\Models\DriverPayout;
use App\Models\LedgerAccount;
use App\Models\LedgerEntry;
use App\Models\LedgerTransaction;
use App\Models\Payment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

class LedgerService
{
    // -----------------------------------------------------------------------
    // Platform account canonical names
    // -----------------------------------------------------------------------
    const ESCROW_ACCOUNT  = 'Platform Escrow';
    const REVENUE_ACCOUNT = 'Platform Revenue';
    const STRIPE_CLEARING = 'Stripe Clearing';
    const MTN_CLEARING    = 'MTN Mobile Money Clearing';
    const BANK_ACCOUNT    = 'Platform Bank';

    private const PLATFORM_ACCOUNT_TYPES = [
        self::ESCROW_ACCOUNT  => 'liability',
        self::REVENUE_ACCOUNT => 'revenue',
        self::STRIPE_CLEARING => 'asset',
        self::MTN_CLEARING    => 'asset',
        self::BANK_ACCOUNT    => 'asset',
    ];

    // -----------------------------------------------------------------------
    // Account Resolvers
    // -----------------------------------------------------------------------

    public function getPlatformAccount(string $name): LedgerAccount
    {
        if (! array_key_exists($name, self::PLATFORM_ACCOUNT_TYPES)) {
            throw new RuntimeException("Unknown platform account: {$name}");
        }

        return LedgerAccount::firstOrCreate(
            ['name' => $name, 'owner_type' => 'platform', 'owner_id' => null],
            [
                'type'      => self::PLATFORM_ACCOUNT_TYPES[$name],
                'currency'  => 'RWF',
                'is_active' => true,
            ]
        );
    }

    public function getDriverAccount(int $driverId): LedgerAccount
    {
        return LedgerAccount::firstOrCreate(
            ['name' => 'Driver Wallet', 'owner_type' => 'driver', 'owner_id' => $driverId],
            ['type' => 'liability', 'currency' => 'RWF', 'is_active' => true]
        );
    }

    public function getPassengerAccount(int $userId): LedgerAccount
    {
        return LedgerAccount::firstOrCreate(
            ['name' => 'Passenger Wallet', 'owner_type' => 'passenger', 'owner_id' => $userId],
            ['type' => 'liability', 'currency' => 'RWF', 'is_active' => true]
        );
    }

    // -----------------------------------------------------------------------
    // Core recording engine
    // -----------------------------------------------------------------------

    /**
     * Record a double-entry ledger transaction.
     *
     * @param  array<int, array{account_id: int, debit: float, credit: float, reference_type?: string, reference_id?: int, description?: string}>  $entries
     */
    public function record(string $description, array $entries, ?int $createdBy = null): LedgerTransaction
    {
        if (count($entries) < 2) {
            throw new RuntimeException('A ledger transaction requires at least 2 entries.');
        }

        $totalDebit  = array_sum(array_column($entries, 'debit'));
        $totalCredit = array_sum(array_column($entries, 'credit'));

        if (abs($totalDebit - $totalCredit) > 0.001) {
            throw new RuntimeException(sprintf(
                'Ledger imbalance: debit=%.2f credit=%.2f (diff=%.4f). Transaction aborted.',
                $totalDebit,
                $totalCredit,
                $totalDebit - $totalCredit
            ));
        }

        return DB::transaction(function () use ($description, $entries, $createdBy, $totalDebit) {
            $transaction = LedgerTransaction::create([
                'description' => $description,
                'created_by'  => $createdBy,
            ]);

            foreach ($entries as $entry) {
                LedgerEntry::create([
                    'account_id'     => $entry['account_id'],
                    'transaction_id' => $transaction->id,
                    'debit'          => $entry['debit'] ?? 0,
                    'credit'         => $entry['credit'] ?? 0,
                    'reference_type' => $entry['reference_type'] ?? null,
                    'reference_id'   => $entry['reference_id'] ?? null,
                    'description'    => $entry['description'] ?? null,
                ]);
            }

            Log::info('Ledger transaction recorded', [
                'txn_id'      => $transaction->id,
                'uuid'        => $transaction->uuid,
                'description' => $description,
                'total'       => $totalDebit,
            ]);

            return $transaction;
        });
    }

    // -----------------------------------------------------------------------
    // High-level financial operations
    // -----------------------------------------------------------------------

    /**
     * Route: passenger pays via provider.
     *
     * Debit:  Clearing (Stripe/MTN) +amount
     * Credit: Passenger Wallet      +amount
     * Debit:  Passenger Wallet      +amount  (move to escrow)
     * Credit: Platform Escrow       +amount
     */
    public function recordPaymentReceived(Payment $payment, string $provider = 'stripe'): LedgerTransaction
    {
        $clearingName    = $provider === 'mtn_momo' ? self::MTN_CLEARING : self::STRIPE_CLEARING;
        $clearingAccount = $this->getPlatformAccount($clearingName);
        $passengerAcct   = $this->getPassengerAccount((int) $payment->user_id);
        $escrowAcct      = $this->getPlatformAccount(self::ESCROW_ACCOUNT);

        $amount = (float) $payment->amount;
        $ref    = ['reference_type' => 'payment', 'reference_id' => $payment->id];

        return $this->record(
            "Payment received via {$provider} for booking #{$payment->booking_id}",
            [
                array_merge(['account_id' => $clearingAccount->id, 'debit' => $amount, 'credit' => 0, 'description' => "Provider receipt ({$provider})"], $ref),
                array_merge(['account_id' => $passengerAcct->id,   'debit' => 0, 'credit' => $amount, 'description' => "Passenger credit booking #{$payment->booking_id}"], $ref),
                array_merge(['account_id' => $passengerAcct->id,   'debit' => $amount, 'credit' => 0, 'description' => "Transfer to escrow booking #{$payment->booking_id}"], $ref),
                array_merge(['account_id' => $escrowAcct->id,      'debit' => 0, 'credit' => $amount, 'description' => "Escrow hold booking #{$payment->booking_id}"], $ref),
            ],
            (int) $payment->user_id
        );
    }

    /**
     * Route: settlement — release escrow to driver (92%) and platform (8%).
     *
     * Debit:  Platform Escrow  +totalAmount
     * Credit: Driver Wallet    +driverPayout
     * Credit: Platform Revenue +commission
     */
    public function recordSettlement(
        int $driverId,
        float $totalAmount,
        float $commission,
        float $driverPayout,
        string $referenceType,
        int $referenceId,
        ?int $createdBy = null
    ): LedgerTransaction {
        $escrowAcct  = $this->getPlatformAccount(self::ESCROW_ACCOUNT);
        $driverAcct  = $this->getDriverAccount($driverId);
        $revenueAcct = $this->getPlatformAccount(self::REVENUE_ACCOUNT);

        $ref = ['reference_type' => $referenceType, 'reference_id' => $referenceId];

        return $this->record(
            "Settlement driver #{$driverId}: total={$totalAmount}, commission={$commission}, payout={$driverPayout}",
            [
                array_merge(['account_id' => $escrowAcct->id,  'debit' => $totalAmount,  'credit' => 0,            'description' => "Release escrow driver #{$driverId}"], $ref),
                array_merge(['account_id' => $driverAcct->id,  'debit' => 0,             'credit' => $driverPayout, 'description' => 'Driver payout 92%'], $ref),
                array_merge(['account_id' => $revenueAcct->id, 'debit' => 0,             'credit' => $commission,   'description' => 'Platform commission 8%'], $ref),
            ],
            $createdBy
        );
    }

    /**
     * Route: payout from driver wallet to platform bank (disbursement).
     *
     * Debit:  Driver Wallet  +amount
     * Credit: Platform Bank  +amount
     */
    public function recordPayout(DriverPayout $payout): LedgerTransaction
    {
        $driverAcct = $this->getDriverAccount((int) $payout->driver_id);
        $bankAcct   = $this->getPlatformAccount(self::BANK_ACCOUNT);
        $amount     = (float) $payout->payout_amount;

        $ref = ['reference_type' => 'payout', 'reference_id' => $payout->id];

        return $this->record(
            "Payout disbursement driver #{$payout->driver_id} for {$payout->payout_date}",
            [
                array_merge(['account_id' => $driverAcct->id, 'debit' => $amount, 'credit' => 0,      'description' => "Driver payout debit"], $ref),
                array_merge(['account_id' => $bankAcct->id,   'debit' => 0,       'credit' => $amount, 'description' => "Disbursement to driver"], $ref),
            ],
            $payout->processed_by
        );
    }

    /**
     * Route: refund — reverse escrow back through clearing account.
     *
     * Debit:  Platform Escrow  +amount
     * Credit: Passenger Wallet +amount
     * Debit:  Passenger Wallet +amount
     * Credit: Clearing Account +amount
     */
    public function recordRefund(Payment $payment, ?int $processedBy = null): LedgerTransaction
    {
        $clearingName  = ($payment->payment_provider === 'mtn_momo') ? self::MTN_CLEARING : self::STRIPE_CLEARING;
        $clearingAcct  = $this->getPlatformAccount($clearingName);
        $passengerAcct = $this->getPassengerAccount((int) $payment->user_id);
        $escrowAcct    = $this->getPlatformAccount(self::ESCROW_ACCOUNT);

        $amount = (float) $payment->amount;
        $ref    = ['reference_type' => 'refund', 'reference_id' => $payment->id];

        return $this->record(
            "Refund payment #{$payment->id} booking #{$payment->booking_id}",
            [
                array_merge(['account_id' => $escrowAcct->id,    'debit' => $amount, 'credit' => 0,      'description' => "Release from escrow for refund"], $ref),
                array_merge(['account_id' => $passengerAcct->id, 'debit' => 0,       'credit' => $amount, 'description' => "Passenger credit for refund"], $ref),
                array_merge(['account_id' => $passengerAcct->id, 'debit' => $amount, 'credit' => 0,      'description' => "Passenger debit for disbursement"], $ref),
                array_merge(['account_id' => $clearingAcct->id,  'debit' => 0,       'credit' => $amount, 'description' => "Refund disbursement via clearing"], $ref),
            ],
            $processedBy
        );
    }

    // -----------------------------------------------------------------------
    // Balance queries
    // -----------------------------------------------------------------------

    public function getAccountBalance(LedgerAccount $account): float
    {
        $debit  = (float) $account->entries()->sum('debit');
        $credit = (float) $account->entries()->sum('credit');

        return match ($account->type) {
            'asset', 'expense'     => $debit - $credit,
            'liability', 'revenue' => $credit - $debit,
            default                => $debit - $credit,
        };
    }

    public function getEscrowBalance(): float
    {
        if (! Schema::hasTable('ledger_accounts')) {
            return 0.0;
        }

        $account = LedgerAccount::where('name', self::ESCROW_ACCOUNT)
            ->where('owner_type', 'platform')
            ->first();

        return $account ? round($this->getAccountBalance($account), 2) : 0.0;
    }

    public function getPlatformRevenue(): float
    {
        if (! Schema::hasTable('ledger_accounts')) {
            return 0.0;
        }

        $account = LedgerAccount::where('name', self::REVENUE_ACCOUNT)
            ->where('owner_type', 'platform')
            ->first();

        return $account ? round($this->getAccountBalance($account), 2) : 0.0;
    }

    public function getTotalDriverWalletBalance(): float
    {
        if (! Schema::hasTable('ledger_accounts')) {
            return 0.0;
        }

        return round(
            LedgerAccount::where('owner_type', 'driver')
                ->where('name', 'Driver Wallet')
                ->get()
                ->sum(fn (LedgerAccount $a) => $this->getAccountBalance($a)),
            2
        );
    }
}
