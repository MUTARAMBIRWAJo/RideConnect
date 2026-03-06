<?php

namespace Tests\Feature\Filament;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class DemandForecastResourceAccessMatrixTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Gate::define('view demand forecasts', function (User $user): bool {
            $role = $this->roleValue($user);

            return in_array($role, [
                UserRole::SUPER_ADMIN->value,
                UserRole::ADMIN->value,
            ], true);
        });
    }

    public function test_demand_forecasts_access_matrix_per_role(): void
    {
        $this->assertEndpointAccessByRole('/admin/demand-forecasts', [
            UserRole::SUPER_ADMIN->value => 200,
            UserRole::ADMIN->value => 200,
            UserRole::ACCOUNTANT->value => 403,
            UserRole::OFFICER->value => 403,
        ]);
    }

    private function assertEndpointAccessByRole(string $uri, array $matrix): void
    {
        foreach ($matrix as $role => $expectedStatus) {
            $user = $this->makeUser($role);

            $response = $this->actingAs($user, 'web')->get($uri);

            $response->assertStatus($expectedStatus);
        }
    }

    private function makeUser(string $role): User
    {
        return User::factory()->create([
            'role' => $role,
            'is_approved' => true,
        ]);
    }

    private function roleValue(User $user): string
    {
        return $user->role instanceof UserRole ? $user->role->value : (string) $user->role;
    }
}
