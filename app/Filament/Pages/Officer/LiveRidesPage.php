<?php

namespace App\Filament\Pages\Officer;

use App\Enums\UserRole;
use App\Services\ActionAuditLogger;
use App\Services\MobileNotificationService;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\DB;
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
                'drivers.license_plate',
                'drivers.status as driver_status',
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

        if ($excludeDriverId !== null) {
            $query->where('drivers.id', '!=', $excludeDriverId);
        }

        if (Schema::hasColumn('drivers', 'status')) {
            $query->whereIn(DB::raw('LOWER(drivers.status)'), ['approved', 'active', 'available']);
        }

        $drivers = $query
            ->orderBy('drivers.id')
            ->limit(30)
            ->get();

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

        return $drivers->map(function ($driver) use ($vehicleByDriver): array {
            $vehicle = $vehicleByDriver->get($driver->driver_id);
            $vehicleLabelParts = array_filter([
                $vehicle->make ?? null,
                $vehicle->model ?? null,
                $vehicle->color ?? null,
            ]);

            $lat = $driver->current_latitude ?? null;
            $lng = $driver->current_longitude ?? null;

            return [
                'driver_id' => (int) $driver->driver_id,
                'driver_name' => $driver->driver_name ?? ('Driver #'.$driver->driver_id),
                'license_plate' => $driver->license_plate ?? 'N/A',
                'vehicle' => ! empty($vehicleLabelParts) ? implode(' • ', $vehicleLabelParts) : 'Vehicle details unavailable',
                'availability' => $driver->availability_status ?? ((isset($driver->is_online) && $driver->is_online) ? 'online' : 'offline'),
                'location' => ($lat !== null && $lng !== null)
                    ? number_format((float) $lat, 6).', '.number_format((float) $lng, 6)
                    : 'Unknown location',
            ];
        })->values()->all();
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
