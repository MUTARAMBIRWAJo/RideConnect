<?php

namespace App\Http\Controllers\Api\V3;

use App\Http\Controllers\Controller;
use App\Jobs\V3\ProcessTripMatchingV3;
use App\Models\Driver;
use App\Models\DriverLocation;
use App\Models\DriverTripOffer;
use App\Models\V3\TripV3;
use App\Services\V3\TripLifecycleEngineV3;
use App\Services\V3\TripLifecycleNotifierV3;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class DriverTripControllerV3 extends Controller
{
    public function __construct(
        private readonly TripLifecycleEngineV3 $lifecycle,
        private readonly TripLifecycleNotifierV3 $notifier,
    ) {}

    public function incoming(Request $request): JsonResponse
    {
        $driver = $this->driverFor($request);

        $offer = DriverTripOffer::query()
            ->with('trip')
            ->where('driver_id', $driver->id)
            ->where('status', 'pending')
            ->where('expires_at', '>', now())
            ->latest()
            ->first();

        if (!$offer || ! $offer->trip) {
            return response()->json([
                'success' => true,
                'data' => null,
                'message' => 'No incoming requests.',
            ]);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'offer' => $offer,
                'trip' => $offer->trip,
                'payload' => $offer->payload,
            ],
        ]);
    }

    public function accept(Request $request, string $id): JsonResponse
    {
        $driver = $this->driverFor($request);

        return DB::transaction(function () use ($id, $driver, $request) {
            $trip = TripV3::where('id', $id)->lockForUpdate()->firstOrFail();
            $offer = $this->pendingOffer($trip, $driver);

            if (! $offer || $trip->status !== 'MATCHING') {
                return response()->json([
                    'success' => false,
                    'message' => 'Trip is no longer available or not assigned to you.',
                ], 400);
            }

            $offer->update([
                'status' => 'accepted',
                'responded_at' => now(),
            ]);

            DriverTripOffer::query()
                ->where('trip_id', $trip->id)
                ->where('id', '!=', $offer->id)
                ->where('status', 'pending')
                ->update(['status' => 'superseded', 'updated_at' => now()]);

            $trip->forceFill([
                'driver_id' => $driver->id,
                'matched_driver_id' => $driver->id,
                'driver_response_status' => 'accepted',
                'matched_at' => now(),
            ]);
            $this->lifecycle->transition($trip, 'DRIVER_ASSIGNED');

            $driver->update([
                'availability_status' => 'busy',
                'is_available' => false,
                'current_trip_id' => $trip->id,
            ]);

            $this->syncActiveTrip($trip);
            $trip->loadMissing(['driver.user', 'driver.vehicle']);

            $payload = [
                'trip_id' => $trip->id,
                'driver' => $this->driverPayload($driver),
                'vehicle' => $driver->vehicle ? $driver->vehicle->only(['id', 'make', 'model', 'year', 'color', 'vehicle_type', 'seats', 'license_plate']) : null,
            ];
            $this->notifier->dispatch($trip, 'trip.driver.accepted', $payload, $driver);

            return response()->json([
                'success' => true,
                'data' => $trip->fresh(['driver.user', 'driver.vehicle']),
            ]);
        });
    }

    public function reject(Request $request, string $id): JsonResponse
    {
        $driver = $this->driverFor($request);

        return DB::transaction(function () use ($id, $driver, $request) {
            $trip = TripV3::where('id', $id)->lockForUpdate()->firstOrFail();
            $offer = $this->pendingOffer($trip, $driver);

            if (! $offer || $trip->status !== 'MATCHING') {
                return response()->json([
                    'success' => false,
                    'message' => 'Trip is no longer available or not assigned to you.',
                ], 400);
            }

            $offer->update([
                'status' => 'rejected',
                'responded_at' => now(),
                'response_reason' => $request->input('reason'),
            ]);

            $ignored = $trip->ignored_driver_ids ?? [];
            if (! in_array($driver->id, $ignored, true)) {
                $ignored[] = $driver->id;
            }

            $trip->ignored_driver_ids = $ignored;
            $trip->matched_driver_id = null;
            $trip->driver_id = null;
            $trip->driver_response_status = 'rejected';
            $this->lifecycle->transition($trip, 'MATCHING');

            $this->notifier->dispatch($trip, 'trip.driver.rejected', [
                'trip_id' => $trip->id,
                'driver_id' => $driver->id,
                'reason' => $request->input('reason', 'rejected'),
                'message' => 'Driver rejected. Finding another driver...',
            ], $driver);

            ProcessTripMatchingV3::dispatch($trip);

            return response()->json([
                'success' => true,
                'message' => 'Trip rejected successfully. Re-matching...',
            ]);
        });
    }

    public function arrived(Request $request, string $id): JsonResponse
    {
        return $this->driverTransition($request, $id, ['DRIVER_ASSIGNED'], 'DRIVER_ARRIVED', 'trip.driver.arrived');
    }

    public function start(Request $request, string $id): JsonResponse
    {
        return $this->driverTransition($request, $id, ['DRIVER_ARRIVED'], 'IN_PROGRESS', 'trip.started', [
            'trip_started_at' => now(),
        ]);
    }

    public function complete(Request $request, string $id): JsonResponse
    {
        $driver = $this->driverFor($request);

        return DB::transaction(function () use ($id, $driver) {
            $trip = TripV3::where('id', $id)->lockForUpdate()->firstOrFail();
            $this->assertDriverOwnsTrip($trip, $driver);

            if ($trip->status !== 'IN_PROGRESS') {
                return response()->json(['success' => false, 'message' => 'Trip is not in progress.'], 422);
            }

            $distanceKm = $this->distanceKm($trip);
            $durationMinutes = $trip->trip_started_at ? max(1, $trip->trip_started_at->diffInMinutes(now())) : null;
            $finalFare = (float) ($trip->fare_actual ?? $trip->fare_estimate ?? max(1500, $distanceKm * 900));

            $trip->forceFill([
                'fare_actual' => $finalFare,
                'trip_completed_at' => now(),
                'metadata' => array_merge($trip->metadata ?? [], [
                    'distance_traveled_km' => round($distanceKm, 2),
                    'duration_minutes' => $durationMinutes,
                ]),
            ]);
            $this->lifecycle->transition($trip, 'COMPLETED');

            $driver->update([
                'availability_status' => 'available',
                'is_available' => true,
                'current_trip_id' => null,
            ]);

            $this->syncActiveTrip($trip);
            $this->notifier->dispatch($trip, 'trip.completed', [
                'trip_id' => $trip->id,
                'distance_traveled_km' => round($distanceKm, 2),
                'duration_minutes' => $durationMinutes,
                'final_fare' => $finalFare,
            ], $driver);

            return response()->json(['success' => true, 'data' => $trip->fresh()]);
        });
    }

    public function pay(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'payment_method' => 'required|string|in:cash,mobile_money,card,Cash,Mobile Money,Card',
            'payment_reference' => 'nullable|string|max:120',
            'amount' => 'required|numeric|min:0',
        ]);

        return DB::transaction(function () use ($id, $validated) {
            $trip = TripV3::where('id', $id)->lockForUpdate()->firstOrFail();

            if ((int) $trip->user_id !== (int) request()->user()->id) {
                abort(403, 'Only the passenger can pay for this trip.');
            }
            if ($trip->status !== 'COMPLETED') {
                return response()->json(['success' => false, 'message' => 'Trip must be completed before payment.'], 422);
            }

            $trip->forceFill([
                'payment_method' => strtolower(str_replace(' ', '_', $validated['payment_method'])),
                'payment_reference' => $validated['payment_reference'] ?? 'cash-'.$trip->id,
                'amount_paid' => $validated['amount'],
                'paid_at' => now(),
            ]);
            $this->lifecycle->transition($trip, 'PAID');
            $this->syncActiveTrip($trip);
            $this->notifier->dispatch($trip, 'trip.payment.completed', [
                'trip_id' => $trip->id,
                'payment_method' => $trip->payment_method,
                'payment_reference' => $trip->payment_reference,
                'amount' => (float) $trip->amount_paid,
            ], $trip->driver);

            return response()->json(['success' => true, 'data' => $trip->fresh()]);
        });
    }

    public function rate(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'rating' => 'required|integer|min:1|max:5',
            'comment' => 'nullable|string|max:1000',
        ]);

        return DB::transaction(function () use ($id, $validated) {
            $trip = TripV3::where('id', $id)->lockForUpdate()->firstOrFail();

            if ((int) $trip->user_id !== (int) request()->user()->id) {
                abort(403, 'Only the passenger can rate this trip.');
            }
            if ($trip->status !== 'PAID') {
                return response()->json(['success' => false, 'message' => 'Trip must be paid before rating.'], 422);
            }

            $trip->forceFill([
                'rating' => $validated['rating'],
                'rating_comment' => $validated['comment'] ?? null,
                'rated_at' => now(),
            ]);
            $this->lifecycle->transition($trip, 'RATED');

            if ($trip->driver) {
                $count = (int) ($trip->driver->rating_count ?? 0);
                $average = (float) ($trip->driver->rating ?? 0);
                $trip->driver->update([
                    'rating_count' => $count + 1,
                    'rating' => round((($average * $count) + $validated['rating']) / max(1, $count + 1), 2),
                ]);
            }

            $this->syncActiveTrip($trip);
            $this->notifier->dispatch($trip, 'trip.rating.submitted', [
                'trip_id' => $trip->id,
                'rating' => $validated['rating'],
                'comment' => $validated['comment'] ?? null,
            ], $trip->driver);

            return response()->json(['success' => true, 'data' => $trip->fresh(['driver'])]);
        });
    }

    public function updateLocation(Request $request): JsonResponse
    {
        $driver = $this->driverFor($request);
        $validated = $request->validate([
            'trip_id' => 'required|uuid|exists:trips_v3,id',
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'heading' => 'nullable|numeric|between:0,360',
            'speed' => 'nullable|numeric|min:0|max:220',
        ]);

        $trip = TripV3::query()
            ->where('id', $validated['trip_id'])
            ->where('driver_id', $driver->id)
            ->whereIn('status', ['DRIVER_ASSIGNED', 'DRIVER_ARRIVED', 'IN_PROGRESS'])
            ->firstOrFail();

        $driverLocationId = $driver->user_id ?: $driver->id;
        $payload = ['driver_id' => $driverLocationId];
        $optionalColumns = [
            'latitude' => $validated['latitude'],
            'longitude' => $validated['longitude'],
            'lat' => $validated['latitude'],
            'lng' => $validated['longitude'],
            'heading' => $validated['heading'] ?? null,
            'speed' => $validated['speed'] ?? null,
            'speed_kmh' => $validated['speed'] ?? null,
            'is_online' => true,
            'recorded_at' => now(),
            'last_activity_at' => now(),
            'updated_at' => now(),
        ];
        foreach ($optionalColumns as $column => $value) {
            if (Schema::hasColumn('driver_locations', $column)) {
                $payload[$column] = $value;
            }
        }
        if (Schema::hasColumn('driver_locations', 'trip_id') && is_numeric($trip->id)) {
            $payload['trip_id'] = $trip->id;
        }

        $location = DriverLocation::query()->updateOrCreate(['driver_id' => $driverLocationId], $payload);

        $driver->update([
            'current_latitude' => $validated['latitude'],
            'current_longitude' => $validated['longitude'],
            'last_seen_at' => now(),
            'last_online_at' => now(),
            'is_online' => true,
        ]);

        return response()->json(['success' => true, 'data' => $location]);
    }

    private function driverTransition(Request $request, string $id, array $fromStatuses, string $toStatus, string $eventName, array $fills = []): JsonResponse
    {
        $driver = $this->driverFor($request);

        return DB::transaction(function () use ($id, $driver, $fromStatuses, $toStatus, $eventName, $fills) {
            $trip = TripV3::where('id', $id)->lockForUpdate()->firstOrFail();
            $this->assertDriverOwnsTrip($trip, $driver);

            if (! in_array($trip->status, $fromStatuses, true)) {
                return response()->json(['success' => false, 'message' => 'Trip cannot move to '.$toStatus.' from '.$trip->status.'.'], 422);
            }

            $trip->forceFill($fills);
            $this->lifecycle->transition($trip, $toStatus);
            $this->syncActiveTrip($trip);
            $this->notifier->dispatch($trip, $eventName, [
                'trip_id' => $trip->id,
                'driver_id' => $driver->id,
                'status' => $toStatus,
            ], $driver);

            return response()->json(['success' => true, 'data' => $trip->fresh()]);
        });
    }

    private function pendingOffer(TripV3 $trip, Driver $driver): ?DriverTripOffer
    {
        return DriverTripOffer::query()
            ->where('trip_id', $trip->id)
            ->where('driver_id', $driver->id)
            ->where('status', 'pending')
            ->where('expires_at', '>', now())
            ->lockForUpdate()
            ->latest()
            ->first();
    }

    private function driverFor(Request $request): Driver
    {
        return $request->user()->driver ?: Driver::query()->where('user_id', $request->user()->id)->firstOrFail();
    }

    private function assertDriverOwnsTrip(TripV3 $trip, Driver $driver): void
    {
        if ((int) $trip->driver_id !== (int) $driver->id) {
            abort(403, 'This trip is not assigned to the authenticated driver.');
        }
    }

    private function syncActiveTrip(TripV3 $trip): void
    {
        DB::table('active_trips_v3')->updateOrInsert(
            ['trip_id' => $trip->id],
            [
                'id' => (string) Str::uuid(),
                'driver_id' => $trip->driver_id,
                'passenger_id' => $trip->user_id,
                'status' => $trip->status,
                'created_at' => $trip->created_at ?? now(),
                'updated_at' => now(),
            ],
        );
    }

    private function driverPayload(Driver $driver): array
    {
        return [
            'id' => $driver->id,
            'name' => $driver->user?->name,
            'phone' => $driver->user?->phone,
            'rating' => (float) ($driver->rating ?? 0),
            'rating_count' => (int) ($driver->rating_count ?? 0),
            'current_latitude' => $driver->current_latitude ? (float) $driver->current_latitude : null,
            'current_longitude' => $driver->current_longitude ? (float) $driver->current_longitude : null,
        ];
    }

    private function distanceKm(TripV3 $trip): float
    {
        if (! $trip->pickup_lat || ! $trip->pickup_lng || ! $trip->dropoff_lat || ! $trip->dropoff_lng) {
            return 0.0;
        }

        $latDelta = deg2rad((float) $trip->dropoff_lat - (float) $trip->pickup_lat);
        $lngDelta = deg2rad((float) $trip->dropoff_lng - (float) $trip->pickup_lng);
        $a = sin($latDelta / 2) ** 2
            + cos(deg2rad((float) $trip->pickup_lat)) * cos(deg2rad((float) $trip->dropoff_lat)) * sin($lngDelta / 2) ** 2;

        return 6371 * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }
}
