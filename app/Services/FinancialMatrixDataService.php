<?php

namespace App\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class FinancialMatrixDataService
{
    /**
     * @return array<string, mixed>
     */
    public function snapshot(string $fromDate, string $toDate): array
    {
        [$paymentsTable, $amountColumn] = $this->resolvePaymentsSource();

        $grossRange = 0.0;
        $gross7d = 0.0;
        $commissionRange = 0.0;
        $payoutsRange = 0.0;
        $pendingPayouts = 0;
        $dailyRows = [];

        if ($paymentsTable && $amountColumn) {
            $grossRange = (float) DB::table($paymentsTable)
                ->whereDate('created_at', '>=', $fromDate)
                ->whereDate('created_at', '<=', $toDate)
                ->sum($amountColumn);

            $gross7d = (float) DB::table($paymentsTable)
                ->whereDate('created_at', '>=', Carbon::parse($toDate)->subDays(6)->toDateString())
                ->whereDate('created_at', '<=', $toDate)
                ->sum($amountColumn);

            $dailyRows = DB::table($paymentsTable)
                ->selectRaw('DATE(created_at) as day, SUM(' . $amountColumn . ') as gross, COUNT(*) as txns')
                ->whereDate('created_at', '>=', $fromDate)
                ->whereDate('created_at', '<=', $toDate)
                ->groupBy('day')
                ->orderBy('day')
                ->get()
                ->map(fn ($row) => [
                    'day' => (string) $row->day,
                    'gross' => (float) $row->gross,
                    'txns' => (int) $row->txns,
                ])
                ->all();

            if (Schema::hasColumn($paymentsTable, 'platform_fee')) {
                $commissionRange = (float) DB::table($paymentsTable)
                    ->whereDate('created_at', '>=', $fromDate)
                    ->whereDate('created_at', '<=', $toDate)
                    ->sum('platform_fee');
            } elseif (Schema::hasTable('platform_commissions') && Schema::hasColumn('platform_commissions', 'commission_amount')) {
                $commissionRange = (float) DB::table('platform_commissions')
                    ->whereDate('created_at', '>=', $fromDate)
                    ->whereDate('created_at', '<=', $toDate)
                    ->sum('commission_amount');
            }
        }

        if (Schema::hasTable('driver_payouts')) {
            $amountField = Schema::hasColumn('driver_payouts', 'net_payout')
                ? 'net_payout'
                : (Schema::hasColumn('driver_payouts', 'amount') ? 'amount' : null);

            if ($amountField) {
                $payoutsRange = (float) DB::table('driver_payouts')
                    ->whereDate('created_at', '>=', $fromDate)
                    ->whereDate('created_at', '<=', $toDate)
                    ->sum($amountField);
            }

            if (Schema::hasColumn('driver_payouts', 'status')) {
                $pendingPayouts = (int) DB::table('driver_payouts')
                    ->whereIn('status', ['pending', 'PENDING', 'processing', 'PROCESSING'])
                    ->count();
            }
        }

        $takeRate = $grossRange > 0 ? round(($commissionRange / $grossRange) * 100, 2) : 0.0;

        return [
            'matrix' => [
                'gross_range' => $grossRange,
                'gross_7d' => $gross7d,
                'commission_range' => $commissionRange,
                'payouts_range' => $payoutsRange,
                'pending_payouts' => $pendingPayouts,
                'take_rate' => $takeRate,
            ],
            'daily_rows' => $dailyRows,
            'period' => [
                'from' => $fromDate,
                'to' => $toDate,
            ],
        ];
    }

    private function resolvePaymentsSource(): array
    {
        foreach (['payments_v2', 'payments'] as $table) {
            if (!Schema::hasTable($table)) {
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
}
