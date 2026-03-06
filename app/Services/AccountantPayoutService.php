<?php

namespace App\Services;

use App\Models\Driver;
use App\Models\DriverPayout;
use App\Models\DriverWallet;
use App\Models\Notification;
use App\Models\PlatformCommission;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class AccountantPayoutService
{
    public function __construct(
        private readonly DriverEarningService $earningService,
        private readonly LedgerService $ledgerService,
        private readonly FraudDetectionService $fraudService,
    ) {
    }

    public function processSingleDriverPayout(int $driverId, string $date, ?int $accountantId = null): DriverPayout
    {
        $payoutDate = Carbon::parse($date)->toDateString();

        // Fraud + rate-limit check before entering the transaction
        if ($accountantId) {
            $eligibility = $this->fraudService->checkPayoutEligibility($driverId, $accountantId);
            if (! $eligibility['eligible']) {
                throw new RuntimeException(implode(' | ', $eligibility['blockers']));
            }
        }

        return DB::transaction(function () use ($driverId, $payoutDate, $accountantId) {
            $existing = DriverPayout::query()
                ->where('driver_id', $driverId)
                ->whereDate('payout_date', $payoutDate)
                ->first();

            if ($existing) {
                throw new RuntimeException('Payout already exists for this driver and date.');
            }

            $income = $this->earningService->calculateDriverDailyIncome($driverId, $payoutDate);

            if (($income['total_driver_income'] ?? 0.0) <= 0) {
                throw new RuntimeException('Cannot payout driver with no completed paid rides for the selected date.');
            }

            $driver = Driver::query()->with('user')->findOrFail($driverId);

            $payout = DriverPayout::create([
                'driver_id'         => $driverId,
                'payout_date'       => $payoutDate,
                'total_income'      => $income['total_driver_income'],
                'commission_amount' => $income['commission'],
                'payout_amount'     => $income['payout_amount'],
                'processed_by'      => $accountantId,
                'status'            => 'processed',
                'processed_at'      => now(),
            ]);

            $this->storeCommissionRecords($driverId, $income['ride_ids'] ?? [], $income['total_driver_income'], $payoutDate);
            $this->updateDriverWallet($driverId, $income['total_driver_income'], $income['commission'], $income['payout_amount']);
            $this->notifyDriver($driver, $payout);

            // Double-entry ledger recording
            try {
                $this->ledgerService->recordSettlement(
                    $driverId,
                    $income['total_driver_income'],
                    $income['commission'],
                    $income['payout_amount'],
                    'payout',
                    $payout->id,
                    $accountantId
                );
                $this->ledgerService->recordPayout($payout);
            } catch (\Throwable $e) {
                // Non-blocking: log and continue since the payout itself succeeded.
                Log::error('Ledger recording failed for payout', [
                    'payout_id' => $payout->id,
                    'error'     => $e->getMessage(),
                ]);
            }

            Log::info('Driver payout processed', [
                'driver_id'    => $driverId,
                'payout_date'  => $payoutDate,
                'processed_by' => $accountantId,
                'payout_id'    => $payout->id,
                'total_income' => $income['total_driver_income'],
                'commission'   => $income['commission'],
                'payout_amount'=> $income['payout_amount'],
            ]);

            return $payout;
        });
    }

    public function processBulkPayout(array $driverIds, string $date, ?int $accountantId = null): Collection
    {
        $payoutDate = Carbon::parse($date)->toDateString();

        return DB::transaction(function () use ($driverIds, $payoutDate, $accountantId) {
            $results = collect();

            foreach ($driverIds as $driverId) {
                $results->push($this->processSingleDriverPayout((int) $driverId, $payoutDate, $accountantId));
            }

            return $results;
        });
    }

    private function storeCommissionRecords(int $driverId, array $rideIds, float $totalIncome, string $date): void
    {
        if (count($rideIds) === 0 || $totalIncome <= 0) {
            return;
        }

        $totalCommission = $this->earningService->calculateCommission($totalIncome);
        $perRideCommission = round($totalCommission / max(count($rideIds), 1), 2);

        foreach ($rideIds as $rideId) {
            PlatformCommission::query()->updateOrCreate(
                [
                    'driver_id' => $driverId,
                    'ride_id' => $rideId,
                    'date' => $date,
                ],
                [
                    'commission_amount' => $perRideCommission,
                ]
            );
        }
    }

    private function updateDriverWallet(int $driverId, float $income, float $commission, float $payoutAmount): void
    {
        $wallet = DriverWallet::query()->firstOrCreate(
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

        $wallet->total_earned               = round((float) $wallet->total_earned + $income, 2);
        $wallet->total_paid                 = round((float) $wallet->total_paid + $payoutAmount, 2);
        $wallet->total_commission_generated = round((float) $wallet->total_commission_generated + $commission, 2);
        $wallet->current_balance            = round((float) $wallet->total_earned - (float) $wallet->total_paid, 2);
        // available_balance tracks net settled funds; since manual payout bypasses the pending flow,
        // we keep it non-negative by netting earned vs paid.
        $wallet->available_balance = round(max(0.0, (float) $wallet->total_earned - (float) $wallet->total_paid), 2);
        $wallet->save();
    }

    private function notifyDriver(Driver $driver, DriverPayout $payout): void
    {
        $driverUserId = $driver->user_id;

        if (! $driverUserId || ! User::query()->whereKey($driverUserId)->exists()) {
            return;
        }

        Notification::query()->create([
            'user_id' => $driverUserId,
            'type' => 'payout_processed',
            'title' => 'Earnings payout processed',
            'message' => sprintf(
                'Your payout for %s has been processed. Net amount: %s %.2f',
                Carbon::parse($payout->payout_date)->toDateString(),
                'RWF',
                (float) $payout->payout_amount,
            ),
            'data' => [
                'driver_payout_id' => $payout->id,
                'payout_date' => Carbon::parse($payout->payout_date)->toDateString(),
                'total_income' => (float) $payout->total_income,
                'commission_amount' => (float) $payout->commission_amount,
                'payout_amount' => (float) $payout->payout_amount,
            ],
            'is_read' => false,
        ]);
    }
}
