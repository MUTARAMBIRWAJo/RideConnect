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
        $seedNow = now()->startOfMinute();

        $tripIds = DB::table('trips')->orderBy('id')->pluck('id')->values();
        $managerIdsByRole = DB::table('managers')
            ->orderBy('id')
            ->get(['id', 'role'])
            ->groupBy('role')
            ->map(fn ($group) => $group->pluck('id')->values());

        $fallbackManagerId = DB::table('managers')->orderBy('id')->value('id');
        if ($fallbackManagerId === null) {
            return;
        }

        $superAdminId = $managerIdsByRole->get('SUPER_ADMIN', collect([$fallbackManagerId]))->first();
        $adminId = $managerIdsByRole->get('ADMIN', collect([$fallbackManagerId]))->first();
        $officerIds = $managerIdsByRole->get('OFFICER', collect([$fallbackManagerId]));

        $tickets = [
            [
                'trip_id' => $tripIds[0] ?? null,
                'issued_by' => $officerIds[0] ?? $fallbackManagerId,
                'reason' => 'Passenger complaint about overcharging. Fare discrepancy reported.',
                'amount' => 1000.00,
                'status' => 'RESOLVED',
                'issued_at' => $seedNow->copy()->subDays(19)->setHour(14)->setMinute(30),
                'created_at' => $seedNow->copy()->subDays(19),
            ],
            [
                'trip_id' => $tripIds[1] ?? ($tripIds[0] ?? null),
                'issued_by' => $officerIds[1] ?? ($officerIds[0] ?? $fallbackManagerId),
                'reason' => 'Speeding violation reported by passenger during ride.',
                'amount' => 10000.00,
                'status' => 'OPEN',
                'issued_at' => $seedNow->copy()->subDays(17)->setHour(9)->setMinute(0),
                'created_at' => $seedNow->copy()->subDays(17),
            ],
            [
                'trip_id' => $tripIds[2] ?? ($tripIds[0] ?? null),
                'issued_by' => $adminId ?? $fallbackManagerId,
                'reason' => 'Driver cancelled trip without valid reason. Passenger reported inconvenience.',
                'amount' => 5000.00,
                'status' => 'OPEN',
                'issued_at' => $seedNow->copy()->subDays(4)->setHour(10)->setMinute(0),
                'created_at' => $seedNow->copy()->subDays(4),
            ],
            [
                'trip_id' => null,
                'issued_by' => $superAdminId ?? $fallbackManagerId,
                'reason' => 'Vehicle inspection failure. Driver operating with expired registration.',
                'amount' => 15000.00,
                'status' => 'PENDING',
                'issued_at' => $seedNow->copy()->subDays(2)->setHour(16)->setMinute(0),
                'created_at' => $seedNow->copy()->subDays(2),
            ],
        ];

        foreach ($tickets as $ticket) {
            DB::table('tickets')->updateOrInsert(
                [
                    'issued_by' => $ticket['issued_by'],
                    'reason' => $ticket['reason'],
                    'issued_at' => $ticket['issued_at'],
                ],
                $ticket
            );
        }
    }
}
