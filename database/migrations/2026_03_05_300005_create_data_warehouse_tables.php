<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // =========================================================
    // FINANCIAL DATA WAREHOUSE — Star Schema
    // Isolated from OLTP transactional tables.
    // All tables prefixed with dw_ to signal warehouse scope.
    // Populated nightly by NightlyWarehouseEtlJob.
    // =========================================================

    public function up(): void
    {
        // ------------------------------------------------------------------
        // DIMENSION: Date
        // ------------------------------------------------------------------
        Schema::create('dw_dim_date', function (Blueprint $table) {
            $table->date('date_key')->primary();
            $table->unsignedSmallInteger('year');
            $table->unsignedTinyInteger('month');
            $table->unsignedTinyInteger('day');
            $table->unsignedTinyInteger('day_of_week');   // 0=Sunday
            $table->unsignedTinyInteger('quarter');
            $table->string('month_name', 20);
            $table->string('day_name', 20);
            $table->boolean('is_weekend')->default(false);
            $table->boolean('is_holiday')->default(false);
        });

        // ------------------------------------------------------------------
        // DIMENSION: Driver
        // ------------------------------------------------------------------
        Schema::create('dw_dim_driver', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('driver_id');        // FK to drivers.id
            $table->string('driver_name', 200);
            $table->string('phone', 30)->nullable();
            $table->string('vehicle_class', 50)->nullable();
            $table->string('region', 100)->nullable();
            $table->date('joined_date')->nullable();
            $table->boolean('is_active')->default(true);
            $table->date('effective_from');
            $table->date('effective_until')->nullable();    // SCD Type 2
            $table->boolean('is_current')->default(true);
            $table->timestamps();

            $table->index(['driver_id', 'is_current']);
        });

        // ------------------------------------------------------------------
        // DIMENSION: Passenger
        // ------------------------------------------------------------------
        Schema::create('dw_dim_passenger', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('passenger_id');
            $table->string('passenger_name', 200);
            $table->string('phone', 30)->nullable();
            $table->date('registered_date')->nullable();
            $table->boolean('is_current')->default(true);
            $table->date('effective_from');
            $table->date('effective_until')->nullable();
            $table->timestamps();

            $table->index(['passenger_id', 'is_current']);
        });

        // ------------------------------------------------------------------
        // DIMENSION: Payment Provider
        // ------------------------------------------------------------------
        Schema::create('dw_dim_payment_provider', function (Blueprint $table) {
            $table->id();
            $table->string('provider_code', 50)->unique(); // 'stripe', 'mtn_momo', 'cash'
            $table->string('provider_name', 100);
            $table->string('provider_type', 50);           // card, mobile_money, cash
            $table->string('currency', 10)->default('RWF');
            $table->boolean('active')->default(true);
        });

        DB::table('dw_dim_payment_provider')->insert([
            ['provider_code' => 'stripe',   'provider_name' => 'Stripe',              'provider_type' => 'card',          'currency' => 'RWF', 'active' => true],
            ['provider_code' => 'mtn_momo', 'provider_name' => 'MTN Mobile Money',    'provider_type' => 'mobile_money',  'currency' => 'RWF', 'active' => true],
            ['provider_code' => 'cash',     'provider_name' => 'Cash',                'provider_type' => 'cash',          'currency' => 'RWF', 'active' => true],
        ]);

        // ------------------------------------------------------------------
        // DIMENSION: Region
        // ------------------------------------------------------------------
        Schema::create('dw_dim_region', function (Blueprint $table) {
            $table->id();
            $table->string('region_code', 20)->unique();
            $table->string('region_name', 100);
            $table->string('province', 100)->nullable();
            $table->string('country_code', 5)->default('RW');
        });

        // ------------------------------------------------------------------
        // FACT: Transactions (one row per financial ledger transaction)
        // ------------------------------------------------------------------
        Schema::create('dw_fact_transactions', function (Blueprint $table) {
            $table->id();
            $table->date('date_key');
            $table->unsignedBigInteger('driver_dim_id')->nullable();
            $table->unsignedBigInteger('passenger_dim_id')->nullable();
            $table->unsignedBigInteger('payment_provider_dim_id')->nullable();
            $table->unsignedBigInteger('region_dim_id')->nullable();

            // Source references (denormalised)
            $table->unsignedBigInteger('ledger_transaction_id')->nullable();
            $table->string('transaction_type', 50); // payment, settlement, payout, refund, tax

            // Financial amounts in RWF
            $table->decimal('gross_amount', 14, 2)->default(0);
            $table->decimal('commission_amount', 14, 2)->default(0);
            $table->decimal('driver_payout', 14, 2)->default(0);
            $table->decimal('tax_amount', 14, 2)->default(0);
            $table->decimal('net_platform_revenue', 14, 2)->default(0);
            $table->string('currency', 10)->default('RWF');

            // Audit
            $table->timestamp('etl_loaded_at')->useCurrent();
            $table->string('etl_batch_id', 36)->nullable();

            $table->index('date_key');
            $table->index(['date_key', 'transaction_type']);
            $table->index('driver_dim_id');
        });

        // ------------------------------------------------------------------
        // FACT: Rides
        // ------------------------------------------------------------------
        Schema::create('dw_fact_rides', function (Blueprint $table) {
            $table->id();
            $table->date('date_key');
            $table->unsignedBigInteger('driver_dim_id')->nullable();
            $table->unsignedBigInteger('passenger_dim_id')->nullable();
            $table->unsignedBigInteger('region_dim_id')->nullable();
            $table->unsignedBigInteger('source_ride_id')->nullable();

            $table->string('ride_status', 30);        // completed, cancelled, etc.
            $table->decimal('fare_amount', 12, 2)->default(0);
            $table->decimal('distance_km', 8, 2)->default(0);
            $table->unsignedSmallInteger('duration_minutes')->default(0);
            $table->decimal('surge_multiplier', 5, 2)->default(1.00);

            $table->timestamp('pickup_at')->nullable();
            $table->timestamp('dropoff_at')->nullable();

            $table->timestamp('etl_loaded_at')->useCurrent();
            $table->string('etl_batch_id', 36)->nullable();

            $table->index('date_key');
            $table->index(['date_key', 'ride_status']);
            $table->index('driver_dim_id');
        });

        // ------------------------------------------------------------------
        // FACT: Driver Earnings  (aggregated per driver per day)
        // ------------------------------------------------------------------
        Schema::create('dw_fact_driver_earnings', function (Blueprint $table) {
            $table->id();
            $table->date('date_key');
            $table->unsignedBigInteger('driver_dim_id');
            $table->unsignedBigInteger('region_dim_id')->nullable();

            $table->unsignedSmallInteger('total_rides')->default(0);
            $table->decimal('gross_earnings', 14, 2)->default(0);
            $table->decimal('commission_deducted', 14, 2)->default(0);
            $table->decimal('tax_withheld', 14, 2)->default(0);
            $table->decimal('net_payout', 14, 2)->default(0);
            $table->decimal('avg_ride_fare', 12, 2)->default(0);

            $table->timestamp('etl_loaded_at')->useCurrent();
            $table->string('etl_batch_id', 36)->nullable();

            $table->unique(['date_key', 'driver_dim_id']);
            $table->index('date_key');
        });

        // ------------------------------------------------------------------
        // FACT: Commissions  (aggregated per day)
        // ------------------------------------------------------------------
        Schema::create('dw_fact_commissions', function (Blueprint $table) {
            $table->id();
            $table->date('date_key');
            $table->unsignedBigInteger('driver_dim_id')->nullable();
            $table->unsignedBigInteger('payment_provider_dim_id')->nullable();

            $table->decimal('total_commission', 14, 2)->default(0);
            $table->decimal('tax_on_commission', 14, 2)->default(0);
            $table->decimal('net_commission', 14, 2)->default(0);
            $table->unsignedSmallInteger('transaction_count')->default(0);

            $table->timestamp('etl_loaded_at')->useCurrent();
            $table->string('etl_batch_id', 36)->nullable();

            $table->unique(['date_key', 'driver_dim_id', 'payment_provider_dim_id']);
            $table->index('date_key');
        });

        // ------------------------------------------------------------------
        // MATERIALIZED VIEW: Daily Revenue
        // ------------------------------------------------------------------
        DB::statement(<<<'SQL'
            CREATE MATERIALIZED VIEW mv_daily_revenue AS
            SELECT
                ft.date_key,
                SUM(ft.gross_amount)          AS total_gross,
                SUM(ft.commission_amount)     AS total_commission,
                SUM(ft.driver_payout)         AS total_driver_payout,
                SUM(ft.tax_amount)            AS total_tax,
                SUM(ft.net_platform_revenue)  AS total_net_revenue,
                COUNT(*)                      AS transaction_count
            FROM dw_fact_transactions ft
            WHERE ft.transaction_type = 'payment'
            GROUP BY ft.date_key
            ORDER BY ft.date_key;
        SQL);

        DB::statement('CREATE UNIQUE INDEX ON mv_daily_revenue (date_key)');

        // ------------------------------------------------------------------
        // MATERIALIZED VIEW: Monthly Growth
        // ------------------------------------------------------------------
        DB::statement(<<<'SQL'
            CREATE MATERIALIZED VIEW mv_monthly_growth AS
            SELECT
                date_trunc('month', date_key::timestamp) AS month,
                SUM(gross_amount)         AS gross_revenue,
                SUM(commission_amount)    AS commission_revenue,
                SUM(tax_amount)           AS tax_collected,
                COUNT(*)                  AS transaction_count,
                SUM(gross_amount) - LAG(SUM(gross_amount)) OVER (ORDER BY date_trunc('month', date_key::timestamp))
                    AS revenue_growth
            FROM dw_fact_transactions
            WHERE transaction_type = 'payment'
            GROUP BY date_trunc('month', date_key::timestamp)
            ORDER BY month;
        SQL);

        DB::statement('CREATE UNIQUE INDEX ON mv_monthly_growth (month)');

        // ------------------------------------------------------------------
        // MATERIALIZED VIEW: Driver Rankings (last 30 days)
        // ------------------------------------------------------------------
        DB::statement(<<<'SQL'
            CREATE MATERIALIZED VIEW mv_driver_rankings AS
            SELECT
                e.driver_dim_id,
                d.driver_name,
                d.region,
                SUM(e.total_rides)       AS total_rides,
                SUM(e.gross_earnings)    AS gross_earnings,
                SUM(e.net_payout)        AS net_payout,
                AVG(e.avg_ride_fare)     AS avg_fare,
                RANK() OVER (ORDER BY SUM(e.gross_earnings) DESC) AS earnings_rank
            FROM dw_fact_driver_earnings e
            JOIN dw_dim_driver d ON d.id = e.driver_dim_id AND d.is_current = true
            WHERE e.date_key >= CURRENT_DATE - INTERVAL '30 days'
            GROUP BY e.driver_dim_id, d.driver_name, d.region;
        SQL);

        DB::statement('CREATE UNIQUE INDEX ON mv_driver_rankings (driver_dim_id)');
    }

    public function down(): void
    {
        DB::statement('DROP MATERIALIZED VIEW IF EXISTS mv_driver_rankings');
        DB::statement('DROP MATERIALIZED VIEW IF EXISTS mv_monthly_growth');
        DB::statement('DROP MATERIALIZED VIEW IF EXISTS mv_daily_revenue');

        Schema::dropIfExists('dw_fact_commissions');
        Schema::dropIfExists('dw_fact_driver_earnings');
        Schema::dropIfExists('dw_fact_rides');
        Schema::dropIfExists('dw_fact_transactions');
        Schema::dropIfExists('dw_dim_region');
        Schema::dropIfExists('dw_dim_payment_provider');
        Schema::dropIfExists('dw_dim_passenger');
        Schema::dropIfExists('dw_dim_driver');
        Schema::dropIfExists('dw_dim_date');
    }
};
