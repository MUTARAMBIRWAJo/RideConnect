<?php

namespace Tests\Feature\Filament;

use App\Enums\UserRole;
use App\Filament\Pages\AccountantDashboard;
use App\Filament\Pages\AdminDashboard;
use App\Filament\Pages\OfficerDashboard;
use App\Filament\Pages\SuperDashboard;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class RoleDashboardNavigationVisibilityTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Gate::define('delete users', fn (User $user): bool => $this->roleValue($user) === UserRole::SUPER_ADMIN->value);
        Gate::define('view demand forecasts', fn (User $user): bool => $this->roleValue($user) === UserRole::ADMIN->value);
        Gate::define('export finances', fn (User $user): bool => $this->roleValue($user) === UserRole::ACCOUNTANT->value);
        Gate::define('manage tickets', fn (User $user): bool => $this->roleValue($user) === UserRole::OFFICER->value);
    }

    public function test_super_admin_sees_only_super_dashboard_navigation(): void
    {
        $this->be($this->makeUser(UserRole::SUPER_ADMIN));

        $this->assertTrue(SuperDashboard::shouldRegisterNavigation());
        $this->assertFalse(AdminDashboard::shouldRegisterNavigation());
        $this->assertFalse(AccountantDashboard::shouldRegisterNavigation());
        $this->assertFalse(OfficerDashboard::shouldRegisterNavigation());
    }

    public function test_admin_sees_only_admin_dashboard_navigation(): void
    {
        $this->be($this->makeUser(UserRole::ADMIN));

        $this->assertFalse(SuperDashboard::shouldRegisterNavigation());
        $this->assertTrue(AdminDashboard::shouldRegisterNavigation());
        $this->assertFalse(AccountantDashboard::shouldRegisterNavigation());
        $this->assertFalse(OfficerDashboard::shouldRegisterNavigation());
    }

    public function test_accountant_sees_only_accountant_dashboard_navigation(): void
    {
        $this->be($this->makeUser(UserRole::ACCOUNTANT));

        $this->assertFalse(SuperDashboard::shouldRegisterNavigation());
        $this->assertFalse(AdminDashboard::shouldRegisterNavigation());
        $this->assertTrue(AccountantDashboard::shouldRegisterNavigation());
        $this->assertFalse(OfficerDashboard::shouldRegisterNavigation());
    }

    public function test_officer_sees_only_officer_dashboard_navigation(): void
    {
        $this->be($this->makeUser(UserRole::OFFICER));

        $this->assertFalse(SuperDashboard::shouldRegisterNavigation());
        $this->assertFalse(AdminDashboard::shouldRegisterNavigation());
        $this->assertFalse(AccountantDashboard::shouldRegisterNavigation());
        $this->assertTrue(OfficerDashboard::shouldRegisterNavigation());
    }

    public function test_unauthenticated_user_sees_no_dashboard_navigation(): void
    {
        auth()->logout();

        $this->assertFalse(SuperDashboard::shouldRegisterNavigation());
        $this->assertFalse(AdminDashboard::shouldRegisterNavigation());
        $this->assertFalse(AccountantDashboard::shouldRegisterNavigation());
        $this->assertFalse(OfficerDashboard::shouldRegisterNavigation());
    }

    private function makeUser(UserRole $role): User
    {
        $user = new User();
        $user->role = $role;

        return $user;
    }

    private function roleValue(User $user): string
    {
        return $user->role instanceof UserRole ? $user->role->value : (string) $user->role;
    }
}
