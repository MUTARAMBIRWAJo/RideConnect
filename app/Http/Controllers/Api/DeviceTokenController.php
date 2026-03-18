<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MobileDeviceToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DeviceTokenController extends Controller
{
    /**
     * Register or refresh push token for authenticated user.
     * POST /api/v1/devices/push-token
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'platform' => 'required|string|in:fcm,apns',
            'device_token' => 'required|string|max:255',
            'device_id' => 'nullable|string|max:120',
        ]);

        $token = MobileDeviceToken::query()->updateOrCreate(
            ['device_token' => $validated['device_token']],
            [
                'user_id' => $request->user()->id,
                'platform' => $validated['platform'],
                'device_id' => $validated['device_id'] ?? null,
                'last_seen_at' => now(),
            ]
        );

        return response()->json([
            'success' => true,
            'message' => 'Push token registered successfully',
            'data' => [
                'id' => $token->id,
                'platform' => $token->platform,
                'device_id' => $token->device_id,
            ],
        ]);
    }

    /**
     * Unregister push token for authenticated user.
     * DELETE /api/v1/devices/push-token/{token}
     */
    public function destroy(Request $request, string $token): JsonResponse
    {
        MobileDeviceToken::query()
            ->where('user_id', $request->user()->id)
            ->where('device_token', $token)
            ->delete();

        return response()->json([
            'success' => true,
            'message' => 'Push token removed successfully',
        ]);
    }
}
