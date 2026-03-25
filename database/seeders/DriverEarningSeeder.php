<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DriverEarningSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $tripIds = DB::table('trips')->orderBy('id')->limit(10)->pluck('id')->all();
        $driverIds = DB::table('drivers')->orderBy('id')->limit(10)->pluck('id')->all();

        if (empty($tripIds) || empty($driverIds)) {
            return;
        }

        $templates = [
            ['amount' => 5500.00, 'created_at' => now()->subDays(20)],
            ['amount' => 3200.00, 'created_at' => now()->subDays(18)],
            ['amount' => 2000.00, 'created_at' => now()->subDays(15)],
            ['amount' => 1800.00, 'created_at' => now()->subDays(10)],
            ['amount' => 4000.00, 'created_at' => now()->subDays(7)],
            ['amount' => 6000.00, 'created_at' => now()->subDays(3)],
        ];

        foreach ($templates as $index => $template) {
            $tripId = $tripIds[$index % count($tripIds)];
            $driverId = $driverIds[$index % count($driverIds)];
            $commission = round($template['amount'] * 0.15, 2);

            DB::table('driver_earnings')->updateOrInsert(
                [
                    'driver_id' => $driverId,
                    'trip_id' => $tripId,
                ],
                [
                    'amount' => $template['amount'],
                    'commission' => $commission,
                    'net_amount' => round($template['amount'] - $commission, 2),
                    'created_at' => $template['created_at'],
                ]
            );
        }
    }
}
