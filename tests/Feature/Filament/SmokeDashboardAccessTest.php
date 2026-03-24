<?php

namespace Tests\Feature\Filament;

use App\Enums\UserRole;
use App\Filament\Pages\AccountantDashboard;
use App\Filament\Pages\AdminDashboard;
use App\Filament\Pages\AiMonitoringDashboard;
use App\Filament\Pages\BiDashboard;
use App\Filament\Pages\ComplianceDashboard;
use App\Filament\Pages\OfficerDashboard;
use App\Filament\Pages\SuperDashboard;
use App\Models\User;
use Tests\TestCase;

class SmokeDashboardAccessTest extends TestCase
{
    public function test_dashboard_role_access_matrix(): void
    {
        $this->seed(\Database\Seeders\RoleSeeder::class);
        $matrix = [
            UserRole::SUPER_ADMIN->value => [
                SuperDashboard::class => true,
                AdminDashboard::class => false,
                AccountantDashboard::class => false,
                OfficerDashboard::class => false,
                AiMonitoringDashboard::class => true,
                BiDashboard::class => true,
                ComplianceDashboard::class => true,
            ],
            UserRole::ADMIN->value => [
                SuperDashboard::class => false,
                AdminDashboard::class => true,
                AccountantDashboard::class => false,
                OfficerDashboard::class => false,
                AiMonitoringDashboard::class => true,
                BiDashboard::class => false,
                ComplianceDashboard::class => false,
            ],
            UserRole::ACCOUNTANT->value => [
                SuperDashboard::class => false,
                AdminDashboard::class => false,
                AccountantDashboard::class => true,
                OfficerDashboard::class => false,
                AiMonitoringDashboard::class => false,
                BiDashboard::class => true,
                ComplianceDashboard::class => false,
            ],
            UserRole::OFFICER->value => [
                SuperDashboard::class => false,
                AdminDashboard::class => false,
                AccountantDashboard::class => false,
                OfficerDashboard::class => true,
                AiMonitoringDashboard::class => false,
                BiDashboard::class => false,
                ComplianceDashboard::class => true,
            ],
        ];

        foreach ($matrix as $role => $checks) {
            /** @var User $user */
            $user = User::factory()->create(['role' => $role]);
            if (method_exists($user, 'assignRole')) {
                $roleName = match ($role) {
                    UserRole::SUPER_ADMIN->value => 'Super_admin',
                    UserRole::ADMIN->value => 'Admin',
                    UserRole::ACCOUNTANT->value => 'Accountant',
                    UserRole::OFFICER->value => 'Officer',
                    default => null,
                };
                if ($roleName) {
                    $user->assignRole($roleName);
                }
            }

            $this->be($user);

            foreach ($checks as $dashboard => $expected) {
                $this->assertSame(
                    $expected,
                    $dashboard::canView(),
                    "Role {$role} should " . ($expected ? '' : 'not ') . "access {$dashboard}"
                );
            }

            auth()->logout();
        }
    }
}
