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
        $earnings = [
            // Trip 1 - Jean Mugabo earned from Alice's trip
            [
                'driver_id' => 1, // Jean Mugabo
                'trip_id' => 1,
                'amount' => 5500.00,
                'commission' => 825.00,    // 15% commission
                'net_amount' => 4675.00,
                'created_at' => now()->subDays(20),
            ],
            // Trip 2 - Patrick Habimana earned from Grace's trip
            [
                'driver_id' => 2, // Patrick Habimana
                'trip_id' => 2,
                'amount' => 3200.00,
                'commission' => 480.00,    // 15% commission
                'net_amount' => 2720.00,
                'created_at' => now()->subDays(18),
            ],
            // Trip 3 - Claude Niyonzima earned from David's trip
            [
                'driver_id' => 3, // Claude Niyonzima
                'trip_id' => 3,
                'amount' => 2000.00,
                'commission' => 300.00,    // 15% commission
                'net_amount' => 1700.00,
                'created_at' => now()->subDays(15),
            ],
            // Trip 4 - Jean Mugabo earned from Marie's trip
            [
                'driver_id' => 1, // Jean Mugabo
                'trip_id' => 4,
                'amount' => 1800.00,
                'commission' => 270.00,    // 15% commission
                'net_amount' => 1530.00,
                'created_at' => now()->subDays(10),
            ],
            // Trip 5 - Patrick Habimana earned from Diane's trip
            [
                'driver_id' => 2, // Patrick Habimana
                'trip_id' => 5,
                'amount' => 4000.00,
                'commission' => 600.00,    // 15% commission
                'net_amount' => 3400.00,
                'created_at' => now()->subDays(7),
            ],
            // Trip 10 - Claude Niyonzima earned from Diane's trip
            [
                'driver_id' => 3, // Claude Niyonzima
                'trip_id' => 10,
                'amount' => 6000.00,
                'commission' => 900.00,    // 15% commission
                'net_amount' => 5100.00,
                'created_at' => now()->subDays(3),
            ],
        ];

        foreach ($earnings as $earning) {
            DB::table('driver_earnings')->insert($earning);
        }
    }
}
