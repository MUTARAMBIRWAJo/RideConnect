<?php

namespace App\Http\Controllers\Health;

use App\Http\Controllers\Controller;
use App\Services\HealthCheckService;
use Illuminate\Http\JsonResponse;

class PlatformHealthController extends Controller
{
    public function __construct(
        private readonly HealthCheckService $health,
    ) {
    }

    public function live(): JsonResponse
    {
        return response()->json($this->health->live(), 200);
    }

    public function ready(): JsonResponse
    {
        $result = $this->health->ready();

        return response()->json($result['payload'], $result['http_status']);
    }

    public function full(): JsonResponse
    {
        $result = $this->health->full();

        return response()->json($result['payload'], $result['http_status']);
    }
}
