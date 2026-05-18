<?php

namespace App\Services;

use App\Events\Domain\BusPositionUpdated;
use App\Events\Domain\BusRouteAssignmentCreated;
use App\Events\Domain\PassengerBoardingUpdated;
use App\Events\Domain\StopArrivalReported;
use App\Exceptions\DomainException;
use App\Models\BusPositionUpdate;
use App\Models\BusRouteAssignment;
use App\Models\CorridorStop;
use App\Models\PassengerBoardingEvent;
use App\Models\PassengerRouteBoarding;
use App\Models\StopArrivalEvent;
use App\Models\TransportCorridor;
use App\Models\Trip;
use App\Models\User;
use App\Models\MobileUser;
use App\Models\Vehicle;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PublicBusTransportService
{
    public function __construct(
        private readonly TransportTicketService $ticketService,
        private readonly MLPredictionService $mlPredictionService,
    ) {}

    public function corridors(): Collection
    {
        return TransportCorridor::query()
            ->with(['stops' => fn ($query) => $query->orderBy('stop_order'), 'stopTimes'])
            ->where('transport_type', 'BUS')
            ->where('status', 'active')
            ->orderBy('corridor_code')
            ->get();
    }

    public function corridorStops(TransportCorridor $corridor): Collection
    {
        return $corridor->stops()->orderBy('stop_order')->get();
    }

    public function activeBuses(TransportCorridor $corridor, ?CorridorStop $boardingStop = null): Collection
    {
        return $this->candidateAssignments($corridor)
            ->get()
            ->map(function (BusRouteAssignment $assignment) use ($boardingStop): array {
                return $this->formatAssignment($assignment, $boardingStop);
            })
            ->sortByDesc('score')
            ->values();
    }

    public function createCorridor(array $data): TransportCorridor
    {
        return TransportCorridor::query()->create($data);
    }

    public function createStop(TransportCorridor $corridor, array $data): CorridorStop
    {
        return $corridor->stops()->create($data);
    }

    public function assignDriver(TransportCorridor $corridor, Vehicle $bus, int $driverId, ?int $tripId = null): BusRouteAssignment
    {
        return DB::transaction(function () use ($corridor, $bus, $driverId, $tripId): BusRouteAssignment {
            $lockedBus = Vehicle::query()->lockForUpdate()->findOrFail($bus->id);
            $assignment = BusRouteAssignment::query()
                ->where('bus_id', $lockedBus->id)
                ->where('corridor_id', $corridor->id)
                ->lockForUpdate()
                ->first();

            $assignment = $assignment ?: new BusRouteAssignment([
                'bus_id' => $lockedBus->id,
                'corridor_id' => $corridor->id,
            ]);

            $assignment->fill([
                'driver_id' => $driverId,
                'active_trip_id' => $tripId,
                'status' => 'active',
                'started_at' => $assignment->started_at ?? now(),
                'ended_at' => null,
            ])->save();

            event(new BusRouteAssignmentCreated((int) $assignment->id));

            return $assignment->fresh(['bus', 'driver.user', 'corridor']);
        });
    }

    public function bookSeat(User $user, array $data): PassengerRouteBoarding
    {
        return DB::transaction(function () use ($user, $data): PassengerRouteBoarding {
            $corridor = TransportCorridor::query()->lockForUpdate()->findOrFail((int) $data['corridor_id']);
            if ($corridor->status !== 'active') {
                throw DomainException::make('Corridor is not active', 'CORRIDOR_INACTIVE');
            }

            $boardingStop = CorridorStop::query()
                ->whereKey((int) $data['boarding_stop_id'])
                ->where('corridor_id', $corridor->id)
                ->lockForUpdate()
                ->firstOrFail();

            $destinationStop = CorridorStop::query()
                ->whereKey((int) $data['destination_stop_id'])
                ->where('corridor_id', $corridor->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($destinationStop->stop_order <= $boardingStop->stop_order) {
                throw DomainException::make('Destination stop must come after boarding stop', 'INVALID_STOP_ORDER');
            }

            $seats = max(1, (int) ($data['seats_reserved'] ?? 1));
            $passengerId = $this->resolvePassengerMobileUserId($user);
            $assignment = $this->resolveBookableAssignment($corridor, $boardingStop, $seats);

            $fareAmount = $this->calculateFareAmount($boardingStop, $destinationStop, $seats, $corridor);

            $trip = Trip::query()->create([
                'booking_id' => null,
                'ride_id' => null,
                'passenger_id' => $passengerId,
                'driver_id' => $assignment?->driver_id,
                'transport_type' => 'BUS',
                'pickup_location' => $boardingStop->stop_name,
                'pickup_place_name' => $boardingStop->stop_name,
                'pickup_lat' => $boardingStop->latitude ?? 0,
                'pickup_lng' => $boardingStop->longitude ?? 0,
                'dropoff_location' => $destinationStop->stop_name,
                'dropoff_place_name' => $destinationStop->stop_name,
                'dropoff_lat' => $destinationStop->latitude ?? 0,
                'dropoff_lng' => $destinationStop->longitude ?? 0,
                'fare' => $fareAmount,
                'status' => 'PENDING',
                'payment_status' => 'pending',
                'assignment_status' => 'assigned',
                'requested_at' => now(),
            ]);

            $ticketCode = sprintf('BUS-%s-%d-%s', now()->format('Ymd'), $trip->id, strtoupper(Str::random(6)));

            $boarding = PassengerRouteBoarding::query()->create([
                'passenger_id' => $passengerId,
                'trip_id' => $trip->id,
                'corridor_id' => $corridor->id,
                'bus_route_assignment_id' => $assignment?->id,
                'boarding_stop_id' => $boardingStop->id,
                'destination_stop_id' => $destinationStop->id,
                'ticket_code' => $ticketCode,
                'qr_payload' => $this->ticketPayload($ticketCode, $trip, $corridor, $boardingStop, $destinationStop, $assignment),
                'seats_reserved' => $seats,
                'fare_amount' => $fareAmount,
                'payment_status' => 'pending',
                'status' => 'reserved',
                'boarded_at' => null,
                'completed_at' => null,
            ]);

            if ($assignment && ! $assignment->active_trip_id) {
                $assignment->update(['active_trip_id' => $trip->id]);
            }

            $this->ticketService->issueForTrip($trip->fresh());

            return $boarding->fresh(['trip', 'corridor', 'boardingStop', 'destinationStop', 'busRouteAssignment.bus.driver.user']);
        });
    }

    public function currentTripForPassenger(int $passengerId): ?PassengerRouteBoarding
    {
        return PassengerRouteBoarding::query()
            ->with(['trip', 'corridor', 'boardingStop', 'destinationStop', 'busRouteAssignment.bus.driver.user'])
            ->where('passenger_id', $passengerId)
            ->whereIn('status', ['reserved', 'boarded'])
            ->latest()
            ->first();
    }

    public function boardingTicket(string|int $ticketRef, int $passengerId): PassengerRouteBoarding
    {
        return PassengerRouteBoarding::query()
            ->with(['trip', 'corridor', 'boardingStop', 'destinationStop', 'busRouteAssignment.bus.driver.user'])
            ->where('passenger_id', $passengerId)
            ->where(function (Builder $query) use ($ticketRef): void {
                if (is_numeric($ticketRef)) {
                    $query->whereKey((int) $ticketRef)->orWhere('ticket_code', (string) $ticketRef);

                    return;
                }

                $query->where('ticket_code', (string) $ticketRef);
            })
            ->firstOrFail();
    }

    public function updateLocation(int $assignmentId, array $data): BusPositionUpdate
    {
        return DB::transaction(function () use ($assignmentId, $data): BusPositionUpdate {
            $assignment = BusRouteAssignment::query()
                ->with(['corridor.stops', 'bus', 'driver'])
                ->lockForUpdate()
                ->findOrFail($assignmentId);

            $update = BusPositionUpdate::query()->create([
                'bus_route_assignment_id' => $assignment->id,
                'trip_id' => $assignment->active_trip_id,
                'latitude' => $data['latitude'],
                'longitude' => $data['longitude'],
                'speed_kph' => $data['speed_kph'] ?? null,
                'heading_degrees' => $data['heading_degrees'] ?? null,
                'next_stop_id' => $data['next_stop_id'] ?? null,
                'eta_minutes' => $data['eta_minutes'] ?? null,
                'route_progress_percent' => $data['route_progress_percent'] ?? null,
                'captured_at' => $data['captured_at'] ?? now(),
            ]);

            event(new BusPositionUpdated((int) $update->id));

            return $update->fresh(['assignment.bus', 'assignment.driver.user', 'nextStop']);
        });
    }

    public function markArrivedStop(int $assignmentId, int $stopId, ?int $tripId = null, array $metadata = []): StopArrivalEvent
    {
        return DB::transaction(function () use ($assignmentId, $stopId, $tripId, $metadata): StopArrivalEvent {
            $assignment = BusRouteAssignment::query()->lockForUpdate()->findOrFail($assignmentId);
            $stop = CorridorStop::query()->whereKey($stopId)->where('corridor_id', $assignment->corridor_id)->firstOrFail();

            $arrival = StopArrivalEvent::query()->create([
                'bus_route_assignment_id' => $assignment->id,
                'trip_id' => $tripId ?: $assignment->active_trip_id,
                'corridor_stop_id' => $stop->id,
                'arrival_time' => now(),
                'departure_time' => null,
                'is_terminal' => false,
                'metadata' => $metadata,
            ]);

            event(new StopArrivalReported((int) $arrival->id));

            return $arrival->fresh(['assignment.bus', 'stop']);
        });
    }

    public function markPassengerBoarded(int $boardingId, int $driverId, array $metadata = []): PassengerBoardingEvent
    {
        return DB::transaction(function () use ($boardingId, $driverId, $metadata): PassengerBoardingEvent {
            $boarding = PassengerRouteBoarding::query()->lockForUpdate()->findOrFail($boardingId);

            if ($boarding->status !== 'reserved') {
                throw DomainException::make('Boarding is not reserved', 'BOARDING_INVALID_STATE');
            }

            $boarding->update([
                'status' => 'boarded',
                'boarded_at' => now(),
                'payment_status' => $boarding->payment_status === 'pending' ? 'pending' : $boarding->payment_status,
            ]);

            $boarding->trip?->update([
                'status' => 'STARTED',
                'started_at' => $boarding->trip->started_at ?? now(),
                'driver_id' => $boarding->busRouteAssignment?->driver_id ?? $boarding->trip->driver_id,
            ]);

            $event = PassengerBoardingEvent::query()->create([
                'passenger_route_boarding_id' => $boarding->id,
                'trip_id' => $boarding->trip_id,
                'passenger_id' => $boarding->passenger_id,
                'boarding_stop_id' => $boarding->boarding_stop_id,
                'destination_stop_id' => $boarding->destination_stop_id,
                'verified_by_driver_id' => $driverId,
                'status' => 'boarded',
                'boarded_at' => now(),
                'verified_at' => now(),
                'metadata' => $metadata,
            ]);

            event(new PassengerBoardingUpdated((int) $event->id));

            return $event->fresh(['boarding.trip', 'boarding.corridor', 'boarding.boardingStop', 'boarding.destinationStop']);
        });
    }

    public function markPassengerCompleted(int $boardingId, int $driverId, array $metadata = []): PassengerRouteBoarding
    {
        return DB::transaction(function () use ($boardingId, $driverId, $metadata): PassengerRouteBoarding {
            $boarding = PassengerRouteBoarding::query()->lockForUpdate()->findOrFail($boardingId);

            if (! in_array($boarding->status, ['reserved', 'boarded'], true)) {
                throw DomainException::make('Boarding cannot be completed', 'BOARDING_INVALID_STATE');
            }

            $boarding->update([
                'status' => 'completed',
                'completed_at' => now(),
                'payment_status' => $boarding->payment_status === 'pending' ? 'paid' : $boarding->payment_status,
            ]);

            $boarding->trip?->update([
                'status' => 'COMPLETED',
                'completed_at' => now(),
                'driver_id' => $boarding->busRouteAssignment?->driver_id ?? $boarding->trip->driver_id,
                'payment_status' => $boarding->payment_status,
            ]);

            $event = PassengerBoardingEvent::query()->create([
                'passenger_route_boarding_id' => $boarding->id,
                'trip_id' => $boarding->trip_id,
                'passenger_id' => $boarding->passenger_id,
                'boarding_stop_id' => $boarding->boarding_stop_id,
                'destination_stop_id' => $boarding->destination_stop_id,
                'verified_by_driver_id' => $driverId,
                'status' => 'completed',
                'boarded_at' => $boarding->boarded_at,
                'verified_at' => now(),
                'metadata' => $metadata,
            ]);

            event(new PassengerBoardingUpdated((int) $event->id));

            return $boarding->fresh(['trip', 'corridor', 'boardingStop', 'destinationStop', 'busRouteAssignment.bus.driver.user']);
        });
    }

    public function validateStopSelection(int $corridorId, int $boardingStopId, int $destinationStopId): array
    {
        $corridor = TransportCorridor::query()->with('stops')->findOrFail($corridorId);
        $boardingStop = $corridor->stops->firstWhere('id', $boardingStopId);
        $destinationStop = $corridor->stops->firstWhere('id', $destinationStopId);

        if (! $boardingStop || ! $destinationStop) {
            throw DomainException::make('Stops must belong to the selected corridor', 'INVALID_CORRIDOR_STOPS');
        }

        if ($destinationStop->stop_order <= $boardingStop->stop_order) {
            throw DomainException::make('Destination stop must be after boarding stop', 'INVALID_STOP_ORDER');
        }

        return [$corridor, $boardingStop, $destinationStop];
    }

    private function candidateAssignments(TransportCorridor $corridor): Builder
    {
        return BusRouteAssignment::query()
            ->with(['bus', 'driver.user', 'activeTrip', 'positionUpdates' => fn ($query) => $query->latest('captured_at')->limit(1), 'stopArrivals' => fn ($query) => $query->latest('arrival_time')->limit(1)])
            ->where('corridor_id', $corridor->id)
            ->where('status', 'active')
            ->whereHas('bus', fn (Builder $query) => $query->where('is_active', true))
            ->whereHas('driver', fn (Builder $query) => $query->where('status', 'approved')->whereIn('availability_status', ['available', 'online']));
    }

    private function resolveBookableAssignment(TransportCorridor $corridor, CorridorStop $boardingStop, int $seats): ?BusRouteAssignment
    {
        $candidates = $this->activeBuses($corridor, $boardingStop);

        foreach ($candidates as $candidate) {
            $assignment = BusRouteAssignment::query()->with(['bus', 'driver.user', 'activeTrip'])->lockForUpdate()->find($candidate['assignment_id']);
            if (! $assignment) {
                continue;
            }

            if ($this->availableSeatsForAssignment($assignment->id) >= $seats) {
                return $assignment;
            }
        }

        throw DomainException::make('No bus with sufficient capacity is available on this corridor', 'NO_BOOKABLE_BUS');
    }

    private function availableSeatsForAssignment(int $assignmentId): int
    {
        $assignment = BusRouteAssignment::query()->with('bus')->findOrFail($assignmentId);

        $reservedSeats = PassengerRouteBoarding::query()
            ->where('bus_route_assignment_id', $assignment->id)
            ->whereIn('status', ['reserved', 'boarded', 'completed'])
            ->sum('seats_reserved');

        return max(0, (int) ($assignment->bus?->seats ?? 0) - (int) $reservedSeats);
    }

    private function formatAssignment(BusRouteAssignment $assignment, ?CorridorStop $boardingStop = null): array
    {
        $latestPosition = $assignment->positionUpdates->first();
        $availableSeats = $this->availableSeatsForAssignment($assignment->id);
        $demand = $this->predictDemandIndex($boardingStop);
        $etaMinutes = (int) ($latestPosition?->eta_minutes ?? $this->estimateEtaMinutes($assignment, $boardingStop));
        $score = $this->scoreAssignment($assignment, $boardingStop, $availableSeats, $etaMinutes, $demand);

        return [
            'assignment_id' => $assignment->id,
            'bus_id' => $assignment->bus_id,
            'corridor_id' => $assignment->corridor_id,
            'driver' => [
                'id' => $assignment->driver?->id,
                'name' => $assignment->driver?->user?->name,
                'rating' => (float) ($assignment->driver?->rating ?? 0),
                'availability_status' => $assignment->driver?->availability_status,
            ],
            'bus' => [
                'id' => $assignment->bus?->id,
                'plate' => $assignment->bus?->license_plate,
                'type' => $assignment->bus?->vehicle_type,
                'seats' => (int) ($assignment->bus?->seats ?? 0),
                'is_active' => (bool) $assignment->bus?->is_active,
            ],
            'available_seats' => $availableSeats,
            'active_trip_id' => $assignment->active_trip_id,
            'status' => $assignment->status,
            'started_at' => $assignment->started_at?->toIso8601String(),
            'latest_position' => $latestPosition ? [
                'latitude' => (float) $latestPosition->latitude,
                'longitude' => (float) $latestPosition->longitude,
                'speed_kph' => (float) ($latestPosition->speed_kph ?? 0),
                'heading_degrees' => $latestPosition->heading_degrees,
                'next_stop_id' => $latestPosition->next_stop_id,
                'eta_minutes' => $latestPosition->eta_minutes,
                'route_progress_percent' => (float) ($latestPosition->route_progress_percent ?? 0),
                'captured_at' => $latestPosition->captured_at?->toIso8601String(),
            ] : null,
            'eta_minutes' => $etaMinutes,
            'demand_index' => $demand,
            'score' => $score,
            'next_stop' => $latestPosition?->nextStop?->only(['id', 'stop_name', 'stop_order']) ?? null,
        ];
    }

    private function scoreAssignment(BusRouteAssignment $assignment, ?CorridorStop $boardingStop, int $availableSeats, int $etaMinutes, float $demandIndex): float
    {
        $driverRating = (float) ($assignment->driver?->rating ?? 0);
        $driverOnline = in_array((string) ($assignment->driver?->availability_status ?? ''), ['available', 'online'], true) ? 1.0 : 0.0;
        $approachingBoost = 0.0;
        $latestStop = $assignment->stopArrivals->first()?->stop;

        if ($boardingStop && $latestStop && $latestStop->stop_order <= $boardingStop->stop_order) {
            $approachingBoost = 1.0;
        }

        $seatRatio = min(1.0, $availableSeats / max(1, (int) ($assignment->bus?->seats ?? 1)));

        return round((
            ($driverOnline * 0.25)
            + (($driverRating / 5) * 0.20)
            + ($seatRatio * 0.20)
            + ($approachingBoost * 0.15)
            + (max(0, 1 - min(1, $etaMinutes / 60)) * 0.10)
            + ((1 - min(1, $demandIndex)) * 0.10)
        ) * 100, 2);
    }

    private function estimateEtaMinutes(BusRouteAssignment $assignment, ?CorridorStop $boardingStop): int
    {
        $position = $assignment->positionUpdates->first();
        if (! $position || ! $boardingStop || ! $boardingStop->latitude || ! $boardingStop->longitude) {
            return 15;
        }

        try {
            $prediction = $this->mlPredictionService->predictETA(
                (float) $position->latitude,
                (float) $position->longitude,
                (float) $boardingStop->latitude,
                (float) $boardingStop->longitude,
                $this->predictCongestionLevel($assignment),
                $this->distanceKm((float) $position->latitude, (float) $position->longitude, (float) $boardingStop->latitude, (float) $boardingStop->longitude)
            );

            return (int) round((float) ($prediction['estimated_time_minutes'] ?? 15));
        } catch (\Throwable) {
            return (int) max(5, round($this->distanceKm((float) $position->latitude, (float) $position->longitude, (float) $boardingStop->latitude, (float) $boardingStop->longitude) / 25 * 60));
        }
    }

    private function predictDemandIndex(?CorridorStop $boardingStop): float
    {
        if (! $boardingStop || ! $boardingStop->latitude || ! $boardingStop->longitude) {
            return 0.5;
        }

        try {
            $prediction = $this->mlPredictionService->predictDemand(
                (float) $boardingStop->latitude,
                (float) $boardingStop->longitude,
                (int) now()->hour,
                (int) now()->dayOfWeekIso
            );

            return (float) ($prediction['demand_level'] ?? 0.5);
        } catch (\Throwable) {
            return 0.5;
        }
    }

    private function predictCongestionLevel(BusRouteAssignment $assignment): float
    {
        $boardingCount = PassengerRouteBoarding::query()
            ->where('bus_route_assignment_id', $assignment->id)
            ->whereIn('status', ['reserved', 'boarded', 'completed'])
            ->sum('seats_reserved');

        $capacity = max(1, (int) ($assignment->bus?->seats ?? 1));

        return min(1.0, $boardingCount / $capacity);
    }

    private function calculateFareAmount(CorridorStop $boardingStop, CorridorStop $destinationStop, int $seats, TransportCorridor $corridor): float
    {
        $distanceKm = max(1, $this->distanceKm((float) ($boardingStop->latitude ?? 0), (float) ($boardingStop->longitude ?? 0), (float) ($destinationStop->latitude ?? 0), (float) ($destinationStop->longitude ?? 0)));
        $routeDurationFactor = max(1, (int) ($corridor->estimated_duration_minutes ?? 20));
        $baseFare = 300.0;
        $fare = ($distanceKm * $baseFare) + ($routeDurationFactor * 10);

        return round(max(500, $fare) * $seats, 2);
    }

    private function ticketPayload(string $ticketCode, Trip $trip, TransportCorridor $corridor, CorridorStop $boardingStop, CorridorStop $destinationStop, ?BusRouteAssignment $assignment): array
    {
        return [
            'ticket_code' => $ticketCode,
            'trip_id' => $trip->id,
            'corridor_id' => $corridor->id,
            'corridor_code' => $corridor->corridor_code,
            'boarding_stop' => $boardingStop->stop_name,
            'destination_stop' => $destinationStop->stop_name,
            'boarding_stop_id' => $boardingStop->id,
            'destination_stop_id' => $destinationStop->id,
            'bus_route_assignment_id' => $assignment?->id,
            'passenger_id' => $trip->passenger_id,
            'issued_at' => now()->toIso8601String(),
            'signature' => hash_hmac('sha256', json_encode([
                $ticketCode,
                $trip->id,
                $corridor->id,
                $boardingStop->id,
                $destinationStop->id,
            ], JSON_THROW_ON_ERROR), (string) config('app.key')),
        ];
    }

    private function resolvePassengerMobileUserId(User $user): int
    {
        if ($user->mobile_user_id) {
            return (int) $user->mobile_user_id;
        }

        $mobileUserId = MobileUser::query()
            ->where('email', $user->email)
            ->value('id');

        if ($mobileUserId) {
            return (int) $mobileUserId;
        }

        throw DomainException::make('Passenger mobile profile is not linked', 'PASSENGER_PROFILE_UNLINKED');
    }

    private function distanceKm(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earthRadius = 6371.0;

        $latDelta = deg2rad($lat2 - $lat1);
        $lngDelta = deg2rad($lng2 - $lng1);
        $a = sin($latDelta / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($lngDelta / 2) ** 2;

        return $earthRadius * (2 * atan2(sqrt($a), sqrt(1 - $a)));
    }
}