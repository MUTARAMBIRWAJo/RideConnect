<?php

namespace App\Filament\Pages\Officer;

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
        return auth()->check() && (auth()->user()->hasRole('officer') || auth()->user()->hasRole('OFFICER'));
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

        $updates = ['driver_id' => $newDriverId];
        if (Schema::hasColumn('rides', 'updated_at')) {
            $updates['updated_at'] = now();
        }

        DB::table('rides')
            ->where('id', $rideId)
            ->update($updates);

        $this->loadActiveRides();

        Notification::make()
            ->title('Ride reassigned successfully')
            ->success()
            ->send();
    }
}
