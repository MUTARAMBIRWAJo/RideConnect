<?php

namespace App\Filament\Pages;

use App\Enums\UserRole;
use App\Filament\Pages\Concerns\HandlesRoleDashboards;
use App\Filament\Widgets\Dashboard\AccountantRevenueSummary;
use App\Filament\Widgets\Dashboard\CommissionOverviewWidget;
use App\Filament\Widgets\Dashboard\DailyCommissionChartWidget;
use App\Filament\Widgets\Dashboard\EscrowBalanceWidget;
use App\Filament\Widgets\Dashboard\FinanceExportActionsWidget;
use App\Filament\Widgets\Dashboard\FraudAlertsWidget;
use App\Filament\Widgets\Dashboard\MonthlyEarningsChartWidget;
use App\Filament\Widgets\Dashboard\TransactionsTableWidget;
use Illuminate\Contracts\Support\Htmlable;

class AccountantDashboard extends \Filament\Pages\Dashboard
{
    use HandlesRoleDashboards;

    protected static string $routePath = '/accountant-dashboard';

    public static function getNavigationLabel(): string
    {
        return 'Accountant Dashboard';
    }

    public static function getNavigationIcon(): string | Htmlable | null
    {
        return 'heroicon-o-banknotes';
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return static::userHasRole($user, 'Accountant', UserRole::ACCOUNTANT);
    }

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);
    }

    public function getWidgets(): array
    {
        return [
            EscrowBalanceWidget::class,
            AccountantRevenueSummary::class,
            CommissionOverviewWidget::class,
            FraudAlertsWidget::class,
            DailyCommissionChartWidget::class,
            FinanceExportActionsWidget::class,
            MonthlyEarningsChartWidget::class,
            TransactionsTableWidget::class,
        ];
    }

    public function getColumns(): int | string | array
    {
        return [
            'md' => 2,
            'xl' => 3,
        ];
    }
}
