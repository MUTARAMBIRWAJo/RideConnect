<?php

namespace App\Filament\Pages;

use App\Enums\UserRole;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AccountantDashboard extends Page
{
    protected static ?string $navigationGroup = 'Dashboards';

    protected static string $routePath = '/accountant-dashboard';

    protected static string $view = 'filament.pages.accountant-dashboard-static';

    public float $totalRevenue = 0.0;

    public float $monthlyRevenue = 0.0;

    public int $successfulPayments24h = 0;

    public int $failedPayments24h = 0;

    public int $pendingPayouts = 0;

    /** @var array<int, array<string, mixed>> */
    public array $recentPayments = [];

    public static function getNavigationLabel(): string
    {
        return 'Accountant Dashboard';
    }

    public static function getNavigationIcon(): string | Htmlable | null
    {
        return 'heroicon-o-banknotes';
    }

    public static function canAccess(): bool
    {
        $user = auth()->user();

        if (!$user) {
            return false;
        }

        $role = $user->role;
        if ($role instanceof UserRole) {
            return $role === UserRole::ACCOUNTANT;
        }

        return (string) $role === UserRole::ACCOUNTANT->value;
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    public function getTitle(): string
    {
        return 'Accountant Dashboard';
    }

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);

        // Permanent architecture note:
        // Removing dashboard widgets removes nested Livewire widget trees from this page.
        // This page renders data via standard server-side queries in a static Blade layout,
        // keeping DOM structure predictable and single-root.
        [$table, $amountColumn] = $this->resolvePaymentsSource();

        if (! $table || ! $amountColumn) {
            return;
        }

        $this->totalRevenue = (float) DB::table($table)->sum($amountColumn);
        $this->monthlyRevenue = (float) DB::table($table)
            ->whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->sum($amountColumn);

        $base24h = DB::table($table)->where('created_at', '>=', now()->subDay());
        $this->successfulPayments24h = (int) (clone $base24h)
            ->whereIn('status', ['successful', 'SUCCESSFUL', 'paid', 'PAID', 'completed', 'COMPLETED'])
            ->count();
        $this->failedPayments24h = (int) (clone $base24h)
            ->whereIn('status', ['failed', 'FAILED', 'declined', 'DECLINED'])
            ->count();

        if (Schema::hasTable('driver_payouts') && Schema::hasColumn('driver_payouts', 'status')) {
            $this->pendingPayouts = (int) DB::table('driver_payouts')
                ->whereIn('status', ['pending', 'PENDING'])
                ->count();
        }

        $this->recentPayments = $this->resolveRecentPayments($table, $amountColumn);
    }

    /** @return array{0: string|null, 1: string|null} */
    private function resolvePaymentsSource(): array
    {
        foreach (['payments_v2', 'payments'] as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            if (! Schema::hasColumn($table, 'status') || ! Schema::hasColumn($table, 'created_at')) {
                continue;
            }

            foreach (['amount', 'total_amount', 'fare_amount'] as $column) {
                if (Schema::hasColumn($table, $column)) {
                    return [$table, $column];
                }
            }
        }

        return [null, null];
    }

    /** @return array<int, array<string, mixed>> */
    private function resolveRecentPayments(string $table, string $amountColumn): array
    {
        $columns = collect(['id', 'status', 'created_at'])
            ->push($amountColumn)
            ->filter(fn (string $column): bool => Schema::hasColumn($table, $column))
            ->values()
            ->all();

        if ($columns === []) {
            return [];
        }

        return DB::table($table)
            ->select($columns)
            ->latest('id')
            ->limit(10)
            ->get()
            ->map(fn ($row): array => (array) $row)
            ->all();
    }

}
