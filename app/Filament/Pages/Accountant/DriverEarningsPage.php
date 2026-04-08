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

    public string $currencyCode = 'RWF';

    public bool $isFallbackData = false;

    public string $dataSourceLabel = 'driver payouts';

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
        $this->isFallbackData = false;
        $this->dataSourceLabel = 'driver payouts';

        if (! Schema::hasTable('driver_payouts') || ! Schema::hasColumn('driver_payouts', 'driver_id')) {
            $this->loadFromTripsFallback();

            return;
        }

        $candidateColumns = [
            'driver_id',
            'total_income',
            'amount',
            'payout_amount',
            'commission_amount',
            'commission_deducted',
            'commission',
            'status',
            'created_at',
        ];

        $selectColumns = collect($candidateColumns)
            ->filter(fn (string $column): bool => Schema::hasColumn('driver_payouts', $column))
            ->values()
            ->all();

        $rows = DB::table('driver_payouts')
            ->select($selectColumns)
            ->limit(10000)
            ->get();

        if ($rows->isEmpty()) {
            $this->loadFromTripsFallback();

            return;
        }

        $driverNameMap = $this->resolveDriverNames(
            $rows->pluck('driver_id')->filter()->map(fn ($id) => (int) $id)->unique()->values()->all()
        );

        $driverData = $rows
            ->groupBy('driver_id')
            ->map(function ($group, $driverId) use ($driverNameMap): array {
                $gross = (float) $group->sum(function ($row): float {
                    $row = (array) $row;

                    return (float) ($row['total_income'] ?? $row['amount'] ?? $row['payout_amount'] ?? 0);
                });

                $commission = (float) $group->sum(function ($row): float {
                    $row = (array) $row;

                    return (float) ($row['commission_amount'] ?? $row['commission_deducted'] ?? $row['commission'] ?? 0);
                });

                $payout = (float) $group->sum(function ($row): float {
                    $row = (array) $row;

                    return (float) ($row['payout_amount'] ?? $row['amount'] ?? 0);
                });

                $net = $payout > 0 ? $payout : max(0.0, $gross - $commission);

                $latest = $group->sortByDesc(fn ($row) => (string) ((array) $row)['created_at'] ?? '')->first();
                $latest = is_object($latest) ? (array) $latest : (array) ($latest ?? []);

                return [
                    'driver_id' => (int) $driverId,
                    'driver_name' => $driverNameMap[(int) $driverId] ?? 'Driver #'.(int) $driverId,
                    'gross_amount' => $gross,
                    'amount' => $payout,
                    'commission_deducted' => $commission,
                    'net_earnings' => $net,
                    'status' => (string) ($latest['status'] ?? 'pending'),
                    'payout_count' => (int) $group->count(),
                ];
            })
            ->sortByDesc('amount')
            ->values()
            ->all();

        $this->driverEarnings = array_slice($driverData, 0, 50);
        $this->totalDriverCount = count($driverData);
        $this->totalPaidOut = (float) array_sum(array_column($driverData, 'amount'));
        $this->totalCommissionEarned = (float) array_sum(array_column($driverData, 'commission_deducted'));
    }

    private function loadFromTripsFallback(): void
    {
        $this->isFallbackData = true;
        $this->dataSourceLabel = 'completed trips + platform commissions';

        if (! Schema::hasTable('trips') || ! Schema::hasColumn('trips', 'driver_id')) {
            return;
        }

        $select = ['driver_id'];

        if (Schema::hasColumn('trips', 'actual_fare')) {
            $select[] = 'actual_fare';
        }

        if (Schema::hasColumn('trips', 'fare')) {
            $select[] = 'fare';
        }

        if (Schema::hasColumn('trips', 'status')) {
            $select[] = 'status';
        }

        $tripRows = DB::table('trips')
            ->select($select)
            ->whereNotNull('driver_id')
            ->when(Schema::hasColumn('trips', 'status'), function ($query) {
                $query->whereRaw('LOWER(CAST(status AS TEXT)) = ?', ['completed']);
            })
            ->limit(100000)
            ->get();

        if ($tripRows->isEmpty()) {
            return;
        }

        $driverNameMap = $this->resolveDriverNames(
            $tripRows->pluck('driver_id')->filter()->map(fn ($id) => (int) $id)->unique()->values()->all()
        );
        $commissionMap = $this->resolveTripFallbackCommissions(
            $tripRows->pluck('driver_id')->filter()->map(fn ($id) => (int) $id)->unique()->values()->all()
        );

        $driverData = $tripRows
            ->groupBy('driver_id')
            ->map(function ($group, $driverId) use ($driverNameMap, $commissionMap): array {
                $gross = (float) $group->sum(function ($row): float {
                    $row = (array) $row;

                    return (float) ($row['actual_fare'] ?? $row['fare'] ?? 0);
                });

                $commission = (float) ($commissionMap[(int) $driverId] ?? 0.0);
                $commission = min($commission, $gross);
                $net = max(0.0, $gross - $commission);

                return [
                    'driver_id' => (int) $driverId,
                    'driver_name' => $driverNameMap[(int) $driverId] ?? 'Driver #'.(int) $driverId,
                    'gross_amount' => $gross,
                    'amount' => $net,
                    'commission_deducted' => $commission,
                    'net_earnings' => $net,
                    'status' => 'completed',
                    'payout_count' => (int) $group->count(),
                ];
            })
            ->sortByDesc('amount')
            ->values()
            ->all();

        $this->driverEarnings = array_slice($driverData, 0, 50);
        $this->totalDriverCount = count($driverData);
        $this->totalPaidOut = (float) array_sum(array_column($driverData, 'amount'));
        $this->totalCommissionEarned = (float) array_sum(array_column($driverData, 'commission_deducted'));
    }

    /**
     * @param array<int, int> $driverIds
     * @return array<int, float>
     */
    private function resolveTripFallbackCommissions(array $driverIds): array
    {
        if ($driverIds === []) {
            return [];
        }

        if (! Schema::hasTable('platform_commissions')
            || ! Schema::hasColumn('platform_commissions', 'driver_id')
            || ! Schema::hasColumn('platform_commissions', 'commission_amount')) {
            return [];
        }

        $rows = DB::table('platform_commissions')
            ->select(['driver_id', DB::raw('SUM(commission_amount) as total_commission')])
            ->whereIn('driver_id', $driverIds)
            ->groupBy('driver_id')
            ->get();

        return $rows
            ->mapWithKeys(fn ($row): array => [(int) $row->driver_id => (float) ($row->total_commission ?? 0)])
            ->all();
    }

    /**
     * @param array<int, int> $driverIds
     * @return array<int, string>
     */
    private function resolveDriverNames(array $driverIds): array
    {
        if ($driverIds === []) {
            return [];
        }

        if (! Schema::hasTable('drivers')) {
            return [];
        }

        $query = DB::table('drivers')->whereIn('drivers.id', $driverIds);

        if (Schema::hasTable('users') && Schema::hasColumn('drivers', 'user_id') && Schema::hasColumn('users', 'name')) {
            $rows = $query
                ->leftJoin('users', 'users.id', '=', 'drivers.user_id')
                ->select(['drivers.id as driver_id', 'users.name as driver_name'])
                ->get();

            return $rows
                ->mapWithKeys(fn ($row): array => [(int) $row->driver_id => (string) ($row->driver_name ?? 'Driver #'.$row->driver_id)])
                ->all();
        }

        return [];
    }
}
