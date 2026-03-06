<?php

use App\Jobs\ProcessDailySettlementJob;
use App\Jobs\ProcessOutboxJob;
use App\Jobs\NightlyWarehouseEtlJob;
use Carbon\Carbon;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| Scheduled Tasks
|--------------------------------------------------------------------------
*/

// Run daily settlement at midnight — processes yesterday's completed rides,
// creates ledger entries, moves escrow → driver wallets.
Schedule::job(new ProcessDailySettlementJob(Carbon::yesterday()->toDateString()))
    ->dailyAt('00:00')
    ->withoutOverlapping()
    ->onFailure(function () {
        \Illuminate\Support\Facades\Log::critical('ProcessDailySettlementJob scheduled run failed.');
    })
    ->name('daily-settlement');

// Process transactional outbox every minute (publish domain events to broker).
Schedule::job(new ProcessOutboxJob())
    ->everyMinute()
    ->onOneServer()
    ->name('process-outbox');

// Nightly data warehouse ETL — runs at 02:00 after settlement completes.
Schedule::job(new NightlyWarehouseEtlJob(Carbon::yesterday()->toDateString()))
    ->dailyAt('02:00')
    ->withoutOverlapping()
    ->onOneServer()
    ->onFailure(function () {
        \Illuminate\Support\Facades\Log::critical('NightlyWarehouseEtlJob failed — BI data may be stale.');
    })
    ->name('etl-nightly');

