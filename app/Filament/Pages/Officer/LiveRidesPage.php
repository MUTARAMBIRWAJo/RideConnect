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
}
