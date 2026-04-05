<?php

namespace Tests\Feature\Filament;

use App\Enums\UserRole;
use App\Filament\Pages\SuperDashboard;
use App\Models\User;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SuperDashboardRenderTest extends TestCase
{
    public function test_super_dashboard_page_renders_without_multiple_root_element_error(): void
    {
        $this->seed(\Database\Seeders\RoleSeeder::class);

        $user = User::factory()->create([
            'role' => UserRole::SUPER_ADMIN->value,
            'is_approved' => true,
        ]);

        Role::findOrCreate('Super_admin', 'web');
        $user->syncRoles(['Super_admin']);

        $this->actingAs($user, 'web');

        $this->assertTrue(SuperDashboard::canAccess());

        Livewire::test(SuperDashboard::class)
            ->assertSee('Super Admin Dashboard')
            ->assertDontSee('Livewire only supports one HTML element per component');
    }
}