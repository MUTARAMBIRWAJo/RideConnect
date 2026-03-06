<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permissionsByGroup = [
            'User Management' => [
                'view users',
                'create users',
                'edit users',
                'delete users',
            ],
            'Ride Management' => [
                'view rides',
                'manage rides',
            ],
            'Finance' => [
                'view finances',
                'export finances',
            ],
            'Reports' => [
                'view performance metrics',
                'view demand forecasts',
            ],
            'Ticketing' => [
                'manage tickets',
            ],
        ];

        $allPermissions = collect($permissionsByGroup)
            ->flatten()
            ->values()
            ->all();

        foreach ($allPermissions as $permissionName) {
            Permission::findOrCreate($permissionName, 'web');
        }

        $superAdmin = Role::findOrCreate('Super_admin', 'web');
        $admin = Role::findOrCreate('Admin', 'web');
        $accountant = Role::findOrCreate('Accountant', 'web');
        $officer = Role::findOrCreate('Officer', 'web');

        $superAdmin->syncPermissions($allPermissions);

        $admin->syncPermissions([
            'view users',
            'create users',
            'edit users',
            'view rides',
            'manage rides',
            'view finances',
            'view performance metrics',
            'view demand forecasts',
            'manage tickets',
        ]);

        $accountant->syncPermissions([
            'view finances',
            'export finances',
            'view performance metrics',
        ]);

        $officer->syncPermissions([
            'view users',
            'create users',
            'edit users',
            'view rides',
            'manage rides',
            'manage tickets',
        ]);

        $roleMap = [
            UserRole::SUPER_ADMIN->value => 'Super_admin',
            UserRole::ADMIN->value => 'Admin',
            UserRole::ACCOUNTANT->value => 'Accountant',
            UserRole::OFFICER->value => 'Officer',
        ];

        $totalPermissions = count($allPermissions);
        $totalRolesSynced = count(array_unique(array_values($roleMap)));

        $assignmentCounts = collect($roleMap)
            ->values()
            ->unique()
            ->mapWithKeys(fn (string $roleName) => [$roleName => 0])
            ->all();

        $processedUsers = 0;

        User::query()
            ->whereIn('role', array_keys($roleMap))
            ->get()
            ->each(function (User $user) use ($roleMap, &$assignmentCounts, &$processedUsers): void {
                $processedUsers++;
                $enumValue = $user->role instanceof UserRole ? $user->role->value : (string) $user->role;
                $spatieRoleName = $roleMap[$enumValue] ?? null;

                if ($spatieRoleName !== null) {
                    $user->syncRoles([$spatieRoleName]);
                    $assignmentCounts[$spatieRoleName]++;
                }
            });

        if ($this->command) {
            $this->command->info('RoleSeeder assignment summary:');
            $this->command->line("- Permission totals: {$totalPermissions} permissions, {$totalRolesSynced} roles synced");
            $this->command->line("- Manager users processed: {$processedUsers}");

            foreach ($assignmentCounts as $roleName => $count) {
                $this->command->line("- {$roleName}: {$count}");
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
