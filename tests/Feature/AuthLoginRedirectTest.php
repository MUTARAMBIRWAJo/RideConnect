<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Filament\Facades\Filament;
use Tests\TestCase;

class AuthLoginRedirectTest extends TestCase
{
    private function loginAsRole(UserRole $role): \Illuminate\Testing\TestResponse
    {
        $user = User::factory()->create([
            'role' => $role->value,
            'is_approved' => true,
            'password' => 'password',
        ]);

        return $this->post('/auth/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);
    }

    public function test_super_admin_redirects_to_super_dashboard(): void
    {
        $adminPanelPath = '/'.trim(Filament::getPanel('admin')->getPath(), '/');

        $response = $this->loginAsRole(UserRole::SUPER_ADMIN);

        $response->assertRedirect("{$adminPanelPath}/super-dashboard");
    }

    public function test_admin_redirects_to_admin_dashboard(): void
    {
        $adminPanelPath = '/'.trim(Filament::getPanel('admin')->getPath(), '/');

        $response = $this->loginAsRole(UserRole::ADMIN);

        $response->assertRedirect("{$adminPanelPath}/admin-dashboard");
    }

    public function test_officer_redirects_to_officer_panel_root(): void
    {
        $officerPanelPath = '/'.trim(Filament::getPanel('officer')->getPath(), '/');

        $response = $this->loginAsRole(UserRole::OFFICER);

        $response->assertRedirect($officerPanelPath);
    }

    public function test_accountant_redirects_to_accountant_panel_root(): void
    {
        $accountantPanelPath = '/'.trim(Filament::getPanel('accountant')->getPath(), '/');

        $response = $this->loginAsRole(UserRole::ACCOUNTANT);

        $response->assertRedirect($accountantPanelPath);
    }

    public function test_mobile_user_redirects_to_dashboard(): void
    {
        $response = $this->loginAsRole(UserRole::PASSENGER);

        $response->assertRedirect('/dashboard');
    }
}
