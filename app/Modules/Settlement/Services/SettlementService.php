<?php

namespace App\Modules\Settlement\Services;

use App\Events\Domain\CommissionCalculated;
use App\Events\Domain\DriverSettled;
use App\Modules\Settlement\Contracts\SettlementRepositoryInterface;
use App\Modules\Settlement\DTOs\SettlementResultDTO;
use App\Modules\Tax\Services\TaxService;
use App\Services\EventSourcing\EventDispatcherService;
use App\Services\LedgerService;
use App\Services\WalletService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SettlementService
{
    private const COMMISSION_RATE = 0.08;

    public function __construct(
        private readonly SettlementRepositoryInterface $settlementRepo,
        private readonly LedgerService                 $ledgerService,
        private readonly WalletService                 $walletService,
        private readonly TaxService                    $taxService,
        private readonly EventDispatcherService        $eventDispatcher,
    ) {}

    public function settleDriver(int $driverId, float $totalIncome, string $date, ?int $createdBy = null): SettlementResultDTO
    {
        // Idempotency guard
        if ($existing = $this->settlementRepo->findByDriverAndDate($driverId, $date)) {
            return new SettlementResultDTO(
                driverId:               $driverId,
                settlementDate:         $date,
                totalIncome:            (float) $existing->total_income,
                commissionAmount:       (float) $existing->commission_amount,
                payoutAmount:           (float) $existing->payout_amount,
                taxWithheld:            0.0,
                netPayout:              (float) $existing->payout_amount,
                isIdempotent:           true,
                payoutId:               $existing->id,
                ledgerTransactionUuid:  null,
            );
        }

        return DB::transaction(function () use ($driverId, $totalIncome, $date, $createdBy) {
            $commission  = round($totalIncome * self::COMMISSION_RATE, 2);
            $grossPayout = round($totalIncome - $commission, 2);

            // Compute tax on payout
            $taxBreakdown = $this->taxService->calculatePayoutTax($grossPayout, 'RW');
            $taxWithheld  = $taxBreakdown->totalTax;
            $netPayout    = $taxBreakdown->netAmount;

            $payout = $this->settlementRepo->createPayout([
                'driver_id'         => $driverId,
                'payout_date'       => $date,
                'total_income'      => $totalIncome,
                'commission_amount' => $commission,
                'payout_amount'     => $grossPayout,
                'processed_by'      => $createdBy,
                'status'            => 'processed',
                'processed_at'      => now(),
            ]);

            // Ledger: escrow → driver wallet + platform revenue
            $ledgerTxn = $this->ledgerService->recordSettlement(
                driverId:      $driverId,
                totalAmount:   $totalIncome,
                commission:    $commission,
                driverPayout:  $grossPayout,
                referenceType: 'payout',
                referenceId:   $payout->id,
                createdBy:     $createdBy,
            );

            // Move wallet: pending → available
            try {
                $this->walletService->releasePending($driverId, $grossPayout);
            } catch (\RuntimeException) {
                // Pending balance may be stale — credit directly
                $this->walletService->credit($driverId, $grossPayout);
            }

            // Domain events
            $this->eventDispatcher->dispatch(new CommissionCalculated(
                driverId:         $driverId,
                referenceId:      $payout->id,
                referenceType:    'payout',
                grossAmount:      $totalIncome,
                commissionAmount: $commission,
                commissionRate:   self::COMMISSION_RATE,
                currency:         'RWF',
                calculatedAt:     now()->toIso8601String(),
            ));

            $this->eventDispatcher->dispatch(new DriverSettled(
                payoutId:         $payout->id,
                driverId:         $driverId,
                totalIncome:      $totalIncome,
                commissionAmount: $commission,
                taxWithheld:      $taxWithheld,
                netPayout:        $netPayout,
                settlementDate:   $date,
                currency:         'RWF',
            ));

            Log::info('SettlementService: driver settled', [
                'driver_id'  => $driverId,
                'payout_id'  => $payout->id,
                'net_payout' => $netPayout,
                'date'       => $date,
            ]);

            return new SettlementResultDTO(
                driverId:              $driverId,
                settlementDate:        $date,
                totalIncome:           $totalIncome,
                commissionAmount:      $commission,
                payoutAmount:          $grossPayout,
                taxWithheld:           $taxWithheld,
                netPayout:             $netPayout,
                isIdempotent:          false,
                payoutId:              $payout->id,
                ledgerTransactionUuid: $ledgerTxn->uuid,
            );
        });
    }
}
