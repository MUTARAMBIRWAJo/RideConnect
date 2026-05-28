<?php

namespace Tests\Feature\Filament;

use App\Enums\UserRole;
use App\Filament\Pages\PlatformMetricsAndHealth;
use App\Models\User;
use Illuminate\Support\Facades\DB;
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

        if (DB::getSchemaBuilder()->hasTable('platform_health_snapshots')) {
            DB::table('platform_health_snapshots')->insert([
                'snapshot_type' => 'platform',
                'overall_status' => 'healthy',
                'database_status' => 'ok',
                'queue_status' => 'ok',
                'cache_status' => 'ok',
                'queue_pending' => 3,
                'database_connections' => 12,
                'ai_prediction_response_time_ms' => 84,
                'successful_checks' => 4,
                'total_checks' => 4,
                'metadata' => json_encode(['test' => true], JSON_THROW_ON_ERROR),
                'created_at' => now(),
            ]);
        }

        $this->assertTrue(PlatformMetricsAndHealth::canAccess());

        Livewire::test(PlatformMetricsAndHealth::class)
            ->assertSee('Platform Health & Metrics')
            ->assertDontSee('Livewire only supports one HTML element per component');

        Livewire::test(PlatformMetricsAndHealth::class)
            ->assertSee('Snapshot Success Rate')
            ->assertSee('Cache Availability')
            ->assertSee('Health Failure Rate')
            ->assertSee('System Status: Operational');
    }
}
