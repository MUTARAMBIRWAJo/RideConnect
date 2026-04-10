<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Driver;
use App\Models\Ride;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

class LiveMapDataController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        if (! $this->canAccessLiveMap($request->user())) {
            return response()->json([
                'success' => false,
                'message' => 'Forbidden',
            ], 403);
        }

        try {
            $payload = Cache::remember('superadmin.live_map_payload', 20, function (): array {
                $drivers = collect();

                if (
                    Schema::hasTable('drivers')
                    && Schema::hasColumn('drivers', 'current_latitude')
                    && Schema::hasColumn('drivers', 'current_longitude')
                ) {
                    $drivers = Driver::query()
                        ->with('user:id,name')
                        ->whereNotNull('current_latitude')
                        ->whereNotNull('current_longitude')
                        ->limit(600)
                        ->get(['id', 'user_id', 'current_latitude', 'current_longitude', 'status'])
                        ->map(fn (Driver $driver) => [
                            'id' => (int) $driver->id,
                            'name' => (string) ($driver->user?->name ?? ('Driver #' . $driver->id)),
                            'latitude' => (float) $driver->current_latitude,
                            'longitude' => (float) $driver->current_longitude,
                            'status' => strtolower((string) ($driver->status ?? 'offline')),
                        ])
                        ->values();
                }

                $rides = collect();

                if (
                    Schema::hasTable('rides')
                    && Schema::hasColumn('rides', 'origin_lat')
                    && Schema::hasColumn('rides', 'origin_lng')
                    && Schema::hasColumn('rides', 'destination_lat')
                    && Schema::hasColumn('rides', 'destination_lng')
                ) {
                    $rides = Ride::query()
                        ->whereNotNull('origin_lat')
                        ->whereNotNull('origin_lng')
                        ->whereNotNull('destination_lat')
                        ->whereNotNull('destination_lng')
                        ->latest('id')
                        ->limit(1000)
                        ->get(['id', 'origin_lat', 'origin_lng', 'destination_lat', 'destination_lng', 'status'])
                        ->map(fn (Ride $ride) => [
                            'id' => (int) $ride->id,
                            'pickup_lat' => (float) $ride->origin_lat,
                            'pickup_lng' => (float) $ride->origin_lng,
                            'dropoff_lat' => (float) $ride->destination_lat,
                            'dropoff_lng' => (float) $ride->destination_lng,
                            'status' => (string) ($ride->status ?? 'unknown'),
                        ])
                        ->values();
                }

                return [
                    'success' => true,
                    'drivers' => $drivers,
                    'rides' => $rides,
                    'meta' => [
                        'generated_at' => now()->toIso8601String(),
                        'cached_for_seconds' => 20,
                    ],
                ];
            });

            return response()->json($payload);
        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'success' => true,
                'drivers' => [],
                'rides' => [],
                'meta' => [
                    'generated_at' => now()->toIso8601String(),
                    'degraded' => true,
                ],
            ]);
        }
    }

    private function canAccessLiveMap(?User $user): bool
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
