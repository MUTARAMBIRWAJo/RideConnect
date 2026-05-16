<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\CreateTestUsers;
use Tests\TestCase;

class CreateTestUsersSeederTest extends TestCase
{
    public function test_seeded_manager_users_are_approved_for_filament_access(): void
    {
        $this->seed(CreateTestUsers::class);

        $superAdmin = User::where('email', 'superadmin@rideconnect.rw')->firstOrFail();

        $this->assertTrue($superAdmin->is_approved);
        $this->assertTrue($superAdmin->canAccessPanel(\Filament\Facades\Filament::getPanel('admin')));
    }
}
