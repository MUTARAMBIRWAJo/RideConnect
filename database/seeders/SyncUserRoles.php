<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class SyncUserRoles extends Seeder
{
    public function run(): void
    {
        $roleMap = [
            'SUPER_ADMIN' => 'Super_admin',
            'ADMIN' => 'Admin',
            'ACCOUNTANT' => 'Accountant',
            'OFFICER' => 'Officer',
        ];

        $processed = 0;
        User::query()
            ->whereIn('role', array_keys($roleMap))
            ->get()
            ->each(function (User $user) use ($roleMap, &$processed): void {
                $processed++;
                $spatieRoleName = $roleMap[$user->role] ?? null;

                if ($spatieRoleName !== null) {
                    $user->syncRoles([$spatieRoleName]);
                    echo "✓ Synced {$user->email} to role: {$spatieRoleName}\n";
                }
            });

        echo "\nTotal users role-synced: {$processed}\n";
    }
}
