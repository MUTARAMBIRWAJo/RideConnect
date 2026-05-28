<?php

namespace Tests\Feature\Filament;

use App\Enums\UserRole;
use App\Filament\Pages\AdvancedGoogleMaps;
use App\Models\User;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdvancedGoogleMapsRenderTest extends TestCase
{
    public function test_advanced_google_maps_shows_not_tracked_metrics_without_data_tables(): void
    {
        Schema::shouldReceive('hasTable')->andReturnFalse();

        $this->seed(\Database\Seeders\RoleSeeder::class);

        $user = User::factory()->create([
            'role' => UserRole::SUPER_ADMIN->value,
            'is_approved' => true,
        ]);

        Role::findOrCreate('Super_admin', 'web');
        $user->syncRoles(['Super_admin']);

        $this->actingAs($user, 'web');

        Livewire::test(AdvancedGoogleMaps::class)
            ->assertSee('Advanced Maps & Real-Time Tracking')
            ->assertSee('—')
            ->assertDontSee('4m 23s')
            ->assertDontSee('98.7%')
            ->assertDontSee('Downtown');

        $this->assertTrue(AdvancedGoogleMaps::canAccess());
    }
}