<?php

namespace App\Filament\Pages\Accountant;

use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class FinancialDashboard extends Page
{
    protected static ?string $navigationGroup = 'Dashboard';

    protected static string $view = 'filament.pages.accountant.dashboard';

    public float $totalRevenue = 0.0;

    public float $monthlyRevenue = 0.0;

    public int $successfulPayments24h = 0;

    public int $failedPayments24h = 0;

    public int $pendingPayouts = 0;

    public float $pendingPayoutAmount = 0.0;

    public float $commissionToday = 0.0;

    public int $paymentRetryQueueCount = 0;

    /** @var array<int, array<string, mixed>> */
    public array $recentPayments = [];

    /** @var array<int, array<string, mixed>> */
    public array $failedPayments = [];

    /** @var array<int, array<string, mixed>> */
    public array $pendingPayoutRows = [];

    public static function getNavigationLabel(): string
    {
        return 'Dashboard';
    }

    public static function getNavigationIcon(): string | Htmlable | null
    {
        return 'heroicon-o-home';
    }

    public static function canAccess(): bool
    {
        return auth()->check() && (auth()->user()->hasRole('accountant') || auth()->user()->hasRole('ACCOUNTANT'));
    }

    public function getTitle(): string
    {
        return 'Financial Dashboard';
    }

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);

        $this->refreshDashboardData();
    }

    public function retryPayment(int $paymentId): void
    {
        if (!auth()->user()->can('manage finances')) {
            abort(403);
        }

        if (!Schema::hasTable('payments')) {
            return;
        }

        $update = [];
        if (Schema::hasColumn('payments', 'retry_count')) {
            $current = (int) DB::table('payments')->where('id', $paymentId)->value('retry_count');
            $update['retry_count'] = $current + 1;
        }
        if (Schema::hasColumn('payments', 'status')) {
            $update['status'] = 'processing';
        }
        if (Schema::hasColumn('payments', 'updated_at')) {
            $update['updated_at'] = now();
        }

        if ($update !== []) {
            DB::table('payments')->where('id', $paymentId)->update($update);
        }

        $this->refreshDashboardData();

        Notification::make()
            ->title('Payment retry queued')
            ->success()
            ->send();
    }

    public function approvePayout(int $payoutId): void
    {
        if (!auth()->user()->can('manage finances')) {
            abort(403);
        }

        if (!Schema::hasTable('driver_payouts')) {
            return;
        }

        $update = [];
        if (Schema::hasColumn('driver_payouts', 'status')) {
            $update['status'] = 'approved';
        }
        if (Schema::hasColumn('driver_payouts', 'updated_at')) {
            $update['updated_at'] = now();
        }

        if ($update !== []) {
            DB::table('driver_payouts')->where('id', $payoutId)->update($update);
        }

        $this->refreshDashboardData();

        Notification::make()
            ->title('Payout approved successfully')
            ->success()
            ->send();
    }

    private function refreshDashboardData(): void
    {

        $this->totalRevenue = $this->resolveTotalRevenue();
        $this->monthlyRevenue = $this->resolveMonthlyRevenue();
        $this->successfulPayments24h = $this->resolveSuccessfulPayments24h();
        $this->failedPayments24h = $this->resolveFailedPayments24h();
        $this->pendingPayouts = $this->resolvePendingPayouts();
        $this->pendingPayoutAmount = $this->resolvePendingPayoutAmount();
        $this->commissionToday = $this->resolveCommissionToday();
        $this->paymentRetryQueueCount = $this->resolvePaymentRetryQueueCount();
        $this->recentPayments = $this->resolveRecentPayments();
        $this->failedPayments = $this->resolveFailedPayments();
        $this->pendingPayoutRows = $this->resolvePendingPayoutRows();
    }

    private function resolveTotalRevenue(): float
    {
        if (!Schema::hasTable('payments') || !Schema::hasColumn('payments', 'amount')) {
            return 0.0;
        }

        return (float) (DB::table('payments')
            ->where('status', 'completed')
            ->orWhere('status', 'COMPLETED')
            ->sum('amount') ?? 0);
    }

    private function resolveMonthlyRevenue(): float
    {
        if (!Schema::hasTable('payments') || !Schema::hasColumn('payments', 'amount')) {
            return 0.0;
        }

        return (float) (DB::table('payments')
            ->where('status', 'completed')
            ->orWhere('status', 'COMPLETED')
            ->whereYear('created_at', now()->year)
            ->whereMonth('created_at', now()->month)
            ->sum('amount') ?? 0);
    }

    private function resolveSuccessfulPayments24h(): int
    {
        if (!Schema::hasTable('payments') || !Schema::hasColumn('payments', 'status')) {
            return 0;
        }

        return (int) DB::table('payments')
            ->where('status', 'completed')
            ->orWhere('status', 'COMPLETED')
            ->where('created_at', '>=', now()->subHours(24))
            ->count();
    }

    private function resolveFailedPayments24h(): int
    {
        if (!Schema::hasTable('payments') || !Schema::hasColumn('payments', 'status')) {
            return 0;
        }

        return (int) DB::table('payments')
            ->whereIn('status', ['failed', 'FAILED', 'declined', 'DECLINED'])
            ->where('created_at', '>=', now()->subHours(24))
            ->count();
    }

    private function resolvePendingPayouts(): int
    {
        if (!Schema::hasTable('driver_payouts') || !Schema::hasColumn('driver_payouts', 'status')) {
            return 0;
        }

        return (int) DB::table('driver_payouts')
            ->whereIn('status', ['pending', 'PENDING'])
            ->count();
    }

    private function resolvePendingPayoutAmount(): float
    {
        if (!Schema::hasTable('driver_payouts') || !Schema::hasColumn('driver_payouts', 'amount')) {
            return 0.0;
        }

        return (float) (DB::table('driver_payouts')
            ->whereIn('status', ['pending', 'PENDING'])
            ->sum('amount') ?? 0);
    }

    private function resolveCommissionToday(): float
    {
        if (!Schema::hasTable('payments') || !Schema::hasColumn('payments', 'commission_amount')) {
            return 0.0;
        }

        return (float) (DB::table('payments')
            ->whereDate('created_at', now()->toDateString())
            ->sum('commission_amount') ?? 0);
    }

    private function resolvePaymentRetryQueueCount(): int
    {
        if (!Schema::hasTable('payments') || !Schema::hasColumn('payments', 'retry_count')) {
            return 0;
        }

        return (int) DB::table('payments')
            ->whereIn('status', ['failed', 'FAILED', 'declined', 'DECLINED'])
            ->where('retry_count', '<', 3)
            ->count();
    }

    /** @return array<int, array<string, mixed>> */
    private function resolveRecentPayments(): array
    {
        if (!Schema::hasTable('payments')) {
            return [];
        }

        $columns = collect(['id', 'amount', 'status', 'created_at'])
            ->filter(fn (string $column): bool => Schema::hasColumn('payments', $column))
            ->values()
            ->all();

        if ($columns === []) {
            return [];
        }

        return DB::table('payments')
            ->select($columns)
            ->latest('id')
            ->limit(10)
            ->get()
            ->map(fn ($row): array => (array) $row)
            ->all();
    }

    /** @return array<int, array<string, mixed>> */
    private function resolveFailedPayments(): array
    {
        if (!Schema::hasTable('payments') || !Schema::hasColumn('payments', 'status')) {
            return [];
        }

        $columns = collect(['id', 'amount', 'status', 'created_at', 'retry_count'])
            ->filter(fn (string $column): bool => Schema::hasColumn('payments', $column))
            ->values()
            ->all();

        if ($columns === []) {
            return [];
        }

        return DB::table('payments')
            ->select($columns)
            ->whereIn('status', ['failed', 'FAILED', 'declined', 'DECLINED'])
            ->latest('id')
            ->limit(10)
            ->get()
            ->map(fn ($row): array => (array) $row)
            ->all();
    }

    /** @return array<int, array<string, mixed>> */
    private function resolvePendingPayoutRows(): array
    {
        if (!Schema::hasTable('driver_payouts')) {
            return [];
        }

        $columns = collect(['id', 'driver_id', 'amount', 'status', 'created_at'])
            ->filter(fn (string $column): bool => Schema::hasColumn('driver_payouts', $column))
            ->values()
            ->all();

        if ($columns === []) {
            return [];
        }

        return DB::table('driver_payouts')
            ->select($columns)
            ->whereIn('status', ['pending', 'PENDING'])
            ->latest('id')
            ->limit(10)
            ->get()
            ->map(fn ($row): array => (array) $row)
            ->all();
    }
}
