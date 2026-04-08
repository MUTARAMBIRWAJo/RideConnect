<?php

namespace App\Filament\Pages\Officer;

use App\Enums\UserRole;
use App\Services\ActionAuditLogger;
use App\Services\MobileNotificationService;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Carbon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;

class LiveRidesPage extends Page
{
    protected static ?string $navigationGroup = 'Live Operations';

    protected static string $view = 'filament.pages.officer.live-rides';

    /** @var array<int, array<string, mixed>> */
    public array $activeRides = [];

    /** @var array<int, array<string, mixed>> */
    public array $availableDrivers = [];

    public ?int $reassignRideId = null;

    public ?int $reassignCurrentDriverId = null;

    public ?int $selectedDriverId = null;

    public int $totalActiveCount = 0;

    public static function getNavigationLabel(): string
    {
        return 'Live Rides';
    }

    public static function getNavigationIcon(): string | Htmlable | null
    {
        return 'heroicon-o-map';
    }

    public static function canAccess(): bool
    {
        $user = auth()->user();

        if (! $user) {
            return false;
        }

        return ($user->role === UserRole::OFFICER)
            || $user->hasAnyRole(['Officer', 'officer', 'OFFICER']);
    }

    public function getTitle(): string
    {
        return 'Live Rides Monitoring';
    }

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);
        $this->loadActiveRides();
    }

    public function refreshRealtimeData(): void
    {
        $this->loadActiveRides();

        if ($this->reassignRideId) {
            $this->availableDrivers = $this->resolveAvailableDrivers($this->reassignCurrentDriverId);

            if ($this->selectedDriverId !== null) {
                $selectedStillExists = collect($this->availableDrivers)
                    ->contains(fn (array $driver): bool => (int) $driver['driver_id'] === (int) $this->selectedDriverId);

                if (! $selectedStillExists) {
                    $this->selectedDriverId = $this->availableDrivers[0]['driver_id'] ?? null;
                }
            }
        }
    }

    private function loadActiveRides(): void
    {
        if (!Schema::hasTable('rides')) {
            return;
        }

        $this->totalActiveCount = DB::table('rides')
            ->whereIn('status', ['in_progress', 'IN_PROGRESS', 'accepted', 'ACCEPTED'])
            ->count();

        $columns = collect(['id', 'status', 'driver_id', 'origin_address', 'destination_address', 'created_at', 'estimated_fare', 'distance'])
            ->filter(fn (string $column): bool => Schema::hasColumn('rides', $column))
            ->values()
            ->all();

        if ($columns === []) {
            return;
        }

        $this->activeRides = DB::table('rides')
            ->select($columns)
            ->whereIn('status', ['in_progress', 'IN_PROGRESS', 'accepted', 'ACCEPTED'])
            ->latest('id')
            ->get()
            ->map(fn ($row): array => (array) $row)
            ->all();
    }

    public function forceCancel(int $rideId): void
    {
        if (!auth()->user()->can('manage rides')) {
            abort(403);
        }

        $updates = ['status' => 'CANCELLED'];
        if (Schema::hasColumn('rides', 'updated_at')) {
            $updates['updated_at'] = now();
        }

        DB::table('rides')
            ->where('id', $rideId)
            ->update($updates);

        app(ActionAuditLogger::class)->log(
            'ride.force_cancel',
            'Officer force-cancelled ride #'.$rideId,
            ['ride_id' => $rideId],
        );

        $this->loadActiveRides();

        Notification::make()
            ->title('Ride cancelled successfully')
            ->success()
            ->send();
    }

    public function prepareReassignment(int $rideId): void
    {
        if (!auth()->user()->can('manage rides')) {
            abort(403);
        }

        $ride = DB::table('rides')->where('id', $rideId)->first(['id', 'driver_id']);

        if (! $ride) {
            Notification::make()
                ->title('Ride not found')
                ->danger()
                ->send();

            return;
        }

        $this->reassignRideId = (int) $ride->id;
        $this->reassignCurrentDriverId = isset($ride->driver_id) ? (int) $ride->driver_id : null;
        $this->availableDrivers = $this->resolveAvailableDrivers($this->reassignCurrentDriverId);
        $this->selectedDriverId = $this->availableDrivers[0]['driver_id'] ?? null;

        if (empty($this->availableDrivers)) {
            Notification::make()
                ->title('No online candidates found')
                ->body('No eligible online driver could be matched near this passenger right now.')
                ->warning()
                ->send();
        }
    }

    public function cancelReassignment(): void
    {
        $this->resetReassignmentState();
    }

    public function confirmReassignment(): void
    {
        if (! $this->reassignRideId) {
            Notification::make()
                ->title('No ride selected')
                ->warning()
                ->send();

            return;
        }

        if (! $this->selectedDriverId) {
            Notification::make()
                ->title('Select a driver first')
                ->warning()
                ->send();

            return;
        }

        $this->reassignDriver((int) $this->reassignRideId, (int) $this->selectedDriverId);
    }

    public function reassignDriver(int $rideId, ?int $newDriverId = null): void
    {
        if (!auth()->user()->can('manage rides')) {
            abort(403);
        }

        $ride = DB::table('rides')->where('id', $rideId)->first(['id', 'driver_id']);

        if (! $ride) {
            Notification::make()
                ->title('Ride not found')
                ->danger()
                ->send();

            return;
        }

        $currentDriverId = isset($ride->driver_id) ? (int) $ride->driver_id : null;
        $targetDriverId = $newDriverId ? (int) $newDriverId : null;

        if (! $targetDriverId) {
            $targetDriverId = $this->resolveReplacementDriverId($currentDriverId);
        }

        if (! $targetDriverId) {
            Notification::make()
                ->title('No replacement driver available')
                ->body('Reassignment requires an available driver. Please try again later.')
                ->warning()
                ->send();

            return;
        }

        if ($currentDriverId !== null && $targetDriverId === $currentDriverId) {
            Notification::make()
                ->title('Driver unchanged')
                ->body('Selected replacement is the same as the current driver.')
                ->warning()
                ->send();

            return;
        }

        $updates = ['driver_id' => $targetDriverId];
        if (Schema::hasColumn('rides', 'updated_at')) {
            $updates['updated_at'] = now();
        }

        DB::transaction(function () use ($rideId, $updates): void {
            DB::table('rides')
                ->where('id', $rideId)
                ->update($updates);
        });

        app(ActionAuditLogger::class)->log(
            'ride.reassign',
            'Officer reassigned ride #'.$rideId,
            [
                'ride_id' => $rideId,
                'previous_driver_id' => $currentDriverId,
                'new_driver_id' => $targetDriverId,
            ],
        );

        $this->sendReassignmentNotifications($rideId, $targetDriverId, $currentDriverId);

        $this->loadActiveRides();

        Notification::make()
            ->title('Ride reassigned successfully')
            ->body('Driver and passenger notifications have been sent.')
            ->success()
            ->send();

        $this->resetReassignmentState();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function resolveAvailableDrivers(?int $excludeDriverId = null): array
    {
        if (! Schema::hasTable('drivers')) {
            return [];
        }

        $hasUsersTable = Schema::hasTable('users');
        $hasVehiclesTable = Schema::hasTable('vehicles');

        $query = DB::table('drivers')
            ->select([
                'drivers.id as driver_id',
                'drivers.user_id',
                'drivers.license_plate',
                'drivers.status as driver_status',
                'drivers.updated_at as driver_updated_at',
            ]);

        if ($hasUsersTable && Schema::hasColumn('drivers', 'user_id') && Schema::hasColumn('users', 'id')) {
            $query->leftJoin('users', 'users.id', '=', 'drivers.user_id');
            if (Schema::hasColumn('users', 'name')) {
                $query->addSelect('users.name as driver_name');
            }
        }

        if (Schema::hasColumn('drivers', 'availability_status')) {
            $query->addSelect('drivers.availability_status');
            $query->orderByRaw("CASE WHEN LOWER(drivers.availability_status) = 'online' THEN 0 ELSE 1 END");
        }

        if (Schema::hasColumn('drivers', 'is_online')) {
            $query->addSelect('drivers.is_online');
            $query->orderByRaw('CASE WHEN drivers.is_online = true THEN 0 ELSE 1 END');
        }

        if (Schema::hasColumn('drivers', 'current_latitude')) {
            $query->addSelect('drivers.current_latitude');
        }

        if (Schema::hasColumn('drivers', 'current_longitude')) {
            $query->addSelect('drivers.current_longitude');
        }

        if (Schema::hasColumn('drivers', 'last_online_at')) {
            $query->addSelect('drivers.last_online_at');
        }

        if ($excludeDriverId !== null) {
            $query->where('drivers.id', '!=', $excludeDriverId);
        }

        if (Schema::hasColumn('drivers', 'status')) {
            $query->whereIn(DB::raw('LOWER(drivers.status)'), ['approved', 'active', 'available']);
        }

        $drivers = $query
            ->orderBy('drivers.id')
            ->limit(60)
            ->get();

        if ($drivers->isEmpty()) {
            return [];
        }

        $vehicleByDriver = collect();

        if ($hasVehiclesTable && Schema::hasColumn('vehicles', 'driver_id')) {
            $vehicleRows = DB::table('vehicles')
                ->select(array_filter([
                    'driver_id',
                    Schema::hasColumn('vehicles', 'make') ? 'make' : null,
                    Schema::hasColumn('vehicles', 'model') ? 'model' : null,
                    Schema::hasColumn('vehicles', 'color') ? 'color' : null,
                    Schema::hasColumn('vehicles', 'is_active') ? 'is_active' : null,
                    Schema::hasColumn('vehicles', 'id') ? 'id' : null,
                ]))
                ->when(Schema::hasColumn('vehicles', 'is_active'), fn ($q) => $q->orderByDesc('is_active'))
                ->orderByDesc('id')
                ->get();

            $vehicleByDriver = $vehicleRows->groupBy('driver_id')->map(fn ($group) => $group->first());
        }

        $pickupCoordinates = $this->resolveRidePickupCoordinates($this->reassignRideId);

        $driverRows = $drivers->map(fn ($driver) => (array) $driver)->all();
        $driverLocationMap = $this->resolveDriverLocationMap($driverRows);
        $driverLoadMap = $this->resolveDriverActiveLoadMap(array_map(static fn (array $row): int => (int) $row['driver_id'], $driverRows));

        $candidates = $drivers->map(function ($driver) use ($vehicleByDriver, $pickupCoordinates, $driverLocationMap, $driverLoadMap): array {
            $vehicle = $vehicleByDriver->get($driver->driver_id);
            $vehicleLabelParts = array_filter([
                $vehicle->make ?? null,
                $vehicle->model ?? null,
                $vehicle->color ?? null,
            ]);

            $location = $driverLocationMap[(int) $driver->driver_id] ?? null;
            $lat = $location['lat'] ?? null;
            $lng = $location['lng'] ?? null;
            $locationUpdatedAt = $location['updated_at']
                ?? (isset($driver->last_online_at) ? (string) $driver->last_online_at : null)
                ?? (isset($driver->driver_updated_at) ? (string) $driver->driver_updated_at : null);
            $distanceKm = $this->haversineDistanceKm(
                $pickupCoordinates['lat'] ?? null,
                $pickupCoordinates['lng'] ?? null,
                $lat,
                $lng,
            );

            $availability = strtolower((string) ($driver->availability_status ?? ((isset($driver->is_online) && $driver->is_online) ? 'online' : 'offline')));
            $isOnline = $availability === 'online';

            $activeLoad = (int) ($driverLoadMap[(int) $driver->driver_id] ?? 0);
            $mlScore = $this->resolveDriverMlScore(
                isOnline: $isOnline,
                distanceKm: $distanceKm,
                rating: isset($driver->rating) ? (float) $driver->rating : 0.0,
                activeLoad: $activeLoad,
                totalRides: isset($driver->completed_rides) ? (int) $driver->completed_rides : 0,
            );

            $heuristicScore = $this->resolveDriverHeuristicScore($isOnline, $distanceKm, isset($driver->rating) ? (float) $driver->rating : 0.0, $activeLoad);
            $finalScore = ($mlScore !== null)
                ? (($mlScore * 0.65) + ($heuristicScore * 0.35))
                : $heuristicScore;
            $freshness = $this->resolveLocationFreshness($locationUpdatedAt);

            return [
                'driver_id' => (int) $driver->driver_id,
                'driver_name' => $driver->driver_name ?? ('Driver #'.$driver->driver_id),
                'license_plate' => $driver->license_plate ?? 'N/A',
                'vehicle' => ! empty($vehicleLabelParts) ? implode(' • ', $vehicleLabelParts) : 'Vehicle details unavailable',
                'availability' => strtoupper($availability),
                'is_online' => $isOnline,
                'location' => ($lat !== null && $lng !== null)
                    ? number_format((float) $lat, 6).', '.number_format((float) $lng, 6)
                    : 'Unknown location',
                'location_updated_at' => $locationUpdatedAt,
                'location_freshness_label' => $freshness['label'],
                'location_freshness_tone' => $freshness['tone'],
                'distance_km' => $distanceKm,
                'distance_label' => $distanceKm !== null ? number_format($distanceKm, 2).' km' : 'Unknown distance',
                'ml_match_score' => $mlScore,
                'final_match_score' => round($finalScore, 2),
                'active_load' => $activeLoad,
            ];
        })->values();

        // Prioritize online drivers; fallback to all if none are online.
        $onlineCandidates = $candidates->where('is_online', true)->values();
        $ranked = ($onlineCandidates->isNotEmpty() ? $onlineCandidates : $candidates)
            ->sortByDesc('final_match_score')
            ->values()
            ->take(20)
            ->map(function (array $candidate, int $index): array {
                $candidate['rank'] = $index + 1;

                return $candidate;
            })
            ->all();

        return $ranked;
    }

    /**
     * @param  array<int, array<string, mixed>>  $driverRows
     * @return array<int, array{lat: float, lng: float, updated_at: string|null}>
     */
    private function resolveDriverLocationMap(array $driverRows): array
    {
        $locationMap = [];

        foreach ($driverRows as $row) {
            $driverId = (int) $row['driver_id'];
            $lat = isset($row['current_latitude']) ? (float) $row['current_latitude'] : null;
            $lng = isset($row['current_longitude']) ? (float) $row['current_longitude'] : null;

            if ($lat !== null && $lng !== null) {
                $locationMap[$driverId] = [
                    'lat' => $lat,
                    'lng' => $lng,
                    'updated_at' => isset($row['last_online_at']) ? (string) $row['last_online_at'] : null,
                ];
            }
        }

        if (!Schema::hasTable('driver_locations')) {
            return $locationMap;
        }

        $driverIds = array_values(array_unique(array_map(static fn (array $row): int => (int) $row['driver_id'], $driverRows)));

        $mobileUserIdsByDriverId = [];
        if (Schema::hasTable('users')) {
            $userIds = array_values(array_filter(array_unique(array_map(static fn (array $row): int => (int) ($row['user_id'] ?? 0), $driverRows))));
            if (!empty($userIds) && Schema::hasColumn('users', 'id') && Schema::hasColumn('users', 'mobile_user_id')) {
                $mobileUserIdsByUserId = DB::table('users')
                    ->whereIn('id', $userIds)
                    ->pluck('mobile_user_id', 'id')
                    ->toArray();

                foreach ($driverRows as $row) {
                    $driverId = (int) $row['driver_id'];
                    $userId = (int) ($row['user_id'] ?? 0);
                    $mobileUserId = isset($mobileUserIdsByUserId[$userId]) ? (int) $mobileUserIdsByUserId[$userId] : null;
                    if ($mobileUserId) {
                        $mobileUserIdsByDriverId[$driverId] = $mobileUserId;
                    }
                }
            }
        }

        $lookupIds = array_values(array_unique(array_filter(array_merge($driverIds, array_values($mobileUserIdsByDriverId)))));
        if (empty($lookupIds) || !Schema::hasColumn('driver_locations', 'driver_id') || !Schema::hasColumn('driver_locations', 'latitude') || !Schema::hasColumn('driver_locations', 'longitude')) {
            return $locationMap;
        }

        $locationRows = DB::table('driver_locations')
            ->whereIn('driver_id', $lookupIds)
            ->select(['driver_id', 'latitude', 'longitude'])
            ->when(Schema::hasColumn('driver_locations', 'updated_at'), fn ($q) => $q->addSelect('updated_at')->orderByDesc('updated_at'))
            ->get();

        $locationByKey = [];
        foreach ($locationRows as $locationRow) {
            $key = (int) $locationRow->driver_id;
            if (!isset($locationByKey[$key])) {
                $locationByKey[$key] = [
                    'lat' => (float) $locationRow->latitude,
                    'lng' => (float) $locationRow->longitude,
                    'updated_at' => isset($locationRow->updated_at) ? (string) $locationRow->updated_at : null,
                ];
            }
        }

        foreach ($driverRows as $row) {
            $driverId = (int) $row['driver_id'];

            if (isset($locationMap[$driverId])) {
                continue;
            }

            if (isset($locationByKey[$driverId])) {
                $locationMap[$driverId] = $locationByKey[$driverId];
                continue;
            }

            $mobileUserId = $mobileUserIdsByDriverId[$driverId] ?? null;
            if ($mobileUserId && isset($locationByKey[$mobileUserId])) {
                $locationMap[$driverId] = $locationByKey[$mobileUserId];
            }
        }

        return $locationMap;
    }

    /**
     * @param  array<int>  $driverIds
     * @return array<int, int>
     */
    private function resolveDriverActiveLoadMap(array $driverIds): array
    {
        if (empty($driverIds) || !Schema::hasTable('rides') || !Schema::hasColumn('rides', 'driver_id') || !Schema::hasColumn('rides', 'status')) {
            return [];
        }

        return DB::table('rides')
            ->whereIn('driver_id', $driverIds)
            ->whereIn(DB::raw('LOWER(status)'), ['in_progress', 'accepted'])
            ->selectRaw('driver_id, COUNT(*) as active_count')
            ->groupBy('driver_id')
            ->pluck('active_count', 'driver_id')
            ->map(fn ($value) => (int) $value)
            ->toArray();
    }

    /**
     * @return array{lat: float|null, lng: float|null}
     */
    private function resolveRidePickupCoordinates(?int $rideId): array
    {
        if (! $rideId || !Schema::hasTable('rides')) {
            return ['lat' => null, 'lng' => null];
        }

        $rideColumns = collect(['origin_lat', 'origin_lng', 'pickup_lat', 'pickup_lng'])
            ->filter(fn (string $column): bool => Schema::hasColumn('rides', $column))
            ->values()
            ->all();

        if (!empty($rideColumns)) {
            $ride = DB::table('rides')->where('id', $rideId)->first($rideColumns);
            $rideLat = isset($ride->origin_lat) ? (float) $ride->origin_lat : (isset($ride->pickup_lat) ? (float) $ride->pickup_lat : null);
            $rideLng = isset($ride->origin_lng) ? (float) $ride->origin_lng : (isset($ride->pickup_lng) ? (float) $ride->pickup_lng : null);
            if ($rideLat !== null && $rideLng !== null) {
                return ['lat' => $rideLat, 'lng' => $rideLng];
            }
        }

        if (Schema::hasTable('bookings') && Schema::hasColumn('bookings', 'ride_id') && Schema::hasColumn('bookings', 'pickup_lat') && Schema::hasColumn('bookings', 'pickup_lng')) {
            $booking = DB::table('bookings')
                ->where('ride_id', $rideId)
                ->whereNotNull('pickup_lat')
                ->whereNotNull('pickup_lng')
                ->when(Schema::hasColumn('bookings', 'created_at'), fn ($q) => $q->orderByDesc('created_at'))
                ->first(['pickup_lat', 'pickup_lng']);

            if ($booking) {
                return ['lat' => (float) $booking->pickup_lat, 'lng' => (float) $booking->pickup_lng];
            }
        }

        return ['lat' => null, 'lng' => null];
    }

    private function haversineDistanceKm(?float $lat1, ?float $lng1, ?float $lat2, ?float $lng2): ?float
    {
        if ($lat1 === null || $lng1 === null || $lat2 === null || $lng2 === null) {
            return null;
        }

        $earthRadiusKm = 6371.0;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);

        $a = sin($dLat / 2) * sin($dLat / 2)
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) * sin($dLng / 2);
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadiusKm * $c;
    }

    private function resolveDriverHeuristicScore(bool $isOnline, ?float $distanceKm, float $rating, int $activeLoad): float
    {
        $distanceComponent = $distanceKm === null ? 35.0 : max(0.0, 60.0 - ($distanceKm * 3.0));
        $ratingComponent = min(5.0, max(0.0, $rating)) * 8.0;
        $onlineComponent = $isOnline ? 25.0 : 0.0;
        $loadPenalty = min(20.0, $activeLoad * 4.0);

        return max(0.0, $distanceComponent + $ratingComponent + $onlineComponent - $loadPenalty);
    }

    private function resolveDriverMlScore(bool $isOnline, ?float $distanceKm, float $rating, int $activeLoad, int $totalRides): ?float
    {
        $baseUrl = rtrim((string) config('services.ml_service.url'), '/');
        if ($baseUrl === '') {
            return null;
        }

        // Driver ranker expects 21 features; unavailable values are safely zero-filled.
        $features = [
            (float) ($distanceKm ?? 0.0),
            $isOnline ? 1.0 : 0.0,
            $rating,
            (float) $activeLoad,
            (float) $totalRides,
            $distanceKm !== null && $distanceKm <= 3.0 ? 1.0 : 0.0,
            $distanceKm !== null && $distanceKm <= 1.0 ? 1.0 : 0.0,
            $distanceKm !== null ? 1.0 / (1.0 + $distanceKm) : 0.0,
            max(0.0, 5.0 - $rating),
            0.0,
            0.0,
            0.0,
            0.0,
            0.0,
            0.0,
            0.0,
            0.0,
            0.0,
            0.0,
            0.0,
            0.0,
        ];

        try {
            $request = Http::timeout((int) config('services.ml_service.timeout', 5));
            $apiKey = (string) config('services.ml_service.api_key');
            if ($apiKey !== '') {
                $request = $request->withHeaders(['X-API-KEY' => $apiKey]);
            }

            $response = $request->post($baseUrl.'/ml/rank-drivers', [
                'features' => $features,
            ]);

            if (! $response->successful()) {
                return null;
            }

            $score = data_get($response->json(), 'driver_ranks.0');

            return is_numeric($score) ? (float) $score : null;
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @return array{label: string, tone: string}
     */
    private function resolveLocationFreshness(?string $updatedAt): array
    {
        if (! $updatedAt) {
            return ['label' => 'UNKNOWN', 'tone' => 'slate'];
        }

        try {
            $minutes = now()->diffInMinutes(Carbon::parse($updatedAt));

            if ($minutes <= 2) {
                return ['label' => 'LIVE', 'tone' => 'emerald'];
            }

            if ($minutes <= 10) {
                return ['label' => 'STALE', 'tone' => 'amber'];
            }

            return ['label' => 'OLD', 'tone' => 'rose'];
        } catch (\Throwable) {
            return ['label' => 'UNKNOWN', 'tone' => 'slate'];
        }
    }

    private function resolveReplacementDriverId(?int $currentDriverId): ?int
    {
        if (!Schema::hasTable('drivers')) {
            return null;
        }

        $query = DB::table('drivers')->select('id');

        if ($currentDriverId !== null) {
            $query->where('id', '!=', $currentDriverId);
        }

        // Prioritize online/available drivers when those fields exist.
        if (Schema::hasColumn('drivers', 'availability_status')) {
            $query->orderByRaw("CASE WHEN LOWER(availability_status) = 'online' THEN 0 ELSE 1 END");
        }

        if (Schema::hasColumn('drivers', 'is_online')) {
            $query->orderByRaw('CASE WHEN is_online = true THEN 0 ELSE 1 END');
        }

        if (Schema::hasColumn('drivers', 'status')) {
            $query->whereIn(DB::raw('LOWER(status)'), ['approved', 'active', 'available']);
        }

        return $query->orderBy('id')->value('id');
    }

    private function resolvePassengerUserIdForRide(int $rideId): ?int
    {
        if (!Schema::hasTable('bookings') || !Schema::hasColumn('bookings', 'ride_id') || !Schema::hasColumn('bookings', 'user_id')) {
            return null;
        }

        $query = DB::table('bookings')
            ->where('ride_id', $rideId)
            ->select('user_id');

        if (Schema::hasColumn('bookings', 'status')) {
            $query->whereIn(DB::raw('LOWER(status)'), ['pending', 'confirmed', 'completed', 'in_progress']);
        }

        if (Schema::hasColumn('bookings', 'created_at')) {
            $query->orderByDesc('created_at');
        } else {
            $query->orderByDesc('id');
        }

        $userId = $query->value('user_id');

        return $userId ? (int) $userId : null;
    }

    private function sendReassignmentNotifications(int $rideId, int $newDriverId, ?int $previousDriverId = null): void
    {
        $mobileNotificationService = app(MobileNotificationService::class);

        if ($previousDriverId) {
            $previousDriverUserId = DB::table('drivers')->where('id', $previousDriverId)->value('user_id');
            if ($previousDriverUserId) {
                $mobileNotificationService->sendToUserId(
                    (int) $previousDriverUserId,
                    'ride_reassigned_away',
                    'Ride Reassigned',
                    'A ride previously assigned to you has been reassigned.',
                    [
                        'ride_id' => $rideId,
                        'new_driver_id' => $newDriverId,
                        'previous_driver_id' => $previousDriverId,
                    ]
                );
            }
        }

        $newDriverUserId = DB::table('drivers')->where('id', $newDriverId)->value('user_id');
        if ($newDriverUserId) {
            $mobileNotificationService->sendToUserId(
                (int) $newDriverUserId,
                'ride_reassigned_to_driver',
                'Ride Reassigned To You',
                'A ride has been reassigned to you. Please review and proceed.',
                [
                    'ride_id' => $rideId,
                    'new_driver_id' => $newDriverId,
                    'previous_driver_id' => $previousDriverId,
                ]
            );
        }

        $passengerUserId = $this->resolvePassengerUserIdForRide($rideId);
        if ($passengerUserId) {
            $mobileNotificationService->sendToUserId(
                $passengerUserId,
                'ride_driver_reassigned',
                'Driver Reassigned',
                'Your ride has been reassigned to another driver.',
                [
                    'ride_id' => $rideId,
                    'new_driver_id' => $newDriverId,
                    'previous_driver_id' => $previousDriverId,
                ]
            );
        }
    }

    private function resetReassignmentState(): void
    {
        $this->reassignRideId = null;
        $this->reassignCurrentDriverId = null;
        $this->selectedDriverId = null;
        $this->availableDrivers = [];
    }
}
