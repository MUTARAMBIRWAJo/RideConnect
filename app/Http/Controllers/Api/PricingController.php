<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class PricingController extends Controller
{
    public function calculate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'distance' => ['required', 'numeric', 'min:0'],
            'transport_type' => ['required', 'string', 'in:BUS,CAR,MOTORCYCLE'],
        ]);

        $distance = (float) $validated['distance'];
        $transportType = strtoupper(trim($validated['transport_type']));

        $price = match ($transportType) {
            'BUS' => $distance * 0.05,
            'CAR' => $distance * 0.1,
            'MOTORCYCLE' => $distance * 0.08,
            default => 0.0,
        };

        return response()->json([
            'success' => true,
            'price' => round($price, 2),
            'currency' => 'RWF',
            'distance' => $distance,
            'transport_type' => $transportType,
        ]);
    }
}
