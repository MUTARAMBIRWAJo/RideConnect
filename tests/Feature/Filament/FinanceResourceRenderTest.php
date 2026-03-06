<?php

namespace Tests\Feature\Filament;

use App\Enums\UserRole;
use App\Models\User;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class FinanceResourceRenderTest extends TestCase
{
    public function test_finance_resource_page_renders_for_authorized_user(): void
    {
        Permission::findOrCreate('view finances', 'web');

        $user = User::factory()->create([
            'role' => UserRole::ACCOUNTANT->value,
            'is_approved' => true,
        ]);

        $user->givePermissionTo('view finances');

        $this->actingAs($user, 'web')
            ->get('/admin/finances')
            ->assertOk();
    }
}
