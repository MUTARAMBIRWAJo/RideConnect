<?php

namespace App\Filament\Pages\Accountant;

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
        return auth()->check() && (auth()->user()->hasRole('accountant') || auth()->user()->hasRole('ACCOUNTANT'));
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

        $columns = collect(['driver_id', 'amount', 'commission_deducted', 'status', 'created_at'])
            ->filter(fn (string $column): bool => Schema::hasColumn('driver_payouts', $column))
            ->values()
            ->all();

        if ($columns === []) {
            return;
        }

        $driverData = DB::table('driver_payouts')
            ->select($columns)
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
