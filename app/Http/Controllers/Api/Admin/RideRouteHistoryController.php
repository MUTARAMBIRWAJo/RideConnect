<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Trip;
use Illuminate\Http\JsonResponse;

class RideRouteHistoryController extends Controller
{
    public function show(int $ride): JsonResponse
    {
        $trip = Trip::query()->findOrFail($ride);

        if ($trip->status !== 'COMPLETED') {
            return response()->json([
                'message' => 'Route replay is only available for completed rides.',
            ], 422);
        }

        if ($trip->pickup_lat === null || $trip->pickup_lng === null || $trip->dropoff_lat === null || $trip->dropoff_lng === null) {
            return response()->json([
                'message' => 'Ride route coordinates are not available for replay.',
            ], 422);
        }

        $points = $this->buildReplayPath(
            (float) $trip->pickup_lat,
            (float) $trip->pickup_lng,
            (float) $trip->dropoff_lat,
            (float) $trip->dropoff_lng
        );

        return response()->json([
            'coordinates' => $points,
        ]);
    }

    private function buildReplayPath(float $startLat, float $startLng, float $endLat, float $endLng): array
    {
        $steps = 24;
        $points = [];

        for ($i = 0; $i <= $steps; $i++) {
            $t = $i / $steps;
            $curve = sin($t * M_PI) * 0.0008;

            $points[] = [
                'lat' => round($startLat + (($endLat - $startLat) * $t) + $curve, 7),
                'lng' => round($startLng + (($endLng - $startLng) * $t) - $curve, 7),
            ];
        }

        return $points;
    }
}
