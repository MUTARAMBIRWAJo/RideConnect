<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BusRouteAssignment;
use App\Models\CorridorStop;
use App\Models\PassengerRouteBoarding;
use App\Models\TransportCorridor;
use App\Models\Vehicle;
use App\Services\PublicBusTransportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class OfficerPublicBusController extends Controller
{
    public function __construct(private readonly PublicBusTransportService $busService) {}

    public function corridors(Request $request): JsonResponse
    {
        Gate::authorize('create', TransportCorridor::class);

        $validated = $request->validate([
            'corridor_code' => 'required|string|max:64|unique:transport_corridors,corridor_code',
            'corridor_name' => 'required|string|max:255',
            'start_stop_id' => 'nullable|integer|exists:corridor_stops,id',
            'end_stop_id' => 'nullable|integer|exists:corridor_stops,id',
            'transport_type' => 'nullable|string|max:32',
            'status' => 'nullable|string|max:32',
            'estimated_duration_minutes' => 'nullable|integer|min:1',
        ]);

        $corridor = $this->busService->createCorridor([
            'corridor_code' => $validated['corridor_code'],
            'corridor_name' => $validated['corridor_name'],
            'start_stop_id' => $validated['start_stop_id'] ?? null,
            'end_stop_id' => $validated['end_stop_id'] ?? null,
            'transport_type' => strtoupper((string) ($validated['transport_type'] ?? 'BUS')),
            'status' => strtolower((string) ($validated['status'] ?? 'active')),
            'estimated_duration_minutes' => $validated['estimated_duration_minutes'] ?? null,
        ]);

        return response()->json(['success' => true, 'data' => $corridor], 201);
    }

    public function stops(Request $request): JsonResponse
    {
        Gate::authorize('create', CorridorStop::class);

        $validated = $request->validate([
            'corridor_id' => 'required|integer|exists:transport_corridors,id',
            'stop_name' => 'required|string|max:255',
            'stop_order' => 'required|integer|min:1',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'is_major_terminal' => 'nullable|boolean',
            'status' => 'nullable|string|max:32',
        ]);

        $corridor = TransportCorridor::query()->findOrFail((int) $validated['corridor_id']);
        $stop = $this->busService->createStop($corridor, [
            'stop_name' => $validated['stop_name'],
            'stop_order' => (int) $validated['stop_order'],
            'latitude' => $validated['latitude'] ?? null,
            'longitude' => $validated['longitude'] ?? null,
            'is_major_terminal' => (bool) ($validated['is_major_terminal'] ?? false),
            'status' => strtolower((string) ($validated['status'] ?? 'active')),
        ]);

        return response()->json(['success' => true, 'data' => $stop], 201);
    }

    public function assignDriver(Request $request): JsonResponse
    {
        Gate::authorize('create', BusRouteAssignment::class);

        $validated = $request->validate([
            'corridor_id' => 'required|integer|exists:transport_corridors,id',
            'bus_id' => 'required|integer|exists:vehicles,id',
            'driver_id' => 'required|integer|exists:drivers,id',
            'trip_id' => 'nullable|integer|exists:trips,id',
        ]);

        $corridor = TransportCorridor::query()->findOrFail((int) $validated['corridor_id']);
        $bus = Vehicle::query()->findOrFail((int) $validated['bus_id']);

        $assignment = $this->busService->assignDriver(
            $corridor,
            $bus,
            (int) $validated['driver_id'],
            $validated['trip_id'] ?? null,
        );

        return response()->json(['success' => true, 'data' => $assignment], 201);
    }

    public function liveMonitoring(Request $request): JsonResponse
    {
        $corridors = $this->busService->corridors()->map(function (TransportCorridor $corridor): array {
            $activeBuses = $this->busService->activeBuses($corridor);

            return [
                'corridor' => [
                    'id' => $corridor->id,
                    'corridor_code' => $corridor->corridor_code,
                    'corridor_name' => $corridor->corridor_name,
                ],
                'stop_count' => $corridor->stops->count(),
                'active_bus_count' => $activeBuses->count(),
                'seat_utilization' => $activeBuses->sum(fn (array $bus): float => max(0, (float) ($bus['bus']['seats'] ?? 0) - (float) $bus['available_seats'])),
                'active_buses' => $activeBuses,
            ];
        });

        return response()->json(['success' => true, 'data' => $corridors]);
    }
}
