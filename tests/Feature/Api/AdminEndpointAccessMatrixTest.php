<?php

namespace Tests\Feature\Api;

use App\Enums\UserRole;
use App\Models\User;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminEndpointAccessMatrixTest extends TestCase
{
    public function test_admin_dashboard_access_matrix_per_role(): void
    {
        $this->assertEndpointAccessByRole(
            'GET',
            '/api/v1/admin/dashboard',
            [
                UserRole::SUPER_ADMIN->value => 200,
                UserRole::ADMIN->value => 200,
                UserRole::ACCOUNTANT->value => 403,
                UserRole::OFFICER->value => 403,
            ]
        );
    }

    public function test_system_logs_access_matrix_per_role(): void
    {
        $this->assertEndpointAccessByRole(
            'GET',
            '/api/v1/admin/logs',
            [
                UserRole::SUPER_ADMIN->value => 200,
                UserRole::ADMIN->value => 403,
                UserRole::ACCOUNTANT->value => 403,
                UserRole::OFFICER->value => 403,
            ]
        );
    }

    public function test_finance_export_access_matrix_per_role(): void
    {
        $this->assertEndpointAccessByRole(
            'GET',
            '/api/v1/finance/export?type=transactions&format=csv',
            [
                UserRole::SUPER_ADMIN->value => 200,
                UserRole::ADMIN->value => 403,
                UserRole::ACCOUNTANT->value => 200,
                UserRole::OFFICER->value => 403,
            ]
        );
    }

    public function test_update_role_access_matrix_per_role(): void
    {
        foreach ([
            UserRole::SUPER_ADMIN->value => 200,
            UserRole::ADMIN->value => 200,
            UserRole::ACCOUNTANT->value => 403,
            UserRole::OFFICER->value => 403,
        ] as $actorRole => $expectedStatus) {
            $actor = $this->makeUser($actorRole);
            $target = $this->makeUser(UserRole::PASSENGER->value);

            Sanctum::actingAs($actor, ['*']);

            $response = $this->putJson("/api/v1/admin/users/{$target->id}/role", [
                'role' => UserRole::DRIVER->value,
            ]);

            $response->assertStatus($expectedStatus);

            if ($expectedStatus === 200) {
                $response->assertJson(['success' => true]);
            }
        }
    }

    private function assertEndpointAccessByRole(string $method, string $uri, array $matrix): void
    {
        foreach ($matrix as $role => $expectedStatus) {
            $user = $this->makeUser($role);
            Sanctum::actingAs($user, ['*']);

            $response = match (strtoupper($method)) {
                'GET' => $this->getJson($uri),
                'POST' => $this->postJson($uri),
                'PUT' => $this->putJson($uri),
                'DELETE' => $this->deleteJson($uri),
                default => throw new \InvalidArgumentException("Unsupported method [{$method}]"),
            };

            $response->assertStatus($expectedStatus);

            if ($expectedStatus === 200) {
                $response->assertJson(['success' => true]);
            }
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
