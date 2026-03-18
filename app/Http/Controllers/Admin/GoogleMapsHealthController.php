<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GoogleMapsHealthController extends Controller
{
    public function preflight(Request $request): JsonResponse
    {
        if (! $this->canAccessGoogleMapsHealth($request->user())) {
            return response()->json([
                'success' => false,
                'message' => 'Forbidden',
            ], 403);
        }

        $googleKey = trim((string) config('services.google_maps.key'));
        $laramapsKey = trim((string) config('laramaps.api_key'));
        $resolvedKey = $googleKey !== '' ? $googleKey : $laramapsKey;

        $configSource = 'missing';
        if ($googleKey !== '') {
            $configSource = 'services.google_maps.key';
        } elseif ($laramapsKey !== '') {
            $configSource = 'laramaps.api_key';
        }

        return response()->json([
            'success' => true,
            'checks' => [
                'key_present' => $resolvedKey !== '',
                'google_key_present' => $googleKey !== '',
                'laramaps_key_present' => $laramapsKey !== '',
                'config_source' => $configSource,
                'app_env' => (string) config('app.env'),
                'app_url' => (string) config('app.url'),
            ],
            'endpoints' => [
                'live_map' => route('api.map.live-data'),
            ],
        ]);
    }

    private function canAccessGoogleMapsHealth(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        $enumRole = $user->role?->value ?? (string) $user->role;
        if (in_array($enumRole, UserRole::managerRoles(), true)) {
            return true;
        }

        return method_exists($user, 'hasAnyRole')
            ? $user->hasAnyRole(['Super_admin', 'Admin', 'Officer', 'Accountant'])
            : false;
    }
}
