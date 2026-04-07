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

    /** @var array<int, array<string, mixed>> */
    public array $recentBookings = [];

    /** @var array<int, array<string, mixed>> */
    public array $recentTickets = [];

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
        $this->recentBookings = $this->resolveRecentBookings();
        $this->recentTickets = $this->resolveRecentTickets();
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

}
