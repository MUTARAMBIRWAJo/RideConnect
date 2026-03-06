<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TicketSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $tickets = [
            [
                'trip_id' => 9,    // Cancelled trip
                'issued_by' => 3,  // Sarah Uwase (OFFICER)
                'reason' => 'Driver cancelled trip without valid reason. Passenger reported inconvenience.',
                'amount' => 5000.00,
                'status' => 'OPEN',
                'issued_at' => now()->subDays(4)->setHour(10)->setMinute(0),
                'created_at' => now()->subDays(4),
            ],
            [
                'trip_id' => 1,    // Completed trip
                'issued_by' => 4,  // Peter Ndayisaba (OFFICER)
                'reason' => 'Passenger complaint about overcharging. Fare discrepancy reported.',
                'amount' => 1000.00,
                'status' => 'RESOLVED',
                'issued_at' => now()->subDays(19)->setHour(14)->setMinute(30),
                'created_at' => now()->subDays(19),
            ],
            [
                'trip_id' => 2,    // Completed trip
                'issued_by' => 3,  // Sarah Uwase (OFFICER)
                'reason' => 'Speeding violation reported by passenger during ride.',
                'amount' => 10000.00,
                'status' => 'OPEN',
                'issued_at' => now()->subDays(17)->setHour(9)->setMinute(0),
                'created_at' => now()->subDays(17),
            ],
            [
                'trip_id' => null,  // Not linked to a specific trip
                'issued_by' => 4,   // Peter Ndayisaba (OFFICER)
                'reason' => 'Vehicle inspection failure. Driver operating with expired registration.',
                'amount' => 15000.00,
                'status' => 'PENDING',
                'issued_at' => now()->subDays(2)->setHour(16)->setMinute(0),
                'created_at' => now()->subDays(2),
            ],
            [
                'trip_id' => 5,    // Completed trip
                'issued_by' => 3,  // Sarah Uwase (OFFICER)
                'reason' => 'Wrong route taken by driver, resulting in higher fare.',
                'amount' => 2000.00,
                'status' => 'CLOSED',
                'issued_at' => now()->subDays(6)->setHour(11)->setMinute(15),
                'created_at' => now()->subDays(6),
            ],
        ];

        foreach ($tickets as $ticket) {
            DB::table('tickets')->insert($ticket);
        }
    }
}
