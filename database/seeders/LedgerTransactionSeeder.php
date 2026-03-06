<?php

namespace Database\Seeders;

use App\Models\Driver;
use App\Models\DriverPayout;
use App\Models\LedgerAccount;
use App\Models\LedgerEntry;
use App\Models\LedgerTransaction;
use App\Models\Payment;
use App\Services\LedgerService;
use Carbon\Carbon;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class LedgerTransactionSeeder extends Seeder
{
    use WithoutModelEvents;

    private LedgerService $ledger;

    public function run(): void
    {
        $this->ledger = app(LedgerService::class);

        // -----------------------------------------------------------------------
        // Seed payment-received transactions for each COMPLETED payment
        // -----------------------------------------------------------------------
        $completedPayments = Payment::query()
            ->whereRaw('LOWER(status) = ?', ['completed'])
            ->with('booking.ride')
            ->get();

        $paymentCount = 0;

        foreach ($completedPayments as $payment) {
            try {
                DB::transaction(function () use ($payment) {
                    $provider = $payment->payment_provider
                        ?? ($payment->payment_method === 'card' ? 'stripe' : 'mtn_momo');

                    $this->ledger->recordPaymentReceived($payment, $provider);
                });
                $paymentCount++;
            } catch (\Throwable $e) {
                // Skip if already recorded or any constraint issue
            }
        }

        // -----------------------------------------------------------------------
        // Seed settlement transactions for each PROCESSED payout
        // -----------------------------------------------------------------------
        $payouts      = DriverPayout::query()->where('status', 'processed')->get();
        $payoutCount  = 0;

        foreach ($payouts as $payout) {
            try {
                DB::transaction(function () use ($payout) {
                    $this->ledger->recordSettlement(
                        driverId:      (int) $payout->driver_id,
                        totalAmount:   (float) $payout->total_income,
                        commission:    (float) $payout->commission_amount,
                        driverPayout:  (float) $payout->payout_amount,
                        referenceType: 'payout',
                        referenceId:   $payout->id,
                        createdBy:     $payout->processed_by
                    );

                    $this->ledger->recordPayout($payout);
                });
                $payoutCount++;
            } catch (\Throwable $e) {
                // Skip duplicates
            }
        }

        // -----------------------------------------------------------------------
        // Seed a set of representative sample transactions (always visible in UI)
        // -----------------------------------------------------------------------
        $this->seedSampleTransactions();

        $this->command->info(sprintf(
            'LedgerTransactionSeeder: %d payment txns, %d payout txns, + sample data.',
            $paymentCount,
            $payoutCount
        ));
    }

    // -----------------------------------------------------------------------
    // Sample double-entry transactions to populate the ledger viewer
    // -----------------------------------------------------------------------
    private function seedSampleTransactions(): void
    {
        $escrow    = $this->ledger->getPlatformAccount('Platform Escrow');
        $revenue   = $this->ledger->getPlatformAccount('Platform Revenue');
        $stripe    = $this->ledger->getPlatformAccount('Stripe Clearing');
        $mtn       = $this->ledger->getPlatformAccount('MTN Mobile Money Clearing');
        $bank      = $this->ledger->getPlatformAccount('Platform Bank');

        $drivers  = Driver::query()->with('user')->take(3)->get();
        $dates    = [
            Carbon::now()->subDays(4)->toDateString(),
            Carbon::now()->subDays(3)->toDateString(),
            Carbon::now()->subDays(2)->toDateString(),
            Carbon::now()->subDays(1)->toDateString(),
            Carbon::now()->toDateString(),
        ];

        // Amounts in RWF (realistic Rwandan ride-sharing amounts)
        $samplePayments = [
            ['amount' => 12000, 'provider' => 'mtn_momo',  'day' => 0],
            ['amount' => 8500,  'provider' => 'stripe',    'day' => 0],
            ['amount' => 15000, 'provider' => 'mtn_momo',  'day' => 1],
            ['amount' => 9200,  'provider' => 'stripe',    'day' => 1],
            ['amount' => 11000, 'provider' => 'mtn_momo',  'day' => 2],
            ['amount' => 7000,  'provider' => 'stripe',    'day' => 2],
            ['amount' => 13500, 'provider' => 'mtn_momo',  'day' => 3],
            ['amount' => 6500,  'provider' => 'stripe',    'day' => 3],
            ['amount' => 18000, 'provider' => 'mtn_momo',  'day' => 4],
            ['amount' => 10000, 'provider' => 'stripe',    'day' => 4],
        ];

        foreach ($samplePayments as $idx => $sample) {
            $amount   = $sample['amount'];
            $clearing = $sample['provider'] === 'stripe' ? $stripe : $mtn;

            // Determine passenger account (use driver #1's passenger or create a generic one)
            $passengerAcct = LedgerAccount::firstOrCreate(
                ['name' => 'Passenger Wallet', 'owner_type' => 'passenger', 'owner_id' => $idx + 10],
                ['type' => 'liability', 'currency' => 'RWF', 'is_active' => true]
            );

            $ref = ['reference_type' => 'payment', 'reference_id' => 9000 + $idx];

            try {
                $this->ledger->record(
                    "Sample payment via {$sample['provider']} — RWF {$amount}",
                    [
                        array_merge(['account_id' => $clearing->id,     'debit' => $amount, 'credit' => 0,      'description' => "Provider receipt"], $ref),
                        array_merge(['account_id' => $passengerAcct->id,'debit' => 0,       'credit' => $amount,'description' => "Passenger credit"], $ref),
                        array_merge(['account_id' => $passengerAcct->id,'debit' => $amount, 'credit' => 0,      'description' => "Transfer to escrow"], $ref),
                        array_merge(['account_id' => $escrow->id,       'debit' => 0,       'credit' => $amount,'description' => "Escrow hold"], $ref),
                    ]
                );
            } catch (\Throwable) {
                // skip on duplicate
            }
        }

        // Settlement samples per driver
        $settlementData = [
            ['amount' => 12000, 'driver_index' => 0, 'day' => 1],
            ['amount' => 8500,  'driver_index' => 1, 'day' => 1],
            ['amount' => 15000, 'driver_index' => 0, 'day' => 2],
            ['amount' => 9200,  'driver_index' => 2, 'day' => 2],
        ];

        foreach ($settlementData as $idx => $s) {
            if (! isset($drivers[$s['driver_index']])) {
                continue;
            }

            $driver      = $drivers[$s['driver_index']];
            $total       = $s['amount'];
            $commission  = round($total * 0.08, 2);
            $payout      = round($total - $commission, 2);
            $driverAcct  = $this->ledger->getDriverAccount($driver->id);

            $ref = ['reference_type' => 'payout', 'reference_id' => 8000 + $idx];

            try {
                $this->ledger->record(
                    "Sample settlement driver #{$driver->id} — RWF {$total}",
                    [
                        array_merge(['account_id' => $escrow->id,    'debit' => $total,  'credit' => 0,          'description' => "Release escrow"], $ref),
                        array_merge(['account_id' => $driverAcct->id,'debit' => 0,       'credit' => $payout,    'description' => "Driver earnings 92%"], $ref),
                        array_merge(['account_id' => $revenue->id,   'debit' => 0,       'credit' => $commission,'description' => "Platform commission 8%"], $ref),
                    ]
                );
            } catch (\Throwable) {
                // skip
            }
        }

        // Payout disbursement samples (driver wallet → platform bank)
        $disbursements = [
            ['driver_index' => 0, 'amount' => 11040],
            ['driver_index' => 1, 'amount' => 7820],
        ];

        foreach ($disbursements as $idx => $d) {
            if (! isset($drivers[$d['driver_index']])) {
                continue;
            }

            $driver     = $drivers[$d['driver_index']];
            $amount     = $d['amount'];
            $driverAcct = $this->ledger->getDriverAccount($driver->id);

            $ref = ['reference_type' => 'payout', 'reference_id' => 7000 + $idx];

            try {
                $this->ledger->record(
                    "Sample payout disbursement driver #{$driver->id} — RWF {$amount}",
                    [
                        array_merge(['account_id' => $driverAcct->id,'debit' => $amount,'credit' => 0,      'description' => "Payout debit"], $ref),
                        array_merge(['account_id' => $bank->id,      'debit' => 0,      'credit' => $amount,'description' => "Disbursement"], $ref),
                    ]
                );
            } catch (\Throwable) {
                // skip
            }
        }
    }
}
