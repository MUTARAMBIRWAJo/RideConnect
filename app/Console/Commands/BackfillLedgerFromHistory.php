<?php

namespace App\Console\Commands;

use App\Models\DriverPayout;
use App\Models\LedgerEntry;
use App\Models\Payment;
use App\Services\LedgerService;
use Illuminate\Console\Command;

class BackfillLedgerFromHistory extends Command
{
    protected $signature = 'ledger:backfill-history {--execute : Persist records. Without this flag, runs as dry-run} {--chunk=200 : Batch size for scanning source rows}';

    protected $description = 'Backfill ledger transactions/entries from existing completed payments and processed payouts';

    public function handle(LedgerService $ledgerService): int
    {
        $execute = (bool) $this->option('execute');
        $chunk   = max(1, (int) $this->option('chunk'));

        $this->info('Ledger backfill mode: ' . ($execute ? 'EXECUTE' : 'DRY-RUN'));

        $paymentCandidates = 0;
        $paymentBackfilled = 0;
        $paymentFailed     = 0;

        $payoutCandidates = 0;
        $payoutBackfilled = 0;
        $payoutFailed     = 0;

        Payment::query()
            ->whereIn('status', ['COMPLETED', 'completed'])
            ->orderBy('id')
            ->chunk($chunk, function ($payments) use (
                $execute,
                $ledgerService,
                &$paymentCandidates,
                &$paymentBackfilled,
                &$paymentFailed,
            ): void {
                foreach ($payments as $payment) {
                    $alreadyRecorded = LedgerEntry::query()
                        ->where('reference_type', 'payment')
                        ->where('reference_id', $payment->id)
                        ->exists();

                    if ($alreadyRecorded) {
                        continue;
                    }

                    $paymentCandidates++;

                    if (! $execute) {
                        continue;
                    }

                    try {
                        $provider = $payment->payment_provider === 'mtn_momo' ? 'mtn_momo' : 'stripe';
                        $ledgerService->recordPaymentReceived($payment, $provider);
                        $paymentBackfilled++;
                    } catch (\Throwable $e) {
                        $paymentFailed++;
                        $this->warn("Payment #{$payment->id} failed: {$e->getMessage()}");
                    }
                }
            });

        DriverPayout::query()
            ->where('status', 'processed')
            ->orderBy('id')
            ->chunk($chunk, function ($payouts) use (
                $execute,
                $ledgerService,
                &$payoutCandidates,
                &$payoutBackfilled,
                &$payoutFailed,
            ): void {
                foreach ($payouts as $payout) {
                    // Settlement + payout posting uses the same reference: (payout, payout_id)
                    // and usually results in 5 entries (3 settlement + 2 disbursement).
                    $existingEntries = LedgerEntry::query()
                        ->where('reference_type', 'payout')
                        ->where('reference_id', $payout->id)
                        ->count();

                    if ($existingEntries > 0) {
                        continue;
                    }

                    $payoutCandidates++;

                    if (! $execute) {
                        continue;
                    }

                    try {
                        $ledgerService->recordSettlement(
                            driverId: (int) $payout->driver_id,
                            totalAmount: (float) $payout->total_income,
                            commission: (float) $payout->commission_amount,
                            driverPayout: (float) $payout->payout_amount,
                            referenceType: 'payout',
                            referenceId: (int) $payout->id,
                            createdBy: $payout->processed_by,
                        );

                        $ledgerService->recordPayout($payout);
                        $payoutBackfilled++;
                    } catch (\Throwable $e) {
                        $payoutFailed++;
                        $this->warn("Payout #{$payout->id} failed: {$e->getMessage()}");
                    }
                }
            });

        $this->newLine();
        $this->line('Payments needing ledger: ' . $paymentCandidates);
        $this->line('Payouts needing ledger: ' . $payoutCandidates);

        if ($execute) {
            $this->line('Payments backfilled: ' . $paymentBackfilled);
            $this->line('Payouts backfilled: ' . $payoutBackfilled);
            $this->line('Payment failures: ' . $paymentFailed);
            $this->line('Payout failures: ' . $payoutFailed);
        } else {
            $this->comment('Dry-run only. Re-run with --execute to write ledger transactions.');
        }

        return self::SUCCESS;
    }
}
