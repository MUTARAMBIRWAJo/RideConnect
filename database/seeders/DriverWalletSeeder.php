<?php

namespace Database\Seeders;

use App\Models\DriverWallet;
use App\Models\Driver;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DriverWalletSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        // RWF-realistic wallet balances per driver
        $walletData = [
            // driver_id => [total_earned, total_paid, commission, available, pending, frozen]
            1 => [125000.00, 110000.00, 10000.00, 15000.00, 3500.00,  0.00],
            2 => [98000.00,  85000.00,  7840.00,  13000.00, 2000.00,  0.00],
            3 => [73500.00,  65000.00,  5880.00,  8500.00,  1500.00,  0.00],
        ];

        $drivers = Driver::pluck('id')->take(count($walletData));

        foreach ($drivers as $i => $driverId) {
            $data = array_values($walletData)[$i] ?? [0, 0, 0, 0, 0, 0];

            [$earned, $paid, $commission, $available, $pending, $frozen] = $data;

            DriverWallet::updateOrCreate(
                ['driver_id' => $driverId],
                [
                    'total_earned'               => $earned,
                    'total_paid'                 => $paid,
                    'total_commission_generated' => $commission,
                    'current_balance'            => round($earned - $paid, 2),
                    'available_balance'          => $available,
                    'pending_balance'            => $pending,
                    'frozen_balance'             => $frozen,
                ]
            );
        }

        // Remaining drivers get a zeroed wallet
        $seededIds = $drivers->all();
        Driver::whereNotIn('id', $seededIds)->each(function (Driver $driver) {
            DriverWallet::firstOrCreate(
                ['driver_id' => $driver->id],
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
        });

        $this->command->info('DriverWalletSeeder: seeded ' . DriverWallet::count() . ' wallets (RWF).');
    }
}
