<?php

namespace App\Filament\Pages\Officer;

use App\Enums\UserRole;
use App\Services\ActionAuditLogger;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DriverManagementPage extends Page
{
    protected static ?string $navigationGroup = 'Fleet Management';

    protected static string $view = 'filament.pages.officer.driver-management';

    /** @var array<int, array<string, mixed>> */
    public array $drivers = [];

    public int $totalDrivers = 0;

    public int $onlineDrivers = 0;

    public int $offlineDrivers = 0;

    public static function getNavigationLabel(): string
    {
        return 'Driver Management';
    }

    public static function getNavigationIcon(): string | Htmlable | null
    {
        return 'heroicon-o-users';
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
        return 'Driver Fleet Management';
    }

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);
        $this->loadDrivers();
    }

    private function loadDrivers(): void
    {
        if (!Schema::hasTable('drivers')) {
            return;
        }

        $columns = collect(['id', 'name', 'status', 'is_online', 'availability_status', 'rating', 'completed_rides', 'vehicle_id', 'created_at'])
            ->filter(fn (string $column): bool => Schema::hasColumn('drivers', $column))
            ->values()
            ->all();

        if ($columns === []) {
            return;
        }

        $query = DB::table('drivers')->select($columns);

        $this->drivers = $query->latest('id')->get()->map(function ($row): array {
            $driver = (array) $row;

            $driver['is_online'] = (bool) ($driver['is_online'] ?? false)
                || strtolower((string) ($driver['availability_status'] ?? '')) === 'online';

            return $driver;
        })->all();

        $this->totalDrivers = count($this->drivers);
        $this->onlineDrivers = collect($this->drivers)->filter(fn ($d) => $d['is_online'] ?? false)->count();
        $this->offlineDrivers = $this->totalDrivers - $this->onlineDrivers;
    }

    public function approveDriver(int $driverId): void
    {
        if (!auth()->user()->can('manage drivers')) {
            abort(403);
        }

        $updates = ['status' => 'approved'];
        if (Schema::hasColumn('drivers', 'updated_at')) {
            $updates['updated_at'] = now();
        }

        DB::table('drivers')
            ->where('id', $driverId)
            ->update($updates);

        app(ActionAuditLogger::class)->log(
            'driver.approve',
            'Officer approved driver #'.$driverId,
            ['driver_id' => $driverId],
        );

        $this->loadDrivers();

        Notification::make()
            ->title('Driver approved successfully')
            ->success()
            ->send();
    }

    public function suspendDriver(int $driverId, ?string $reason = null): void
    {
        if (!auth()->user()->can('manage drivers')) {
            abort(403);
        }

        $updates = ['status' => 'suspended'];
        if (Schema::hasColumn('drivers', 'updated_at')) {
            $updates['updated_at'] = now();
        }

        DB::table('drivers')
            ->where('id', $driverId)
            ->update($updates);

        app(ActionAuditLogger::class)->log(
            'driver.suspend',
            'Officer suspended driver #'.$driverId,
            ['driver_id' => $driverId, 'reason' => $reason],
        );

        $this->loadDrivers();

        Notification::make()
            ->title('Driver suspended successfully')
            ->warning()
            ->send();
    }

    public function toggleOnlineStatus(int $driverId): void
    {
        if (!auth()->user()->can('manage drivers')) {
            abort(403);
        }

        $hasIsOnlineColumn = Schema::hasColumn('drivers', 'is_online');
        $hasAvailabilityStatusColumn = Schema::hasColumn('drivers', 'availability_status');

        if (! $hasIsOnlineColumn && ! $hasAvailabilityStatusColumn) {
            Notification::make()
                ->title('Online status is not configurable in this environment')
                ->warning()
                ->send();

            return;
        }

        $driver = DB::table('drivers')->where('id', $driverId)->first();
        if ($driver) {
            $currentlyOnline = (bool) ($driver->is_online ?? false)
                || strtolower((string) ($driver->availability_status ?? '')) === 'online';

            $updates = [];

            if ($hasIsOnlineColumn) {
                $updates['is_online'] = ! $currentlyOnline;
            }

            if ($hasAvailabilityStatusColumn) {
                $updates['availability_status'] = $currentlyOnline ? 'offline' : 'online';
            }

            if (Schema::hasColumn('drivers', 'updated_at')) {
                $updates['updated_at'] = now();
            }

            DB::table('drivers')
                ->where('id', $driverId)
                ->update($updates);

            app(ActionAuditLogger::class)->log(
                'driver.toggle_online_status',
                'Officer toggled online status for driver #'.$driverId,
                ['driver_id' => $driverId, 'is_online' => ! $currentlyOnline],
            );

            Notification::make()
                ->title('Driver online status updated')
                ->success()
                ->send();
        }

        $this->loadDrivers();
    }
}
