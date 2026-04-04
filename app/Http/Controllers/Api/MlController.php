<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\MlService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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
            'features' => 'required|array|size:8',
            'features.*' => 'required|numeric',
        ]);

        return $this->respond($this->mlService->predictDemand($payload['features']));
    }

    public function health(): JsonResponse
    {
        return $this->respond($this->mlService->health());
    }

    public function reloadModels(): JsonResponse
    {
        return $this->respond($this->mlService->reloadModels());
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
}
