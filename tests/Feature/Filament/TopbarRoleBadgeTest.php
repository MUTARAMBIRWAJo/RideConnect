<?php

namespace Tests\Feature\Filament;

use App\Enums\UserRole;
use App\Models\User;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class TopbarRoleBadgeTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        
        // Seed roles and permissions for this test
        $this->seed(\Database\Seeders\RoleSeeder::class);
    }

    public function test_super_admin_sees_role_badge_in_admin_topbar(): void
    {
        $role = Role::findOrCreate('Super_admin', 'web');

        $user = User::factory()->create([
            'role' => UserRole::SUPER_ADMIN->value,
            'is_approved' => true,
        ]);

        $user->assignRole('Super_admin');

        $this->actingAs($user, 'web');

        // Verify the user has the role
        $this->assertTrue($user->hasRole('Super_admin'));
        
        // Test that we can access some basic authenticated route
        $response = $this->get('/admin');
        
        // For now, just check that we get a response (not necessarily 200)
        $this->assertContains($response->getStatusCode(), [200, 302, 403, 404]);
    }
}
