<?php

namespace Database\Seeders;

use App\Models\Driver;
use App\Models\DriverPayout;
use App\Models\PlatformCommission;
use App\Models\Ride;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DriverPayoutSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $drivers = Driver::query()->with('user')->take(3)->get();

        if ($drivers->isEmpty()) {
            $this->command->warn('DriverPayoutSeeder: no drivers found, skipping.');
            return;
        }

        // Accountant user id (first manager with ACCOUNTANT role)
        $accountantId = User::where('role', 'ACCOUNTANT')->value('id');

        $payouts = [];

        // -----------------------------------------------------------------------
        // Last 7 days of payouts for the first 3 drivers
        // -----------------------------------------------------------------------
        $earningsMap = [
            // [$totalIncome, $commissionRate]
            0 => [12000.00, 0.08],
            1 => [8500.00,  0.08],
            2 => [6000.00,  0.08],
        ];

        for ($daysAgo = 6; $daysAgo >= 1; $daysAgo--) {
            $date = Carbon::now()->subDays($daysAgo)->toDateString();

            foreach ($drivers as $idx => $driver) {
                [$income, $rate] = $earningsMap[$idx] ?? [5000.00, 0.08];
                // Vary amounts slightly by day
                $income     = round($income * (0.85 + $idx * 0.05 + ($daysAgo % 3) * 0.1), 2);
                $commission = round($income * $rate, 2);
                $payout     = round($income - $commission, 2);

                // Avoid duplicate (driver_id + payout_date unique constraint)
                if (DriverPayout::where('driver_id', $driver->id)->whereDate('payout_date', $date)->exists()) {
                    continue;
                }

                $payouts[] = DriverPayout::create([
                    'driver_id'         => $driver->id,
                    'payout_date'       => $date,
                    'total_income'      => $income,
                    'commission_amount' => $commission,
                    'payout_amount'     => $payout,
                    'processed_by'      => $accountantId,
                    'status'            => 'processed',
                    'processed_at'      => Carbon::now()->subDays($daysAgo)->setTime(1, 0, 0),
                ]);
            }
        }

        // One pending payout for today (not yet processed)
        if ($drivers->isNotEmpty()) {
            $driver = $drivers->first();
            $today  = Carbon::now()->toDateString();

            if (! DriverPayout::where('driver_id', $driver->id)->whereDate('payout_date', $today)->exists()) {
                $income     = 14500.00;
                $commission = round($income * 0.08, 2);
                $payout     = round($income - $commission, 2);

                $payouts[] = DriverPayout::create([
                    'driver_id'         => $driver->id,
                    'payout_date'       => $today,
                    'total_income'      => $income,
                    'commission_amount' => $commission,
                    'payout_amount'     => $payout,
                    'processed_by'      => null,
                    'status'            => 'pending',
                    'processed_at'      => null,
                ]);
            }
        }

        // -----------------------------------------------------------------------
        // Platform commission records tied to rides
        // -----------------------------------------------------------------------
        $rideIds = Ride::pluck('id')->take(6)->values();

        if ($rideIds->isNotEmpty()) {
            foreach ($payouts as $payoutRecord) {
                $rideSample = $rideIds->random(min(2, $rideIds->count()));
                $perRide    = round((float) $payoutRecord->commission_amount / max($rideSample->count(), 1), 2);
                $date       = Carbon::parse($payoutRecord->payout_date)->toDateString();

                foreach ($rideSample as $rideId) {
                    PlatformCommission::updateOrCreate(
                        [
                            'driver_id' => $payoutRecord->driver_id,
                            'ride_id'   => $rideId,
                            'date'      => $date,
                        ],
                        ['commission_amount' => $perRide]
                    );
                }
            }
        }

        $this->command->info(sprintf(
            'DriverPayoutSeeder: %d payouts, %d commission records (RWF).',
            count($payouts),
            PlatformCommission::count()
        ));
    }
}
