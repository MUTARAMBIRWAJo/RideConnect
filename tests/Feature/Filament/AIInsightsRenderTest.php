<?php

namespace Tests\Feature\Filament;

use App\Enums\UserRole;
use App\Filament\Pages\Officer\AIInsightsPage;
use App\Models\User;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AIInsightsRenderTest extends TestCase
{
    public function test_ai_insights_shows_empty_states_when_no_ride_tables(): void
    {
        Schema::shouldReceive('hasTable')->andReturnFalse();

        $this->seed(\Database\Seeders\RoleSeeder::class);

        $user = User::factory()->create([
            'role' => UserRole::OFFICER->value,
            'is_approved' => true,
        ]);

        Role::findOrCreate('Officer', 'web');
        $user->syncRoles(['Officer']);

        $this->actingAs($user, 'web');

        Livewire::test(AIInsightsPage::class)
            ->assertSee('AI Insights')
            ->assertSee('No demand data available.')
            ->assertSee('No peak hour data.')
            ->assertSee('No trend data.')
            ->assertSee('—');
    }
}
