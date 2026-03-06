<?php

namespace App\Jobs;

use App\Models\Driver;
use App\Models\DriverPayout;
use App\Models\PlatformCommission;
use App\Services\DriverEarningService;
use App\Services\LedgerService;
use App\Services\WalletService;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Throwable;

class ProcessDailySettlementJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 3;
    public int $backoff = 300; // 5 minutes between retries

    public function __construct(public readonly string $settlementDate)
    {
    }

    public function handle(
        DriverEarningService $earningService,
        LedgerService $ledgerService,
        WalletService $walletService
    ): void {
        $date = Carbon::parse($this->settlementDate)->toDateString();

        if (! Schema::hasTable('driver_payouts')) {
            Log::warning('ProcessDailySettlementJob skipped: driver_payouts table missing. Run migrations first.');
            return;
        }

        Log::info("Daily settlement started", ['date' => $date]);

        $drivers = Driver::query()
            ->with('user')
            ->whereHas('rides', fn ($q) => $q->whereIn('status', ['COMPLETED', 'completed']))
            ->get();

        $settled = 0;
        $skipped = 0;
        $failed  = 0;

        foreach ($drivers as $driver) {
            try {
                $settled += (int) $this->settleDriver($driver, $date, $earningService, $ledgerService, $walletService);
            } catch (Throwable $e) {
                $failed++;
                Log::error("Settlement failed for driver #{$driver->id}", [
                    'date'  => $date,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        Log::info("Daily settlement complete", compact('date', 'settled', 'skipped', 'failed'));
    }

    private function settleDriver(
        Driver $driver,
        string $date,
        DriverEarningService $earningService,
        LedgerService $ledgerService,
        WalletService $walletService
    ): bool {
        // Idempotency: skip if already processed
        $alreadySettled = DriverPayout::query()
            ->where('driver_id', $driver->id)
            ->whereDate('payout_date', $date)
            ->where('status', 'processed')
            ->exists();

        if ($alreadySettled) {
            return false;
        }

        $income = $earningService->calculateDriverDailyIncome($driver->id, $date);

        if (($income['total_driver_income'] ?? 0.0) <= 0) {
            return false;
        }

        DB::transaction(function () use ($driver, $income, $date, $ledgerService, $walletService) {
            // Create payout record (automated — no processed_by)
            $payout = DriverPayout::create([
                'driver_id'         => $driver->id,
                'payout_date'       => $date,
                'total_income'      => $income['total_driver_income'],
                'commission_amount' => $income['commission'],
                'payout_amount'     => $income['payout_amount'],
                'processed_by'      => null,
                'status'            => 'processed',
                'processed_at'      => now(),
            ]);

            // Double-entry: escrow → driver wallet + platform revenue
            $ledgerService->recordSettlement(
                $driver->id,
                $income['total_driver_income'],
                $income['commission'],
                $income['payout_amount'],
                'payout',
                $payout->id
            );

            // Record disbursement entry
            $ledgerService->recordPayout($payout);

            // Wallet: release pending escrow → available
            try {
                $walletService->releasePending($driver->id, $income['payout_amount']);
            } catch (Throwable $e) {
                // If pending is lower than payout (e.g. webhook flow not yet active),
                // fall back to a direct credit so the wallet stays consistent.
                $walletService->credit($driver->id, $income['payout_amount']);
            }

            // Platform commission records (per-ride breakdown)
            $this->storeCommissionRecords($driver->id, $income, $date);
        });

        Log::info("Settled driver #{$driver->id}", [
            'date'           => $date,
            'total_income'   => $income['total_driver_income'],
            'commission'     => $income['commission'],
            'payout_amount'  => $income['payout_amount'],
        ]);

        return true;
    }

    private function storeCommissionRecords(int $driverId, array $income, string $date): void
    {
        $rideIds         = $income['ride_ids'] ?? [];
        $totalCommission = $income['commission'] ?? 0.0;

        if (empty($rideIds) || $totalCommission <= 0) {
            return;
        }

        $perRide = round($totalCommission / count($rideIds), 2);

        foreach ($rideIds as $rideId) {
            PlatformCommission::updateOrCreate(
                ['driver_id' => $driverId, 'ride_id' => $rideId, 'date' => $date],
                ['commission_amount' => $perRide]
            );
        }
    }

    public function failed(Throwable $exception): void
    {
        Log::critical("ProcessDailySettlementJob permanently failed", [
            'date'  => $this->settlementDate,
            'error' => $exception->getMessage(),
        ]);
    }
}
