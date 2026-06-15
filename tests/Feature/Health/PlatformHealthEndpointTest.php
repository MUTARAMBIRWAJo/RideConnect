<?php

namespace Tests\Feature\Health;

use App\Services\Health\Checks\DatabaseHealthCheck;
use App\Services\Health\Checks\FirebaseHealthCheck;
use App\Services\Health\Checks\MlServiceHealthCheck;
use App\Services\Health\Checks\QueueHealthCheck;
use App\Services\Health\Checks\RedisHealthCheck;
use Illuminate\Support\Facades\Http;
use Tests\LightweightTestCase;

class PlatformHealthEndpointTest extends LightweightTestCase
{
    public function test_live_endpoint_returns_alive_without_external_checks(): void
    {
        $response = $this->getJson('/health/live');

        $response->assertOk()
            ->assertJsonPath('status', 'alive');
    }

    public function test_health_alias_points_to_live(): void
    {
        $response = $this->getJson('/health');

        $response->assertOk()
            ->assertJsonPath('status', 'alive');
    }

    public function test_ready_endpoint_returns_core_flags(): void
    {
        $this->bindHealthyCoreChecks();

        Http::fake([
            'https://ml.test/health' => Http::response(['status' => 'ok'], 200),
        ]);

        config([
            'health.ml_service.url' => 'https://ml.test',
            'firebase.enabled' => false,
        ]);

        $response = $this->getJson('/health/ready');

        $response->assertOk()
            ->assertJsonPath('status', 'ready')
            ->assertJsonPath('database', true)
            ->assertJsonPath('queue', true)
            ->assertJsonStructure([
                'status',
                'database',
                'firebase',
                'ml_service',
                'queue',
                'timestamp',
            ]);
    }

    public function test_ready_returns_503_when_database_check_fails(): void
    {
        $this->app->instance(DatabaseHealthCheck::class, new class extends DatabaseHealthCheck
        {
            public function check(bool $extended = false): array
            {
                return [
                    'ok' => false,
                    'status' => 'error',
                    'message' => 'Database unavailable',
                    'latency_ms' => 1,
                ];
            }
        });

        $this->bindHealthyQueueCheck();
        $this->bindHealthyRedisCheck();

        $response = $this->getJson('/health/ready');

        $response->assertStatus(503)
            ->assertJsonPath('status', 'not_ready')
            ->assertJsonPath('database', false);
    }

    public function test_ready_marks_ml_service_false_when_unreachable(): void
    {
        $this->bindHealthyCoreChecks();

        Http::fake([
            'https://ml.test/health' => Http::response([], 503),
        ]);

        config([
            'health.ml_service.url' => 'https://ml.test',
            'firebase.enabled' => false,
        ]);

        $response = $this->getJson('/health/ready');

        $response->assertOk()
            ->assertJsonPath('ml_service', false);
    }

    public function test_ready_marks_firebase_false_when_unavailable(): void
    {
        $this->bindHealthyCoreChecks();

        Http::fake([
            'https://ml.test/health' => Http::response(['status' => 'ok'], 200),
        ]);

        config([
            'health.ml_service.url' => 'https://ml.test',
            'firebase.enabled' => true,
        ]);

        $this->app->instance(FirebaseHealthCheck::class, new class extends FirebaseHealthCheck
        {
            public function __construct()
            {
            }

            public function check(bool $extended = false): array
            {
                return [
                    'ok' => false,
                    'status' => 'error',
                    'message' => 'Firebase unavailable',
                    'latency_ms' => 1,
                ];
            }
        });

        $response = $this->getJson('/health/ready');

        $response->assertOk()
            ->assertJsonPath('firebase', false);
    }

    public function test_full_endpoint_returns_diagnostics_payload(): void
    {
        $this->bindHealthyCoreChecks();

        Http::fake([
            'https://ml.test/health' => Http::response(['status' => 'ok'], 200),
            'https://ml.test/rank-drivers' => Http::response(['detail' => 'No candidates'], 422),
        ]);

        config([
            'health.ml_service.url' => 'https://ml.test',
            'firebase.enabled' => false,
        ]);

        $response = $this->getJson('/health/full');

        $response->assertOk()
            ->assertJsonStructure([
                'status',
                'environment',
                'checks' => [
                    'database',
                    'queue',
                    'application',
                    'storage',
                    'firebase',
                    'ml_service',
                ],
                'summary',
                'timestamp',
            ]);
    }

    public function test_full_returns_503_when_queue_check_fails(): void
    {
        $this->app->instance(DatabaseHealthCheck::class, new class extends DatabaseHealthCheck
        {
            public function check(bool $extended = false): array
            {
                return ['ok' => true, 'status' => 'ok', 'message' => 'ok', 'latency_ms' => 0];
            }
        });

        $this->app->instance(QueueHealthCheck::class, new class extends QueueHealthCheck
        {
            public function check(bool $extended = false): array
            {
                return [
                    'ok' => false,
                    'status' => 'error',
                    'message' => 'Queue unavailable',
                    'latency_ms' => 1,
                ];
            }
        });

        $this->app->instance(FirebaseHealthCheck::class, new class extends FirebaseHealthCheck
        {
            public function __construct()
            {
            }

            public function check(bool $extended = false): array
            {
                return ['ok' => true, 'status' => 'skipped', 'message' => 'skipped', 'latency_ms' => 0];
            }
        });

        $this->app->instance(MlServiceHealthCheck::class, new class extends MlServiceHealthCheck
        {
            public function check(bool $extended = false): array
            {
                return ['ok' => true, 'status' => 'ok', 'message' => 'ok', 'latency_ms' => 0];
            }
        });

        $this->bindHealthyRedisCheck();

        $response = $this->getJson('/health/full');

        $response->assertStatus(503)
            ->assertJsonPath('summary.status', 'unhealthy');
    }

    private function bindHealthyCoreChecks(): void
    {
        $this->bindHealthyDatabaseCheck();
        $this->bindHealthyRedisCheck();
        $this->bindHealthyQueueCheck();
    }

    private function bindHealthyDatabaseCheck(): void
    {
        $this->app->instance(DatabaseHealthCheck::class, new class extends DatabaseHealthCheck
        {
            public function check(bool $extended = false): array
            {
                return [
                    'ok' => true,
                    'status' => 'ok',
                    'message' => 'Database ok',
                    'latency_ms' => 1,
                    'details' => ['driver' => 'pgsql'],
                ];
            }
        });
    }

    private function bindHealthyRedisCheck(): void
    {
        $this->app->instance(RedisHealthCheck::class, new class extends RedisHealthCheck
        {
            public function check(bool $extended = false): array
            {
                return [
                    'ok' => true,
                    'status' => 'ok',
                    'message' => 'Redis ok',
                    'latency_ms' => 1,
                ];
            }
        });
    }

    private function bindHealthyQueueCheck(): void
    {
        $this->app->instance(QueueHealthCheck::class, new class extends QueueHealthCheck
        {
            public function check(bool $extended = false): array
            {
                return [
                    'ok' => true,
                    'status' => 'ok',
                    'message' => 'Queue ok',
                    'latency_ms' => 1,
                    'details' => ['driver' => 'database', 'pending_jobs' => 0],
                ];
            }
        });
    }
}
