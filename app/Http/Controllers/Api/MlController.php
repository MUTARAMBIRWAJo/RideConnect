<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\MlService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class MlController extends Controller
{
    public function __construct(private readonly MlService $mlService)
    {
    }

    public function predictFare(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'features' => 'required|array|size:23',
            'features.*' => 'required|numeric',
        ]);

        return $this->respond($this->mlService->predictFare($payload['features']));
    }

    public function rankDrivers(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'features' => 'required|array|size:21',
            'features.*' => 'required|numeric',
        ]);

        return $this->respond($this->mlService->rankDrivers($payload['features']));
    }

    public function predictDemand(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'hour' => 'required|integer|min:0|max:23',
            'day_of_week' => 'required|integer|min:0|max:6',
        ]);

        return $this->respond($this->mlService->predictDemand($payload));
    }

    public function detectAnomaly(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'speed_kmh' => 'required|numeric|min:0|max:250',
            'acceleration_ms2' => 'required|numeric|min:0|max:20',
            'heading_change_degrees' => 'required|numeric|min:0|max:360',
            'route_deviation_meters' => 'required|numeric|min:0|max:20000',
            'stop_duration_seconds' => 'required|integer|min:0|max:86400',
            'trip_id' => 'nullable|integer|exists:trips,id',
        ]);

        $tripId = $payload['trip_id'] ?? null;
        unset($payload['trip_id']);

        return $this->respond($this->mlService->detectAnomaly($payload, $tripId));
    }

    public function health(): JsonResponse
    {
        return $this->respond($this->mlService->health());
    }

    public function reloadModels(): JsonResponse
    {
        return $this->respond($this->mlService->reloadModels());
    }

    public function retrain(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'models' => 'nullable|array',
            'models.*' => 'string|max:120',
        ]);

        $models = $payload['models'] ?? [];
        $runId = $this->createTrainingRun(empty($models) ? 'all' : implode(',', $models));
        $result = $this->mlService->triggerRetrain($models);
        $this->finishTrainingRun($runId, ($result['success'] ?? false) ? 'triggered' : 'failed', $result);

        return $this->respond($result);
    }

    private function respond(array $result): JsonResponse
    {
        if (!($result['success'] ?? false)) {
            return response()->json([
                'success' => false,
                'error' => $result['error'] ?? 'ML service call failed',
                'status' => $result['status'] ?? 502,
                'data' => $result['data'] ?? null,
            ], (int) ($result['status'] ?? 502));
        }

        return response()->json([
            'success' => true,
            'data' => $result['data'] ?? [],
        ]);
    }

    private function createTrainingRun(string $modelName): ?int
    {
        if (! Schema::hasTable('ml_training_runs')) {
            return null;
        }

        return (int) DB::table('ml_training_runs')->insertGetId([
            'model_name' => $modelName,
            'status' => 'triggering',
            'started_at' => now(),
            'dataset_range' => json_encode(['source' => 'supabase', 'mode' => 'manual_trigger'], JSON_THROW_ON_ERROR),
        ]);
    }

    private function finishTrainingRun(?int $runId, string $status, array $result): void
    {
        if ($runId === null || ! Schema::hasTable('ml_training_runs')) {
            return;
        }

        DB::table('ml_training_runs')
            ->where('id', $runId)
            ->update([
                'status' => $status,
                'ended_at' => now(),
                'metrics_json' => json_encode($result['data'] ?? $result, JSON_THROW_ON_ERROR),
            ]);
    }
}
