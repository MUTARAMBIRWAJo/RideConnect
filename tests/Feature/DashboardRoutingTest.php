<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Tests\TestCase;

class DashboardRoutingTest extends TestCase
{
    /**
     * Test that Super Admin is routed to Super Dashboard
     */
    public function test_super_admin_redirected_to_super_dashboard(): void
    {
        $user = User::factory()->create([
            'role' => UserRole::SUPER_ADMIN->value,
        ]);

        $this->actingAs($user)
            ->get('/admin')
            ->assertRedirectToRoute('filament.admin.pages.super-dashboard');
    }

    /**
     * Test that Admin is routed to Admin Dashboard
     */
    public function test_admin_redirected_to_admin_dashboard(): void
    {
        $user = User::factory()->create([
            'role' => UserRole::ADMIN->value,
        ]);

        $this->actingAs($user)
            ->get('/admin')
            ->assertRedirectToRoute('filament.admin.pages.admin-dashboard');
    }

    /**
     * Test that Accountant is routed to Accountant Dashboard
     */
    public function test_accountant_redirected_to_accountant_dashboard(): void
    {
        $user = User::factory()->create([
            'role' => UserRole::ACCOUNTANT->value,
        ]);

        $this->actingAs($user)
            ->get('/admin')
            ->assertRedirectToRoute('filament.admin.pages.accountant-dashboard');
    }

    /**
     * Test that Officer is routed to Officer Dashboard V2
     */
    public function test_officer_redirected_to_officer_dashboard(): void
    {
        $user = User::factory()->create([
            'role' => UserRole::OFFICER->value,
        ]);

        $this->actingAs($user)
            ->get('/admin')
            ->assertRedirectToRoute('filament.admin.pages.officer-dashboard-v2');
    }

    /**
     * Test that Driver is routed to Driver Dashboard
     */
    public function test_driver_redirected_to_driver_dashboard(): void
    {
        $user = User::factory()->create([
            'role' => UserRole::DRIVER->value,
        ]);

        $this->actingAs($user)
            ->get('/admin')
            ->assertRedirectToRoute('filament.admin.pages.driver-dashboard');
    }

    /**
     * Test that Passenger is routed to Passenger Dashboard
     */
    public function test_passenger_redirected_to_passenger_dashboard(): void
    {
        $user = User::factory()->create([
            'role' => UserRole::PASSENGER->value,
        ]);

        $this->actingAs($user)
            ->get('/admin')
            ->assertRedirectToRoute('filament.admin.pages.passenger-dashboard');
    }

    /**
     * Test that unauthenticated user cannot access dashboard
     */
    public function test_unauthenticated_cannot_access_dashboard(): void
    {
        $this->get('/admin')
            ->assertRedirect('/admin/login');
    }

    /**
     * Test that Super Admin can access Super Dashboard
     */
    public function test_super_admin_can_access_super_dashboard(): void
    {
        $user = User::factory()->create([
            'role' => UserRole::SUPER_ADMIN->value,
        ]);

        $this->actingAs($user)
            ->get('/admin/super-dashboard')
            ->assertSuccessful();
    }

    /**
     * Test that Admin cannot access Super Dashboard
     */
    public function test_admin_cannot_access_super_dashboard(): void
    {
        $user = User::factory()->create([
            'role' => UserRole::ADMIN->value,
        ]);

        $this->actingAs($user)
            ->get('/admin/super-dashboard')
            ->assertStatus(403);
    }

    /**
     * Test that Driver can access Driver Dashboard
     */
    public function test_driver_can_access_driver_dashboard(): void
    {
        $user = User::factory()->create([
            'role' => UserRole::DRIVER->value,
        ]);

        $this->actingAs($user)
            ->get('/admin/driver-dashboard')
            ->assertSuccessful();
    }

    /**
     * Test that Passenger can access Passenger Dashboard
     */
    public function test_passenger_can_access_passenger_dashboard(): void
    {
        $user = User::factory()->create([
            'role' => UserRole::PASSENGER->value,
        ]);

        $this->actingAs($user)
            ->get('/admin/passenger-dashboard')
            ->assertSuccessful();
    }
}
