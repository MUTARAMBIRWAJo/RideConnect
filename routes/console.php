<?php

use App\Jobs\ProcessDailySettlementJob;
use App\Jobs\ProcessOutboxJob;
use App\Jobs\NightlyWarehouseEtlJob;
use App\Jobs\CleanupStaleDriverLocations;
use App\Jobs\PollDemandPredictionsJob;
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

// Trigger independent AI model retraining from platform data every night.
Schedule::command('ai:retrain-models')
    ->dailyAt('03:00')
    ->withoutOverlapping()
    ->onOneServer()
    ->name('ai-retrain-models');

// Poll production demand forecasts for key Kigali zones.
Schedule::job(new PollDemandPredictionsJob())
    ->everyFiveMinutes()
    ->withoutOverlapping()
    ->onOneServer()
    ->name('poll-ml-demand-predictions');

// Clean up stale driver locations every 5 minutes
Schedule::job(new CleanupStaleDriverLocations())
    ->everyFiveMinutes()
    ->name('cleanup-stale-driver-locations');

// Continuously enforce ride category transitions in both directions.
Schedule::command('rides:promote-bookings-to-trips')
    ->everyFiveMinutes()
    ->withoutOverlapping()
    ->onOneServer()
    ->name('sync-travel-categories');
