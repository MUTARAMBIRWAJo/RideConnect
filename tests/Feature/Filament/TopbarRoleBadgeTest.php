<?php

namespace Tests\Feature\Filament;

use App\Enums\UserRole;
use App\Models\User;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class TopbarRoleBadgeTest extends TestCase
{
    public function test_super_admin_sees_role_badge_in_admin_topbar(): void
    {
        Role::findOrCreate('Super_admin', 'web');

        $user = User::factory()->create([
            'role' => UserRole::SUPER_ADMIN->value,
            'is_approved' => true,
        ]);

        $user->assignRole('Super_admin');

        $this->actingAs($user, 'web');

        $response = $this->get('/admin/roles');

        $response->assertOk();
        $response->assertSeeText('Role: Super_admin');
    }
}
