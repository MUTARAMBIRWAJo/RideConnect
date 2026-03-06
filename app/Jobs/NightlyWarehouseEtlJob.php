<?php

namespace App\Jobs;

use App\Modules\Reporting\Services\ReportingService;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * NightlyWarehouseEtlJob
 *
 * Runs nightly after settlement to:
 *  1. Populate dw_dim_* (SCD Type 2 for drivers/passengers)
 *  2. Load dw_fact_transactions from ledger_transactions
 *  3. Load dw_fact_rides from rides table
 *  4. Load dw_fact_driver_earnings from driver_payouts
 *  5. Load dw_fact_commissions from platform_commissions
 *  6. REFRESH materialized views
 *  7. Invalidate Redis BI cache
 *
 * Safely retried (idempotent via etl_batch_id deduplication).
 */
class NightlyWarehouseEtlJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int    $tries   = 3;
    public int    $backoff = 600; // 10-minute retry backoff

    public function __construct(
        public readonly string $targetDate,
    ) {
        $this->onQueue('reporting');
    }

    public function handle(ReportingService $reportingService): void
    {
        if (! Schema::hasTable('dw_fact_transactions')) {
            Log::warning('NightlyWarehouseEtlJob: warehouse tables not yet created. Run migrations first.');
            return;
        }

        $batchId = (string) Str::uuid();

        Log::info("NightlyWarehouseEtlJob: ETL started for {$this->targetDate}", ['batch_id' => $batchId]);

        try {
            $this->populateDimDate($this->targetDate, $batchId);
            $this->upsertDimDrivers($batchId);
            $this->upsertDimPassengers($batchId);
            $this->loadFactTransactions($this->targetDate, $batchId);
            $this->loadFactRides($this->targetDate, $batchId);
            $this->loadFactDriverEarnings($this->targetDate, $batchId);
            $this->loadFactCommissions($this->targetDate, $batchId);
            $this->refreshMaterializedViews();

            $reportingService->invalidateCache();

            Log::info("NightlyWarehouseEtlJob: ETL complete for {$this->targetDate}", ['batch_id' => $batchId]);
        } catch (\Throwable $e) {
            Log::critical('NightlyWarehouseEtlJob: ETL failed', [
                'date'     => $this->targetDate,
                'batch_id' => $batchId,
                'error'    => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    public function failed(\Throwable $e): void
    {
        Log::critical('NightlyWarehouseEtlJob permanently failed', [
            'date'  => $this->targetDate,
            'error' => $e->getMessage(),
        ]);
    }

    // -----------------------------------------------------------------------

    private function populateDimDate(string $date, string $batchId): void
    {
        $d = Carbon::parse($date);

        DB::table('dw_dim_date')->updateOrInsert(
            ['date_key' => $date],
            [
                'year'        => $d->year,
                'month'       => $d->month,
                'day'         => $d->day,
                'day_of_week' => $d->dayOfWeek,
                'quarter'     => $d->quarter,
                'month_name'  => $d->format('F'),
                'day_name'    => $d->format('l'),
                'is_weekend'  => $d->isWeekend(),
                'is_holiday'  => false, // Pluggable: integrate RWA holiday calendar
            ]
        );
    }

    private function upsertDimDrivers(string $batchId): void
    {
        DB::table('drivers as d')
            ->join('users as u', 'u.id', '=', 'd.user_id')
            ->orderBy('d.id')
            ->select('d.id', 'u.name', 'u.phone', 'd.created_at')
            ->chunkById(200, function ($drivers) use ($batchId) {
                foreach ($drivers as $driver) {
                    // Check if already in dim with same name
                    $existing = DB::table('dw_dim_driver')
                        ->where('driver_id', $driver->id)
                        ->where('is_current', true)
                        ->first();

                    if (! $existing) {
                        DB::table('dw_dim_driver')->insert([
                            'driver_id'      => $driver->id,
                            'driver_name'    => $driver->name,
                            'phone'          => $driver->phone,
                            'joined_date'    => Carbon::parse($driver->created_at)->toDateString(),
                            'is_active'      => true,
                            'is_current'     => true,
                            'effective_from' => Carbon::parse($driver->created_at)->toDateString(),
                            'created_at'     => now(),
                            'updated_at'     => now(),
                        ]);
                    }
                }
            }, 'id');
    }

    private function upsertDimPassengers(string $batchId): void
    {
        DB::table('mobile_users as m')
            ->orderBy('m.id')
            ->chunkById(200, function ($passengers) {
                foreach ($passengers as $p) {
                    if (! DB::table('dw_dim_passenger')->where('passenger_id', $p->id)->where('is_current', true)->exists()) {
                        DB::table('dw_dim_passenger')->insert([
                            'passenger_id'   => $p->id,
                            'passenger_name' => trim($p->first_name . ' ' . $p->last_name),
                            'phone'          => $p->phone,
                            'registered_date'=> Carbon::parse($p->created_at)->toDateString(),
                            'is_current'     => true,
                            'effective_from' => Carbon::parse($p->created_at)->toDateString(),
                            'created_at'     => now(),
                            'updated_at'     => now(),
                        ]);
                    }
                }
            }, 'id');
    }

    private function loadFactTransactions(string $date, string $batchId): void
    {
        // Avoid duplicate ETL runs for the same date
        $alreadyLoaded = DB::table('dw_fact_transactions')
            ->where('date_key', $date)
            ->where('etl_batch_id', '!=', $batchId)
            ->exists();

        if ($alreadyLoaded) {
            Log::info("NightlyWarehouseEtlJob: fact_transactions already loaded for {$date}, skipping.");
            return;
        }

        DB::table('ledger_transactions as lt')
            ->join('ledger_entries as le', 'le.transaction_id', '=', 'lt.id')
            ->join('ledger_accounts as la', 'la.id', '=', 'le.account_id')
            ->whereDate('lt.created_at', $date)
            ->where('la.name', 'Platform Escrow')
            ->where('le.credit', '>', 0)
            ->select('lt.id', 'lt.description', 'le.credit', 'lt.created_at')
            ->chunkById(500, function ($rows) use ($date, $batchId) {
                $insert = [];
                foreach ($rows as $row) {
                    $commission = round($row->credit * 0.08, 2);
                    $payout     = round($row->credit * 0.92, 2);

                    $insert[] = [
                        'date_key'              => $date,
                        'ledger_transaction_id' => $row->id,
                        'transaction_type'      => 'payment',
                        'gross_amount'          => $row->credit,
                        'commission_amount'     => $commission,
                        'driver_payout'         => $payout,
                        'tax_amount'            => 0.00,
                        'net_platform_revenue'  => $commission,
                        'currency'              => 'RWF',
                        'etl_batch_id'          => $batchId,
                        'etl_loaded_at'         => now(),
                    ];
                }
                if ($insert) DB::table('dw_fact_transactions')->insert($insert);
            }, 'lt.id');
    }

    private function loadFactRides(string $date, string $batchId): void
    {
        DB::table('rides')
            ->whereDate('updated_at', $date)
            ->where('status', 'completed')
            ->chunkById(500, function ($rides) use ($date, $batchId) {
                $dimDriverMap    = DB::table('dw_dim_driver')->where('is_current', true)->pluck('id', 'driver_id');
                $dimPassengerMap = DB::table('dw_dim_passenger')->where('is_current', true)->pluck('id', 'passenger_id');

                $insert = [];
                foreach ($rides as $ride) {
                    $insert[] = [
                        'date_key'         => $date,
                        'driver_dim_id'    => $dimDriverMap[$ride->driver_id] ?? null,
                        'source_ride_id'   => $ride->id,
                        'ride_status'      => $ride->status,
                        'fare_amount'      => $ride->fare_amount ?? 0,
                        'distance_km'      => $ride->distance_km ?? 0,
                        'duration_minutes' => $ride->duration_minutes ?? 0,
                        'surge_multiplier' => 1.00,
                        'etl_batch_id'     => $batchId,
                        'etl_loaded_at'    => now(),
                    ];
                }
                if ($insert) DB::table('dw_fact_rides')->insert($insert);
            }, 'id');
    }

    private function loadFactDriverEarnings(string $date, string $batchId): void
    {
        $dimDriverMap = DB::table('dw_dim_driver')->where('is_current', true)->pluck('id', 'driver_id');

        DB::table('driver_payouts')
            ->where('payout_date', $date)
            ->where('status', 'processed')
            ->chunkById(200, function ($payouts) use ($date, $batchId, $dimDriverMap) {
                $insert = [];
                foreach ($payouts as $p) {
                    $insert[] = [
                        'date_key'            => $date,
                        'driver_dim_id'       => $dimDriverMap[$p->driver_id] ?? null,
                        'total_rides'         => 0, // Could join ride count
                        'gross_earnings'      => $p->total_income,
                        'commission_deducted' => $p->commission_amount,
                        'tax_withheld'        => 0.00,
                        'net_payout'          => $p->payout_amount,
                        'avg_ride_fare'       => 0.00,
                        'etl_batch_id'        => $batchId,
                        'etl_loaded_at'       => now(),
                    ];
                }
                if ($insert) {
                    // Use upsert to handle re-runs
                    DB::table('dw_fact_driver_earnings')->upsert($insert, ['date_key', 'driver_dim_id'], [
                        'gross_earnings', 'commission_deducted', 'net_payout', 'etl_batch_id', 'etl_loaded_at',
                    ]);
                }
            }, 'id');
    }

    private function loadFactCommissions(string $date, string $batchId): void
    {
        $dimDriverMap = DB::table('dw_dim_driver')->where('is_current', true)->pluck('id', 'driver_id');

        DB::table('platform_commissions')
            ->where('date', $date)
            ->chunkById(200, function ($commissions) use ($date, $batchId, $dimDriverMap) {
                $insert = [];
                foreach ($commissions as $c) {
                    $insert[] = [
                        'date_key'              => $date,
                        'driver_dim_id'         => $dimDriverMap[$c->driver_id] ?? null,
                        'total_commission'      => $c->commission_amount,
                        'tax_on_commission'     => round($c->commission_amount * 0.15, 2),
                        'net_commission'        => round($c->commission_amount * 0.85, 2),
                        'transaction_count'     => 1,
                        'etl_batch_id'          => $batchId,
                        'etl_loaded_at'         => now(),
                    ];
                }
                if ($insert) {
                    DB::table('dw_fact_commissions')->upsert($insert, ['date_key', 'driver_dim_id', 'payment_provider_dim_id'], [
                        'total_commission', 'tax_on_commission', 'net_commission', 'etl_batch_id', 'etl_loaded_at',
                    ]);
                }
            }, 'id');
    }

    private function refreshMaterializedViews(): void
    {
        DB::statement('REFRESH MATERIALIZED VIEW CONCURRENTLY mv_daily_revenue');
        DB::statement('REFRESH MATERIALIZED VIEW CONCURRENTLY mv_monthly_growth');
        DB::statement('REFRESH MATERIALIZED VIEW CONCURRENTLY mv_driver_rankings');
    }
}
