<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Filament\Pages\AccountantDashboard;
use App\Filament\Pages\AdminDashboard;
use App\Filament\Pages\Dashboard;
use App\Filament\Pages\DriverDashboard;
use App\Filament\Pages\OfficerDashboardV2;
use App\Filament\Pages\PassengerDashboard;
use App\Filament\Pages\SuperDashboard;
use App\Models\User;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class DashboardRoutingTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\RoleSeeder::class);
    }

    private function createApprovedUser(UserRole $role): User
    {
        $user = User::factory()->create([
            'role' => $role->value,
            'is_approved' => true,
        ]);

        $spatieRole = match ($role) {
            UserRole::SUPER_ADMIN => 'Super_admin',
            UserRole::ADMIN => 'Admin',
            UserRole::ACCOUNTANT => 'Accountant',
            UserRole::OFFICER => 'Officer',
            default => null,
        };

        if ($spatieRole) {
            Role::findOrCreate($spatieRole, 'web');
            $user->syncRoles([$spatieRole]);
        }

        return $user;
    }

    /**
     * Test Super Admin access for super dashboard page class.
     */
    public function test_super_admin_can_access_super_dashboard_logic(): void
    {
        $user = $this->createApprovedUser(UserRole::SUPER_ADMIN);

        $this->actingAs($user, 'web');

        $this->assertTrue(SuperDashboard::canAccess());
        $this->assertTrue(SuperDashboard::canView());
        $this->assertTrue(SuperDashboard::shouldRegisterNavigation());
    }

    /**
     * Test Admin access for admin dashboard page class.
     */
    public function test_admin_can_access_admin_dashboard_logic(): void
    {
        $user = $this->createApprovedUser(UserRole::ADMIN);

        $this->actingAs($user, 'web');

        $this->assertTrue(AdminDashboard::canAccess());
        $this->assertTrue(AdminDashboard::canView());
        $this->assertTrue(AdminDashboard::shouldRegisterNavigation());
        $this->assertFalse(SuperDashboard::canAccess());
    }

    /**
     * Test Accountant access for accountant dashboard page class.
     */
    public function test_accountant_can_access_accountant_dashboard_logic(): void
    {
        $user = $this->createApprovedUser(UserRole::ACCOUNTANT);

        $this->actingAs($user, 'web');

        $this->assertTrue(AccountantDashboard::canAccess());
        $this->assertTrue(AccountantDashboard::canView());
        $this->assertTrue(AccountantDashboard::shouldRegisterNavigation());
    }

    /**
     * Test Officer access for officer dashboard page class.
     */
    public function test_officer_can_access_officer_dashboard_logic(): void
    {
        $user = $this->createApprovedUser(UserRole::OFFICER);

        $this->actingAs($user, 'web');

        $this->assertTrue(OfficerDashboardV2::canAccess());
        $this->assertTrue(OfficerDashboardV2::canView());
        $this->assertTrue(OfficerDashboardV2::shouldRegisterNavigation());
    }

    /**
     * Test Driver access for driver dashboard page class.
     */
    public function test_driver_can_access_driver_dashboard_logic(): void
    {
        $user = $this->createApprovedUser(UserRole::DRIVER);

        $this->actingAs($user, 'web');

        $this->assertTrue(DriverDashboard::canAccess());
        $this->assertTrue(DriverDashboard::shouldRegisterNavigation());
        $this->assertFalse(SuperDashboard::canAccess());
    }

    /**
     * Test Passenger access for passenger dashboard page class.
     */
    public function test_passenger_can_access_passenger_dashboard_logic(): void
    {
        $user = $this->createApprovedUser(UserRole::PASSENGER);

        $this->actingAs($user, 'web');

        $this->assertTrue(PassengerDashboard::canAccess());
        $this->assertTrue(PassengerDashboard::shouldRegisterNavigation());
        $this->assertFalse(SuperDashboard::canAccess());
    }

    /**
     * Test unauthenticated user cannot access page logic.
     */
    public function test_unauthenticated_cannot_access_dashboards_logic(): void
    {
        auth()->logout();

        $this->assertFalse(SuperDashboard::canAccess());
        $this->assertFalse(AdminDashboard::canAccess());
        $this->assertFalse(AccountantDashboard::canAccess());
        $this->assertFalse(OfficerDashboardV2::canAccess());
        $this->assertFalse(DriverDashboard::canAccess());
        $this->assertFalse(PassengerDashboard::canAccess());
    }

    /**
     * Test root dashboard class resolves role without crashing.
     */
    public function test_dashboard_page_class_exists_for_role_redirect_logic(): void
    {
        $user = $this->createApprovedUser(UserRole::SUPER_ADMIN);

        $this->actingAs($user, 'web');

        $this->assertTrue(class_exists(Dashboard::class));
    }

    /**
     * Test Admin cannot access Super Dashboard logic.
     */
    public function test_admin_cannot_access_super_dashboard_logic(): void
    {
        $user = $this->createApprovedUser(UserRole::ADMIN);

        $this->actingAs($user, 'web');

        $this->assertFalse(SuperDashboard::canAccess());
    }

    /**
     * Test Driver dashboard widget and column config resolves.
     */
    public function test_driver_dashboard_widgets_and_columns_resolve(): void
    {
        $user = $this->createApprovedUser(UserRole::DRIVER);

        $this->actingAs($user, 'web');

        $page = app(DriverDashboard::class);
        $this->assertIsArray($page->getWidgets());
        $this->assertNotEmpty($page->getWidgets());
        $this->assertIsArray($page->getColumns());
    }

    /**
     * Test Passenger dashboard widget and column config resolves.
     */
    public function test_passenger_dashboard_widgets_and_columns_resolve(): void
    {
        $user = $this->createApprovedUser(UserRole::PASSENGER);

        $this->actingAs($user, 'web');

        $page = app(PassengerDashboard::class);
        $this->assertIsArray($page->getWidgets());
        $this->assertNotEmpty($page->getWidgets());
        $this->assertIsArray($page->getColumns());
    }
}
