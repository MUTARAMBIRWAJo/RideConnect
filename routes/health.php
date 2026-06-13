<?php

use App\Http\Controllers\Health\PlatformHealthController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Platform Health Routes (Render / monitoring)
|--------------------------------------------------------------------------
|
| Lightweight liveness for load balancers: GET /health/live
| Readiness gate for traffic:            GET /health/ready
| Full diagnostics:                      GET /health/full
|
*/

Route::get('/health/live', [PlatformHealthController::class, 'live']);
Route::get('/health/ready', [PlatformHealthController::class, 'ready']);
Route::get('/health/full', [PlatformHealthController::class, 'full']);

// Backward-compatible alias used by older Render configs.
Route::get('/health', [PlatformHealthController::class, 'live']);
