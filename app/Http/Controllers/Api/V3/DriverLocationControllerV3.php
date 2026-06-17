<?php

namespace App\Http\Controllers\Api\V3;

use App\Http\Controllers\Controller;
use App\Models\V3\DriverLocationV3;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class DriverLocationControllerV3 extends Controller
{
    public function update(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'lat' => 'required|numeric',
            'lng' => 'required|numeric',
            'heading' => 'nullable|numeric',
            'speed' => 'nullable|numeric',
            'is_online' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 400);
        }

        // Assume driver_id is retrieved from auth user
        $driverId = $request->user()->driver->id ?? null;

        if (!$driverId) {
            return response()->json(['error' => 'Unauthorized or not a driver'], 403);
        }

        $location = DriverLocationV3::updateOrCreate(
            ['driver_id' => $driverId],
            [
                'lat' => $request->lat,
                'lng' => $request->lng,
                'heading' => $request->heading,
                'speed' => $request->speed,
                'is_online' => $request->is_online ?? true,
            ]
        );

        return response()->json(['success' => true, 'data' => $location]);
    }
}
