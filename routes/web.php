<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
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
