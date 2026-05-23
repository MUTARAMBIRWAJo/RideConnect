<?php

namespace Tests\Feature\Filament;

use App\Enums\UserRole;
use App\Filament\Pages\PlatformMetricsAndHealth;
use App\Models\User;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PlatformMetricsAndHealthRenderTest extends TestCase
{
    public function test_platform_metrics_page_renders_without_multiple_root_element_error(): void
    {
        $this->seed(\Database\Seeders\RoleSeeder::class);

        $user = User::factory()->create([
            'role' => UserRole::SUPER_ADMIN->value,
            'is_approved' => true,
        ]);

        Role::findOrCreate('Super_admin', 'web');
        $user->syncRoles(['Super_admin']);

        $this->actingAs($user, 'web');

        $this->assertTrue(PlatformMetricsAndHealth::canAccess());

        Livewire::test(PlatformMetricsAndHealth::class)
            ->assertSee('Platform Health & Metrics')
            ->assertDontSee('Livewire only supports one HTML element per component');
    }
}
