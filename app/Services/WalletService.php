<?php

namespace App\Services;

use App\Models\DriverWallet;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * All wallet mutations go through this service — never touch DriverWallet directly.
 * Every method is wrapped in a DB transaction for atomicity.
 */
class WalletService
{
    // -----------------------------------------------------------------------
    // Credit operations
    // -----------------------------------------------------------------------

    /**
     * Credit driver's pending_balance (funds just received, in escrow).
     * Called immediately after a successful payment webhook.
     */
    public function creditPending(int $driverId, float $amount): void
    {
        $this->assertPositive($amount, 'creditPending');

        DB::transaction(function () use ($driverId, $amount) {
            $wallet = $this->getOrCreate($driverId);
            $wallet->pending_balance = round((float) $wallet->pending_balance + $amount, 2);
            $wallet->current_balance = $this->computeTotal($wallet);
            $wallet->save();
        });
    }

    /**
     * Credit driver's available_balance directly (used by settlement).
     * Also updates total_earned.
     */
    public function credit(int $driverId, float $amount): void
    {
        $this->assertPositive($amount, 'credit');

        DB::transaction(function () use ($driverId, $amount) {
            $wallet = $this->getOrCreate($driverId);
            $wallet->available_balance = round((float) $wallet->available_balance + $amount, 2);
            $wallet->total_earned      = round((float) $wallet->total_earned + $amount, 2);
            $wallet->current_balance   = $this->computeTotal($wallet);
            $wallet->save();
        });
    }

    // -----------------------------------------------------------------------
    // Settlement: move pending → available
    // -----------------------------------------------------------------------

    /**
     * Release funds from pending_balance into available_balance.
     * Called by the midnight settlement job after computing verified earnings.
     */
    public function releasePending(int $driverId, float $amount): void
    {
        $this->assertPositive($amount, 'releasePending');

        DB::transaction(function () use ($driverId, $amount) {
            $wallet = $this->getOrCreate($driverId);

            if ((float) $wallet->pending_balance < $amount - 0.001) {
                throw new RuntimeException(
                    "Insufficient pending balance for driver #{$driverId}. " .
                    "Have: {$wallet->pending_balance}, need: {$amount}"
                );
            }

            $wallet->pending_balance   = round(max(0.0, (float) $wallet->pending_balance - $amount), 2);
            $wallet->available_balance = round((float) $wallet->available_balance + $amount, 2);
            $wallet->current_balance   = $this->computeTotal($wallet);
            $wallet->save();
        });
    }

    // -----------------------------------------------------------------------
    // Debit (payout disbursement)
    // -----------------------------------------------------------------------

    /**
     * Debit driver's available_balance for a payout disbursement.
     * Also updates total_paid.
     */
    public function debit(int $driverId, float $amount): void
    {
        $this->assertPositive($amount, 'debit');

        DB::transaction(function () use ($driverId, $amount) {
            $wallet = $this->getOrCreate($driverId);

            if ((float) $wallet->available_balance < $amount - 0.001) {
                throw new RuntimeException(
                    "Insufficient available balance for driver #{$driverId}. " .
                    "Have: {$wallet->available_balance}, need: {$amount}"
                );
            }

            $wallet->available_balance = round(max(0.0, (float) $wallet->available_balance - $amount), 2);
            $wallet->total_paid        = round((float) $wallet->total_paid + $amount, 2);
            $wallet->current_balance   = $this->computeTotal($wallet);
            $wallet->save();
        });
    }

    // -----------------------------------------------------------------------
    // Freeze / release (fraud / dispute)
    // -----------------------------------------------------------------------

    /**
     * Move funds from available_balance → frozen_balance.
     */
    public function freeze(int $driverId, float $amount): void
    {
        $this->assertPositive($amount, 'freeze');

        DB::transaction(function () use ($driverId, $amount) {
            $wallet = $this->getOrCreate($driverId);

            if ((float) $wallet->available_balance < $amount - 0.001) {
                throw new RuntimeException(
                    "Insufficient available balance to freeze for driver #{$driverId}. " .
                    "Have: {$wallet->available_balance}, need: {$amount}"
                );
            }

            $wallet->available_balance = round(max(0.0, (float) $wallet->available_balance - $amount), 2);
            $wallet->frozen_balance    = round((float) $wallet->frozen_balance + $amount, 2);
            $wallet->save();
        });
    }

    /**
     * Move funds from frozen_balance → available_balance (fraud cleared).
     */
    public function release(int $driverId, float $amount): void
    {
        $this->assertPositive($amount, 'release');

        DB::transaction(function () use ($driverId, $amount) {
            $wallet = $this->getOrCreate($driverId);

            if ((float) $wallet->frozen_balance < $amount - 0.001) {
                throw new RuntimeException(
                    "Insufficient frozen balance to release for driver #{$driverId}. " .
                    "Have: {$wallet->frozen_balance}, need: {$amount}"
                );
            }

            $wallet->frozen_balance    = round(max(0.0, (float) $wallet->frozen_balance - $amount), 2);
            $wallet->available_balance = round((float) $wallet->available_balance + $amount, 2);
            $wallet->save();
        });
    }

    // -----------------------------------------------------------------------
    // Transfer (driver-to-driver, rarely used)
    // -----------------------------------------------------------------------

    public function transfer(int $fromDriverId, int $toDriverId, float $amount): void
    {
        $this->assertPositive($amount, 'transfer');

        DB::transaction(function () use ($fromDriverId, $toDriverId, $amount) {
            $this->debit($fromDriverId, $amount);
            $this->credit($toDriverId, $amount);
        });
    }

    // -----------------------------------------------------------------------
    // Queries
    // -----------------------------------------------------------------------

    public function getBalance(int $driverId): array
    {
        $wallet = $this->getOrCreate($driverId);

        return [
            'available' => (float) $wallet->available_balance,
            'pending'   => (float) $wallet->pending_balance,
            'frozen'    => (float) $wallet->frozen_balance,
            'total'     => round((float) $wallet->available_balance + (float) $wallet->pending_balance, 2),
        ];
    }

    // -----------------------------------------------------------------------
    // Internal helpers
    // -----------------------------------------------------------------------

    private function getOrCreate(int $driverId): DriverWallet
    {
        return DriverWallet::firstOrCreate(
            ['driver_id' => $driverId],
            [
                'total_earned'               => 0,
                'total_paid'                 => 0,
                'total_commission_generated' => 0,
                'current_balance'            => 0,
                'available_balance'          => 0,
                'pending_balance'            => 0,
                'frozen_balance'             => 0,
            ]
        );
    }

    private function computeTotal(DriverWallet $wallet): float
    {
        return round((float) $wallet->available_balance + (float) $wallet->pending_balance, 2);
    }

    private function assertPositive(float $amount, string $method): void
    {
        if ($amount <= 0) {
            throw new RuntimeException("WalletService::{$method}() requires a positive amount. Got: {$amount}");
        }
    }
}
