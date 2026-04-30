<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\Accountant\ReportDownloadController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Admin\FinancialMatrixExportController;
use App\Http\Controllers\Admin\GoogleMapsHealthController;
use App\Http\Controllers\Admin\OperationsIntelligenceExportController;
use App\Http\Controllers\Api\Admin\LiveMapDataController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| RideConnect Web Routes
| Session-based authentication for web users
| Filament handles admin authentication at /admin
|
*/

// Default route - Unified Login Page at /auth/login
Route::get('/', function () {
    return redirect()->route('auth.login');
})->name('home');

// Unified Login Page Route - ONLY login page for all users
Route::get('/auth/login', [AuthController::class, 'showLogin'])->name('auth.login');
Route::post('/auth/login', [AuthController::class, 'login']);

// Admin login route redirects to unified login
Route::get('/admin/login', function () {
    return redirect()->route('auth.login');
})->name('admin.login');

// Filament login compatibility route
Route::get('/admin/filament-login', function () {
    return redirect()->route('auth.login');
})->name('filament.admin.auth.login');

// Public authentication routes
Route::middleware('guest')->group(function () {
    // User registration
    Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/register', [AuthController::class, 'register']);
    
});

// Note: Filament handles admin authentication at /admin
// All login routes are unified through /auth/login

// Google OAuth routes
Route::get('/auth/google', [\App\Http\Controllers\GoogleOAuthController::class, 'redirect'])->name('auth.google');
Route::get('/auth/google/callback', [\App\Http\Controllers\GoogleOAuthController::class, 'callback'])->name('auth.google.callback');

// Two-Factor Authentication routes (middleware: auth.partial)
Route::middleware('auth.partial')->group(function () {
    Route::get('/auth/two-factor-challenge', [\App\Http\Controllers\TwoFactorController::class, 'show'])->name('auth.two-factor-challenge');
    Route::post('/auth/two-factor-challenge', [\App\Http\Controllers\TwoFactorController::class, 'verify'])->name('auth.two-factor-verify');
    Route::post('/auth/two-factor-backup', [\App\Http\Controllers\TwoFactorController::class, 'verifyBackupCode'])->name('auth.two-factor-backup');
});

// MFA Setup routes (middleware: auth, verified)
Route::middleware(['auth', 'verified'])->prefix('/auth/mfa')->group(function () {
    Route::get('/settings', [\App\Http\Controllers\MfaSetupController::class, 'settings'])->name('mfa.settings');
    Route::get('/setup', [\App\Http\Controllers\MfaSetupController::class, 'show'])->name('mfa.setup');
    Route::post('/store', [\App\Http\Controllers\MfaSetupController::class, 'store'])->name('mfa.store');
    Route::post('/disable', [\App\Http\Controllers\MfaSetupController::class, 'disable'])->name('mfa.disable');
    Route::get('/backup-codes', [\App\Http\Controllers\MfaSetupController::class, 'backupCodes'])->name('mfa.backup-codes');
});

// Protected routes - All authenticated users
Route::middleware(['auth'])->group(function () {
    // User dashboard (for drivers, passengers)
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    
    // User logout
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
});

// Admin logout compatibility route - POST only for security
Route::post('/admin/logout', [AuthController::class, 'logout'])->name('admin.logout');
Route::post('/admin/filament-logout', [AuthController::class, 'logout'])
    ->name('filament.admin.auth.logout');

// Compatibility aliases for legacy admin view links.
Route::middleware('auth')->group(function () {
    Route::redirect('/admin/legacy/dashboard', '/admin')->name('admin.dashboard');

    Route::redirect('/admin/legacy/users', '/admin/users')->name('admin.users.index');
    Route::redirect('/admin/legacy/users/create', '/admin/users/create')->name('admin.users.create');
    Route::redirect('/admin/legacy/users/pending', '/admin/users')->name('admin.users.pending');

    Route::post('/admin/legacy/users/{user}/approve', fn () => redirect()->to('/admin/users'))
        ->name('admin.users.approve');
    Route::post('/admin/legacy/users/{user}/reject', fn () => redirect()->to('/admin/users'))
        ->name('admin.users.reject');

    Route::redirect('/admin/legacy/bookings', '/admin/bookings')->name('admin.bookings.index');
    Route::redirect('/admin/legacy/bookings/pending', '/admin/bookings')->name('admin.bookings.pending');

    Route::redirect('/admin/legacy/trips', '/admin/trips')->name('admin.trips.index');
    Route::redirect('/admin/legacy/trips/pending', '/admin/trips')->name('admin.trips.pending');
    Route::redirect('/admin/legacy/trips/completed', '/admin/trips')->name('admin.trips.completed');

    Route::redirect('/admin/legacy/rides', '/admin/rides')->name('admin.rides.index');
    Route::redirect('/admin/legacy/rides/available', '/admin/rides')->name('admin.rides.available');

    Route::redirect('/admin/legacy/reports/users', '/admin')->name('admin.reports.users');
    Route::redirect('/admin/legacy/reports/trips', '/admin')->name('admin.reports.trips');
    Route::redirect('/admin/legacy/reports/financial', '/admin')->name('admin.reports.financial');
});

// Public fallback routes for placeholder legal/help links.
Route::redirect('/terms-of-service', '/')->name('terms');
Route::redirect('/privacy-policy', '/')->name('privacy');
Route::redirect('/forgot-password', '/auth/login')->name('password.request');

// Filament admin live map endpoint (session-authenticated; role checks handled in controller).
Route::middleware(['auth'])
    ->get('/api/map/live-data', [LiveMapDataController::class, 'index'])
    ->name('api.map.live-data');

Route::middleware(['auth'])
    ->get('/admin/google-maps-health/preflight', [GoogleMapsHealthController::class, 'preflight'])
    ->name('admin.maps.health.preflight');

Route::middleware(['auth'])
    ->get('/accountant/reports/download/{file}', ReportDownloadController::class)
    ->where('file', '.*')
    ->name('accountant.reports.download');

Route::middleware(['auth'])
    ->prefix('/admin/exports/operations-intelligence')
    ->group(function () {
        Route::get('/csv', [OperationsIntelligenceExportController::class, 'csv'])
            ->name('admin.exports.operations-intelligence.csv');
        Route::get('/pdf', [OperationsIntelligenceExportController::class, 'pdf'])
            ->name('admin.exports.operations-intelligence.pdf');
        Route::get('/xlsx', [OperationsIntelligenceExportController::class, 'xlsx'])
            ->name('admin.exports.operations-intelligence.xlsx');
    });

Route::middleware(['auth'])
    ->prefix('/admin/exports/financial-matrix')
    ->group(function () {
        Route::get('/csv', [FinancialMatrixExportController::class, 'csv'])
            ->name('admin.exports.financial-matrix.csv');
        Route::get('/pdf', [FinancialMatrixExportController::class, 'pdf'])
            ->name('admin.exports.financial-matrix.pdf');
        Route::get('/xlsx', [FinancialMatrixExportController::class, 'xlsx'])
            ->name('admin.exports.financial-matrix.xlsx');
    });
