<?php

namespace Database\Seeders;

use App\Models\LedgerAccount;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class LedgerAccountSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        // -----------------------------------------------------------------------
        // Platform-level accounts (owner_id = null, owner_type = platform)
        // -----------------------------------------------------------------------
        $platformAccounts = [
            [
                'name'       => 'Platform Escrow',
                'type'       => 'liability',
                'owner_type' => 'platform',
                'owner_id'   => null,
                'currency'   => 'RWF',
                'is_active'  => true,
            ],
            [
                'name'       => 'Platform Revenue',
                'type'       => 'revenue',
                'owner_type' => 'platform',
                'owner_id'   => null,
                'currency'   => 'RWF',
                'is_active'  => true,
            ],
            [
                'name'       => 'Stripe Clearing',
                'type'       => 'asset',
                'owner_type' => 'platform',
                'owner_id'   => null,
                'currency'   => 'RWF',
                'is_active'  => true,
            ],
            [
                'name'       => 'MTN Mobile Money Clearing',
                'type'       => 'asset',
                'owner_type' => 'platform',
                'owner_id'   => null,
                'currency'   => 'RWF',
                'is_active'  => true,
            ],
            [
                'name'       => 'Platform Bank',
                'type'       => 'asset',
                'owner_type' => 'platform',
                'owner_id'   => null,
                'currency'   => 'RWF',
                'is_active'  => true,
            ],
        ];

        foreach ($platformAccounts as $account) {
            LedgerAccount::firstOrCreate(
                [
                    'name'       => $account['name'],
                    'owner_type' => $account['owner_type'],
                    'owner_id'   => $account['owner_id'],
                ],
                $account
            );
        }

        // -----------------------------------------------------------------------
        // Per-driver wallet accounts (one per driver)
        // -----------------------------------------------------------------------
        $driverIds = \App\Models\Driver::pluck('id');

        foreach ($driverIds as $driverId) {
            LedgerAccount::firstOrCreate(
                [
                    'name'       => 'Driver Wallet',
                    'owner_type' => 'driver',
                    'owner_id'   => $driverId,
                ],
                [
                    'type'      => 'liability',
                    'currency'  => 'RWF',
                    'is_active' => true,
                ]
            );
        }

        // -----------------------------------------------------------------------
        // Per-passenger wallet accounts
        // -----------------------------------------------------------------------
        $passengerIds = \App\Models\User::where('role', 'PASSENGER')->pluck('id');

        foreach ($passengerIds as $userId) {
            LedgerAccount::firstOrCreate(
                [
                    'name'       => 'Passenger Wallet',
                    'owner_type' => 'passenger',
                    'owner_id'   => $userId,
                ],
                [
                    'type'      => 'liability',
                    'currency'  => 'RWF',
                    'is_active' => true,
                ]
            );
        }

        $this->command->info('LedgerAccountSeeder: seeded ' . LedgerAccount::count() . ' accounts.');
    }
}
