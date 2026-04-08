<?php

namespace App\Filament\Pages\Accountant;

use App\Enums\UserRole;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DriverEarningsPage extends Page
{
    protected static ?string $navigationGroup = 'Financial Operations';

    protected static string $view = 'filament.pages.accountant.driver-earnings';

    /** @var array<int, array<string, mixed>> */
    public array $driverEarnings = [];

    public int $totalDriverCount = 0;

    public float $totalPaidOut = 0.0;

    public float $totalCommissionEarned = 0.0;

    public static function getNavigationLabel(): string
    {
        return 'Driver Earnings';
    }

    public static function getNavigationIcon(): string | Htmlable | null
    {
        return 'heroicon-o-wallet';
    }

    public static function canAccess(): bool
    {
        $user = auth()->user();

        if (! $user) {
            return false;
        }

        return ($user->role === UserRole::ACCOUNTANT)
            || $user->hasAnyRole(['Accountant', 'accountant', 'ACCOUNTANT']);
    }

    public function getTitle(): string
    {
        return 'Driver Earnings & Commission';
    }

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);
        $this->loadDriverEarnings();
    }

    private function loadDriverEarnings(): void
    {
        if (!Schema::hasTable('driver_payouts')) {
            return;
        }

        if (!Schema::hasColumn('driver_payouts', 'driver_id')) {
            return;
        }

        $query = DB::table('driver_payouts')->select(['driver_id']);

        if (Schema::hasColumn('driver_payouts', 'amount')) {
            $query->addSelect(DB::raw('SUM(COALESCE(amount, 0)) as amount'));
        } else {
            $query->addSelect(DB::raw('0 as amount'));
        }

        if (Schema::hasColumn('driver_payouts', 'commission_deducted')) {
            $query->addSelect(DB::raw('SUM(COALESCE(commission_deducted, 0)) as commission_deducted'));
        } else {
            $query->addSelect(DB::raw('0 as commission_deducted'));
        }

        if (Schema::hasColumn('driver_payouts', 'created_at')) {
            $query->addSelect(DB::raw('MAX(created_at) as last_payout_at'));
        }

        if (Schema::hasColumn('driver_payouts', 'status')) {
            $query->addSelect(DB::raw('MAX(status) as status'));
        }

        $query->addSelect(DB::raw('COUNT(*) as payout_count'));

        $driverData = $query
            ->groupBy('driver_id')
            ->get()
            ->map(function ($row): array {
                $row = (array) $row;
                $row['net_earnings'] = ($row['amount'] ?? 0) - ($row['commission_deducted'] ?? 0);
                return $row;
            })
            ->sortByDesc('amount')
            ->values()
            ->all();

        $this->driverEarnings = array_slice($driverData, 0, 50);
        $this->totalDriverCount = count($driverData);
        $this->totalPaidOut = (float) array_sum(array_column($this->driverEarnings, 'amount'));
        $this->totalCommissionEarned = (float) array_sum(array_column($this->driverEarnings, 'commission_deducted'));
    }
}
