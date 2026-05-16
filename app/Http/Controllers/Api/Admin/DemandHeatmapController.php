<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Services\DemandPredictionService;
use Illuminate\Http\JsonResponse;

class DemandHeatmapController extends Controller
{
    public function __construct(private readonly DemandPredictionService $demandPredictionService) {}

    public function index(): JsonResponse
    {
        return response()->json([
            'points' => $this->demandPredictionService->predict()->all(),
        ]);
    }
}
