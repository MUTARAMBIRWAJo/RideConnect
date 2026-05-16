<?php

namespace Tests\Feature\Api;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MlProxyEndpointsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.ml_service.url' => 'https://ml.test',
            'services.ml_service.api_key' => null,
            'services.ml_service.timeout' => 10,
        ]);
    }

    public function test_predict_fare_rejects_invalid_feature_count(): void
    {
        Sanctum::actingAs($this->makeUser(), ['*']);

        $response = $this->postJson('/api/v1/ml/predict-fare', [
            'features' => [1.0, 2.0, 3.0],
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['features']);
    }

    public function test_rank_drivers_proxies_successful_response(): void
    {
        Http::fake([
            'https://ml.test/ml/rank-drivers' => Http::response([
                'driver_ranks' => [0.91],
            ], 200),
        ]);

        Sanctum::actingAs($this->makeUser(), ['*']);

        $features = array_fill(0, 21, 0.5);

        $response = $this->postJson('/api/v1/ml/rank-drivers', [
            'features' => $features,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.driver_ranks.0', 0.91);

        Http::assertSent(function ($request) use ($features) {
            return $request->url() === 'https://ml.test/ml/rank-drivers'
                && $request['features'] === $features;
        });
    }

    public function test_predict_demand_uses_live_contract(): void
    {
        Http::fake([
            'https://ml.test/ml/predict-demand' => Http::response([
                'demand_level' => 0.85,
                'expected_wait_time_minutes' => 6,
                'confidence' => 0.72,
            ], 200),
        ]);

        Sanctum::actingAs($this->makeUser(), ['*']);

        $payload = [
            'latitude' => -1.9579,
            'longitude' => 30.1127,
            'hour' => 17,
            'day_of_week' => 2,
        ];

        $response = $this->postJson('/api/v1/ml/predict-demand', $payload);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.demand_level', 0.85);

        Http::assertSent(function ($request) use ($payload) {
            return $request->url() === 'https://ml.test/ml/predict-demand'
                && $request->data() === $payload;
        });
    }

    public function test_predict_demand_rejects_old_features_payload(): void
    {
        Sanctum::actingAs($this->makeUser(), ['*']);

        $response = $this->postJson('/api/v1/ml/predict-demand', [
            'features' => array_fill(0, 8, 1.1),
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['latitude', 'longitude', 'hour', 'day_of_week']);
    }

    public function test_health_proxies_successful_response(): void
    {
        Http::fake([
            'https://ml.test/ml/health' => Http::response([
                'status' => 'ok',
                'models_loaded' => [
                    'fare_estimator' => true,
                    'driver_ranker' => true,
                    'demand_lstm' => true,
                ],
            ], 200),
        ]);

        Sanctum::actingAs($this->makeUser(), ['*']);

        $response = $this->getJson('/api/v1/ml/health');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'ok');
    }

    public function test_reload_models_proxies_upstream_server_error(): void
    {
        Http::fake([
            'https://ml.test/ml/reload-models' => Http::response([
                'detail' => 'reload failed',
            ], 500),
        ]);

        Sanctum::actingAs($this->makeUser(), ['*']);

        $response = $this->postJson('/api/v1/ml/reload-models');

        $response->assertStatus(500)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error', 'reload failed');
    }

    private function makeUser(): User
    {
        return User::factory()->create([
            'role' => UserRole::PASSENGER->value,
            'is_approved' => true,
        ]);
    }
}
