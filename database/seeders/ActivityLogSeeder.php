<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ActivityLogSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $seedNow = now()->startOfMinute();

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
        $accountantId = $managerIdsByRole->get('ACCOUNTANT', collect([$fallbackManagerId]))->first();

        $logs = [
            [
                'manager_id' => $superAdminId,
                'action' => 'CREATE_MANAGER',
                'description' => 'Created new admin account for John Kamanzi (john.kamanzi@rideconnect.rw)',
                'created_at' => $seedNow->copy()->subDays(80),
            ],
            [
                'manager_id' => $superAdminId,
                'action' => 'CREATE_MANAGER',
                'description' => 'Created new officer account for Sarah Uwase (sarah.uwase@rideconnect.rw)',
                'created_at' => $seedNow->copy()->subDays(60),
            ],
            [
                'manager_id' => $superAdminId,
                'action' => 'CREATE_MANAGER',
                'description' => 'Created new accountant account for Yvonne Mutoni (yvonne.mutoni@rideconnect.rw)',
                'created_at' => $seedNow->copy()->subDays(50),
            ],
            [
                'manager_id' => $adminId ?? $fallbackManagerId,
                'action' => 'VERIFY_DRIVER',
                'description' => 'Verified driver Jean Mugabo (ID: 1). Documents approved.',
                'created_at' => $seedNow->copy()->subDays(58),
            ],
            [
                'manager_id' => $adminId ?? $fallbackManagerId,
                'action' => 'VERIFY_DRIVER',
                'description' => 'Verified driver Patrick Habimana (ID: 2). Documents approved.',
                'created_at' => $seedNow->copy()->subDays(43),
            ],
            [
                'manager_id' => $adminId ?? $fallbackManagerId,
                'action' => 'VERIFY_DRIVER',
                'description' => 'Verified driver Claude Niyonzima (ID: 3). Documents approved.',
                'created_at' => $seedNow->copy()->subDays(28),
            ],
            [
                'manager_id' => $officerIds[0] ?? $fallbackManagerId,
                'action' => 'ISSUE_TICKET',
                'description' => 'Issued ticket for trip #9 cancellation. Amount: 5,000 RWF.',
                'created_at' => $seedNow->copy()->subDays(4),
            ],
            [
                'manager_id' => $officerIds[1] ?? ($officerIds[0] ?? $fallbackManagerId),
                'action' => 'ISSUE_TICKET',
                'description' => 'Issued ticket for fare discrepancy on trip #1. Amount: 1,000 RWF.',
                'created_at' => $seedNow->copy()->subDays(19),
            ],
            [
                'manager_id' => $officerIds[1] ?? ($officerIds[0] ?? $fallbackManagerId),
                'action' => 'RESOLVE_TICKET',
                'description' => 'Resolved ticket #2 for trip #1. Fare adjusted and refund issued.',
                'created_at' => $seedNow->copy()->subDays(18),
            ],
            [
                'manager_id' => $officerIds[0] ?? $fallbackManagerId,
                'action' => 'ISSUE_TICKET',
                'description' => 'Issued speeding violation ticket for trip #2. Amount: 10,000 RWF.',
                'created_at' => $seedNow->copy()->subDays(17),
            ],
            [
                'manager_id' => $adminId ?? $fallbackManagerId,
                'action' => 'SUSPEND_VEHICLE',
                'description' => 'Suspended vehicle RAG 654 E (ID: 5) owned by driver Jean Mugabo. Reason: Failed inspection.',
                'created_at' => $seedNow->copy()->subDays(10),
            ],
            [
                'manager_id' => $accountantId ?? $fallbackManagerId,
                'action' => 'GENERATE_REPORT',
                'description' => 'Generated monthly earnings report for all drivers. Period: Last 30 days.',
                'created_at' => $seedNow->copy()->subDays(1),
            ],
            [
                'manager_id' => $accountantId ?? $fallbackManagerId,
                'action' => 'PROCESS_PAYOUT',
                'description' => 'Processed payout batch for 3 drivers. Total amount: 19,125 RWF.',
                'created_at' => $seedNow->copy()->subDays(1),
            ],
            [
                'manager_id' => $superAdminId,
                'action' => 'SYSTEM_CONFIG',
                'description' => 'Updated commission rate from 20% to 15% for all new trips.',
                'created_at' => $seedNow->copy()->subDays(30),
            ],
            [
                'manager_id' => $officerIds[0] ?? $fallbackManagerId,
                'action' => 'CLOSE_TICKET',
                'description' => 'Closed ticket #5 for trip #5. Driver warned about route compliance.',
                'created_at' => $seedNow->copy()->subDays(5),
            ],
        ];

        foreach ($logs as $log) {
            DB::table('activity_logs')->updateOrInsert(
                [
                    'manager_id' => $log['manager_id'],
                    'action' => $log['action'],
                    'description' => $log['description'],
                    'created_at' => $log['created_at'],
                ],
                $log
            );
        }
    }
}
