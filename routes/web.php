<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Admin\AdminAuthController;
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

// Unified Login Page Route (for regular web users)
Route::get('/auth/login', [AuthController::class, 'showLogin'])->name('auth.login');
Route::post('/auth/login', [AuthController::class, 'login']);

// Public authentication routes
Route::middleware('guest')->group(function () {
    // User registration
    Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/register', [AuthController::class, 'register']);
    
});

// Protected routes - Regular users (drivers, passengers)
Route::middleware(['auth'])->group(function () {
    // User dashboard
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    
    // User logout
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
});

// Note: Filament handles admin authentication at /admin
// No custom admin routes needed - Filament manages its own auth

// Legacy admin-auth pages used by custom Blade templates.
Route::middleware('guest')->group(function () {
    Route::get('/admin/login', [AdminAuthController::class, 'showLogin'])->name('admin.login');
    Route::post('/admin/login', [AdminAuthController::class, 'login']);

    // Compatibility route expected by Filament auth middleware redirects.
    Route::get('/admin/filament-login', [AuthController::class, 'showLogin'])
        ->name('filament.admin.auth.login');
});

Route::middleware('auth:admin')->group(function () {
    Route::get('/admin/register', [AdminAuthController::class, 'showRegister'])->name('admin.register');
    Route::post('/admin/register', [AdminAuthController::class, 'register']);
    Route::post('/admin/logout', [AdminAuthController::class, 'logout'])->name('admin.logout');

    // Compatibility route expected by Filament user-menu/account widgets.
    Route::match(['GET', 'POST'], '/admin/filament-logout', [AdminAuthController::class, 'logout'])
        ->name('filament.admin.auth.logout');
});

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
