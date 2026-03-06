<?php

namespace Tests\Feature\Filament;

use App\Enums\UserRole;
use App\Models\User;
use Tests\TestCase;

class RolePermissionResourceAccessTest extends TestCase
{
    public function test_non_super_admin_gets_403_on_roles_and_permissions_resources(): void
    {
        $user = new User();
        $user->name = 'Admin User';
        $user->email = 'admin@example.com';
        $user->role = UserRole::ADMIN;

        $this->actingAs($user, 'web');

        $this->get('/admin/roles')->assertForbidden();
        $this->get('/admin/permissions')->assertForbidden();
    }
}
