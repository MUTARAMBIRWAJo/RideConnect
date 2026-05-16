<?php

namespace App\Filament\Pages;

use App\Enums\UserRole;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class OfficerDashboardV2 extends Page
{
    protected static ?string $navigationGroup = 'Dashboards';

    protected static string $routePath = '/officer-dashboard-v2';

    protected static string $view = 'filament.pages.officer-dashboard-static';

    public int $activeRidesCount = 0;

    public int $pendingBookingsCount = 0;

    public int $openTicketsCount = 0;

    public int $onlineDriversCount = 0;

    public int $overdueBookingsCount = 0;

    public int $highPriorityTicketsCount = 0;

    public int $cancelledRidesTodayCount = 0;

    /** @var array<int, array<string, mixed>> */
    public array $recentBookings = [];

    /** @var array<int, array<string, mixed>> */
    public array $recentTickets = [];

    /** @var array<int, array<string, mixed>> */
    public array $escalationTickets = [];

    /** @var array<int, array<string, mixed>> */
    public array $unassignedRides = [];

    public static function getNavigationLabel(): string
    {
        return 'Officer Dashboard V2';
    }

    public static function getNavigationIcon(): string | Htmlable | null
    {
        return 'heroicon-o-clipboard-document-check';
    }

    public static function canAccess(): bool
    {
        $user = auth()->user();

        if (!$user) {
            return false;
        }

        $role = $user->role;
        if ($role instanceof UserRole) {
            return $role === UserRole::OFFICER;
        }

        return (string) $role === UserRole::OFFICER->value;
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    public static function canView(): bool
    {
        return static::canAccess();
    }

    public function getTitle(): string
    {
        return 'Officer Dashboard';
    }

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);

        // Permanent architecture note:
        // Filament dashboard widgets are Livewire components. Stacking many widgets can produce
        // nested component trees that are prone to multiple-root detection errors.
        // This page uses only server-side queries + static Blade sections to guarantee
        // one deterministic root container and avoid widget-driven Livewire nesting.
        $this->activeRidesCount = $this->resolveActiveRidesCount();
        $this->pendingBookingsCount = $this->resolvePendingBookingsCount();
        $this->openTicketsCount = $this->resolveOpenTicketsCount();
        $this->onlineDriversCount = $this->resolveOnlineDriversCount();
        $this->overdueBookingsCount = $this->resolveOverdueBookingsCount();
        $this->highPriorityTicketsCount = $this->resolveHighPriorityTicketsCount();
        $this->cancelledRidesTodayCount = $this->resolveCancelledRidesTodayCount();
        $this->recentBookings = $this->resolveRecentBookings();
        $this->recentTickets = $this->resolveRecentTickets();
        $this->escalationTickets = $this->resolveEscalationTickets();
        $this->unassignedRides = $this->resolveUnassignedRides();
    }

    private function resolveActiveRidesCount(): int
    {
        if (! Schema::hasTable('rides') || ! Schema::hasColumn('rides', 'status')) {
            return 0;
        }

        return (int) DB::table('rides')
            ->whereIn('status', ['in_progress', 'IN_PROGRESS', 'accepted', 'ACCEPTED'])
            ->count();
    }

    private function resolvePendingBookingsCount(): int
    {
        if (! Schema::hasTable('bookings') || ! Schema::hasColumn('bookings', 'status')) {
            return 0;
        }

        return (int) DB::table('bookings')
            ->whereIn('status', ['pending', 'PENDING', 'confirmed', 'CONFIRMED'])
            ->count();
    }

    private function resolveOpenTicketsCount(): int
    {
        if (! Schema::hasTable('tickets') || ! Schema::hasColumn('tickets', 'status')) {
            return 0;
        }

        return (int) DB::table('tickets')
            ->whereIn('status', ['open', 'OPEN', 'pending', 'PENDING'])
            ->count();
    }

    private function resolveOnlineDriversCount(): int
    {
        if (! Schema::hasTable('drivers')) {
            return 0;
        }

        if (Schema::hasColumn('drivers', 'is_online')) {
            return (int) DB::table('drivers')->where('is_online', true)->count();
        }

        if (Schema::hasColumn('drivers', 'status')) {
            return (int) DB::table('drivers')
                ->whereIn('status', ['active', 'ACTIVE', 'approved', 'APPROVED'])
                ->count();
        }

        return 0;
    }

    private function resolveOverdueBookingsCount(): int
    {
        if (! Schema::hasTable('bookings') || ! Schema::hasColumn('bookings', 'status') || ! Schema::hasColumn('bookings', 'created_at')) {
            return 0;
        }

        return (int) DB::table('bookings')
            ->whereIn('status', ['pending', 'PENDING', 'confirmed', 'CONFIRMED'])
            ->where('created_at', '<=', now()->subMinutes(15))
            ->count();
    }

    private function resolveHighPriorityTicketsCount(): int
    {
        if (! Schema::hasTable('tickets') || ! Schema::hasColumn('tickets', 'status') || ! Schema::hasColumn('tickets', 'priority')) {
            return 0;
        }

        return (int) DB::table('tickets')
            ->whereIn('status', ['open', 'OPEN', 'pending', 'PENDING'])
            ->whereIn('priority', ['high', 'HIGH', 'urgent', 'URGENT'])
            ->count();
    }

    private function resolveCancelledRidesTodayCount(): int
    {
        if (! Schema::hasTable('rides') || ! Schema::hasColumn('rides', 'status')) {
            return 0;
        }

        $query = DB::table('rides')
            ->whereIn('status', ['cancelled', 'CANCELLED']);

        if (Schema::hasColumn('rides', 'updated_at')) {
            $query->whereDate('updated_at', now()->toDateString());
        } elseif (Schema::hasColumn('rides', 'created_at')) {
            $query->whereDate('created_at', now()->toDateString());
        }

        return (int) $query->count();
    }

    /** @return array<int, array<string, mixed>> */
    private function resolveRecentBookings(): array
    {
        if (! Schema::hasTable('bookings')) {
            return [];
        }

        $columns = collect(['id', 'status', 'created_at'])
            ->filter(fn (string $column): bool => Schema::hasColumn('bookings', $column))
            ->values()
            ->all();

        if ($columns === []) {
            return [];
        }

        return DB::table('bookings')
            ->select($columns)
            ->latest('id')
            ->limit(8)
            ->get()
            ->map(fn ($row): array => (array) $row)
            ->all();
    }

    /** @return array<int, array<string, mixed>> */
    private function resolveRecentTickets(): array
    {
        if (! Schema::hasTable('tickets')) {
            return [];
        }

        $columns = collect(['id', 'status', 'priority', 'created_at'])
            ->filter(fn (string $column): bool => Schema::hasColumn('tickets', $column))
            ->values()
            ->all();

        if ($columns === []) {
            return [];
        }

        return DB::table('tickets')
            ->select($columns)
            ->latest('id')
            ->limit(8)
            ->get()
            ->map(fn ($row): array => (array) $row)
            ->all();
    }

    /** @return array<int, array<string, mixed>> */
    private function resolveEscalationTickets(): array
    {
        if (! Schema::hasTable('tickets') || ! Schema::hasColumn('tickets', 'status')) {
            return [];
        }

        $columns = collect(['id', 'status', 'priority', 'created_at'])
            ->filter(fn (string $column): bool => Schema::hasColumn('tickets', $column))
            ->values()
            ->all();

        if ($columns === []) {
            return [];
        }

        $query = DB::table('tickets')
            ->select($columns)
            ->whereIn('status', ['open', 'OPEN', 'pending', 'PENDING']);

        if (Schema::hasColumn('tickets', 'priority')) {
            $query->whereIn('priority', ['high', 'HIGH', 'urgent', 'URGENT']);
        }

        return $query
            ->latest('id')
            ->limit(8)
            ->get()
            ->map(fn ($row): array => (array) $row)
            ->all();
    }

    /** @return array<int, array<string, mixed>> */
    private function resolveUnassignedRides(): array
    {
        if (! Schema::hasTable('rides') || ! Schema::hasColumn('rides', 'status')) {
            return [];
        }

        $columns = collect(['id', 'status', 'driver_id', 'origin_address', 'destination_address', 'created_at'])
            ->filter(fn (string $column): bool => Schema::hasColumn('rides', $column))
            ->values()
            ->all();

        if ($columns === []) {
            return [];
        }

        $query = DB::table('rides')
            ->select($columns)
            ->whereIn('status', ['pending', 'PENDING', 'confirmed', 'CONFIRMED']);

        if (Schema::hasColumn('rides', 'driver_id')) {
            $query->whereNull('driver_id');
        }

        return $query
            ->latest('id')
            ->limit(8)
            ->get()
            ->map(fn ($row): array => (array) $row)
            ->all();
    }

}
