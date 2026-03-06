<?php

namespace Tests\Feature\Filament;

use App\Enums\UserRole;
use App\Models\User;
use Tests\TestCase;

class AnalyticsResourcesAccessMatrixTest extends TestCase
{
    public function test_performance_metrics_access_matrix_per_role(): void
    {
        $this->assertEndpointAccessByRole('/admin/performance-metrics', [
            UserRole::SUPER_ADMIN->value => 200,
            UserRole::ADMIN->value => 200,
            UserRole::ACCOUNTANT->value => 403,
            UserRole::OFFICER->value => 403,
        ]);
    }

    public function test_route_optimizations_access_matrix_per_role(): void
    {
        $this->assertEndpointAccessByRole('/admin/route-optimizations', [
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
}
