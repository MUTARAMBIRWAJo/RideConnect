<?php

namespace App\Modules\Reporting\Repositories;

use App\Modules\Reporting\Contracts\ReportingRepositoryInterface;
use Illuminate\Support\Facades\DB;

class ReportingRepository implements ReportingRepositoryInterface
{
    public function getDailyRevenueSummary(string $date): array
    {
        $row = DB::table('mv_daily_revenue')
            ->where('date_key', $date)
            ->first();

        if (! $row) return $this->emptyRevenueSummary($date);

        return [
            'date'               => $date,
            'total_gross'        => (float) $row->total_gross,
            'total_commission'   => (float) $row->total_commission,
            'total_driver_payout'=> (float) $row->total_driver_payout,
            'total_tax'          => (float) $row->total_tax,
            'total_net_revenue'  => (float) $row->total_net_revenue,
            'transaction_count'  => (int)   $row->transaction_count,
            'currency'           => 'RWF',
        ];
    }

    public function getDriverEarningsSummary(string $from, string $to): array
    {
        return DB::table('dw_fact_driver_earnings as e')
            ->join('dw_dim_driver as d', 'd.id', '=', 'e.driver_dim_id')
            ->where('d.is_current', true)
            ->whereBetween('e.date_key', [$from, $to])
            ->selectRaw('d.driver_name, d.region, SUM(e.total_rides) as rides, SUM(e.gross_earnings) as gross, SUM(e.net_payout) as net, SUM(e.tax_withheld) as tax')
            ->groupBy('d.driver_name', 'd.region')
            ->orderByDesc('gross')
            ->get()
            ->map(fn ($r) => (array) $r)
            ->all();
    }

    public function getCommissionBreakdown(string $from, string $to): array
    {
        return DB::table('dw_fact_commissions as c')
            ->whereBetween('c.date_key', [$from, $to])
            ->selectRaw('c.date_key, SUM(c.total_commission) as commission, SUM(c.tax_on_commission) as tax, SUM(c.net_commission) as net, SUM(c.transaction_count) as txn_count')
            ->groupBy('c.date_key')
            ->orderBy('c.date_key')
            ->get()
            ->map(fn ($r) => (array) $r)
            ->all();
    }

    public function getTaxPayableSummary(string $from, string $to): array
    {
        return DB::table('ledger_entries as e')
            ->join('ledger_accounts as a', 'a.id', '=', 'e.account_id')
            ->where('a.name', 'Tax Payable')
            ->whereBetween('e.created_at', [$from . ' 00:00:00', $to . ' 23:59:59'])
            ->selectRaw("DATE(e.created_at) as date, SUM(e.credit) as tax_collected, e.reference_type")
            ->groupByRaw("DATE(e.created_at), e.reference_type")
            ->orderByRaw('DATE(e.created_at)')
            ->get()
            ->map(fn ($r) => (array) $r)
            ->all();
    }

    public function getRefundReport(string $from, string $to): array
    {
        return DB::table('payments')
            ->where('status', 'refunded')
            ->whereBetween('updated_at', [$from . ' 00:00:00', $to . ' 23:59:59'])
            ->selectRaw("DATE(updated_at) as date, COUNT(*) as count, SUM(amount) as total_refunded")
            ->groupByRaw('DATE(updated_at)')
            ->orderBy('date')
            ->get()
            ->map(fn ($r) => (array) $r)
            ->all();
    }

    public function getFraudIncidentReport(string $from, string $to): array
    {
        return DB::table('fraud_flags')
            ->whereBetween('created_at', [$from . ' 00:00:00', $to . ' 23:59:59'])
            ->selectRaw("severity, COUNT(*) as count, SUM(CASE WHEN resolved THEN 1 ELSE 0 END) as resolved_count")
            ->groupBy('severity')
            ->orderByRaw("CASE severity WHEN 'high' THEN 1 WHEN 'medium' THEN 2 ELSE 3 END")
            ->get()
            ->map(fn ($r) => (array) $r)
            ->all();
    }

    private function emptyRevenueSummary(string $date): array
    {
        return [
            'date'                => $date,
            'total_gross'         => 0.0,
            'total_commission'    => 0.0,
            'total_driver_payout' => 0.0,
            'total_tax'           => 0.0,
            'total_net_revenue'   => 0.0,
            'transaction_count'   => 0,
            'currency'            => 'RWF',
        ];
    }
}
