<?php

use App\Http\Controllers\Api\AuthController as ApiAuthController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\RideController;
use App\Http\Controllers\Api\BookingController;
use App\Http\Controllers\Api\TripController;
use App\Http\Controllers\Api\PassengerController;
use App\Http\Controllers\Api\RiderController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\TicketController;
use App\Http\Controllers\Api\Admin\UserApprovalController as UserApprovalController;
use App\Http\Controllers\Api\Admin\UserManagementController;
use App\Http\Controllers\Api\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Api\Finance\FinanceController;
use App\Http\Controllers\Api\Finance\ExportController;
use App\Http\Controllers\Api\Accountant\PayoutController;
use App\Http\Controllers\Api\Analytics\AnalyticsController;
use App\Http\Controllers\Api\HealthCheckController;
use App\Http\Controllers\Api\Webhooks\StripeWebhookController;
use App\Http\Controllers\Api\Webhooks\MTNWebhookController;
use App\Models\Manager;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes - RideConnect
|--------------------------------------------------------------------------
|
| RideConnect API Routes with URI versioning support
| Token-based authentication using Laravel Sanctum
| Role-based access control
|
*/

/* ===========================
   VERSION PREFIX
   =========================== */

/* ===========================
   PAYMENT WEBHOOKS (No auth — verified by signature/key)
   =========================== */

Route::prefix('webhooks')->group(function () {
    Route::post('/stripe', [StripeWebhookController::class, 'handle'])->name('webhooks.stripe');
    Route::post('/mtn',    [MTNWebhookController::class,   'handle'])->name('webhooks.mtn');
});

Route::prefix('v1')->group(function () {

    /* ===========================
       PUBLIC ROUTES - No Authentication
       =========================== */

    // Authentication
    Route::prefix('auth')->group(function () {
        // Register - Only passenger and rider allowed
        Route::post('/register', [ApiAuthController::class, 'register']);
        // Login
        Route::post('/login', [ApiAuthController::class, 'login']);
    });

    /* ===========================
       MOBILE APP - SANCTUM AUTHENTICATED
       =========================== */

    Route::middleware(['auth:sanctum'])->group(function () {

        /* ---- Authentication ---- */
        Route::prefix('auth')->group(function () {
            // Logout
            Route::post('/logout', [ApiAuthController::class, 'logout']);
            // Get profile
            Route::get('/profile', [ApiAuthController::class, 'profile']);
            // Update profile
            Route::put('/profile', [ApiAuthController::class, 'updateProfile']);
        });

        /* ===========================
           PASSENGER APIs
           =========================== */
        Route::prefix('passenger')->group(function () {
            // Profile
            Route::get('/profile', [PassengerController::class, 'profile']);
            Route::put('/profile', [PassengerController::class, 'updateProfile']);
            
            // Rides - Booking
            Route::post('/rides', [RideController::class, 'bookRide']);
            Route::get('/rides', [RideController::class, 'myRides']);
            Route::get('/rides/{id}', [RideController::class, 'showRide']);
            Route::put('/rides/{id}/cancel', [RideController::class, 'cancelRide']);
            
            // Ride History
            Route::get('/rides/history', [PassengerController::class, 'rideHistory']);
            
            // Payments
            Route::post('/payments', [PaymentController::class, 'createPayment']);
            Route::get('/payments/history', [PaymentController::class, 'paymentHistory']);
        });

        /* ===========================
           RIDER APIs
           =========================== */
        Route::prefix('rider')->group(function () {
            // Availability status
            Route::put('/status', [RiderController::class, 'updateStatus']);
            
            // Ride Requests
            Route::get('/requests', [RiderController::class, 'rideRequests']);
            Route::put('/requests/{id}/accept', [RiderController::class, 'acceptRequest']);
            Route::put('/requests/{id}/reject', [RiderController::class, 'rejectRequest']);
            Route::put('/requests/{id}/complete', [RiderController::class, 'completeRequest']);
            
            // Earnings
            Route::get('/earnings', [RiderController::class, 'earnings']);
            Route::get('/earnings/monthly', [RiderController::class, 'monthlyEarnings']);
            
            // Documents
            Route::post('/documents', [RiderController::class, 'uploadDocument']);
            Route::get('/documents', [RiderController::class, 'listDocuments']);
        });

        /* ===========================
           SHARED - All Authenticated Users
           =========================== */
           
        // User Profile (alias for /auth/profile)
        Route::get('/user/profile', [ApiAuthController::class, 'profile']);
        Route::put('/user/password', [UserController::class, 'updatePassword']);
    });

    /* ===========================
       MANAGER APIs - Role Based
       =========================== */

    // Manager Authentication (Session/Token based for web)
    Route::prefix('manager')->group(function () {
        Route::post('/login', [ApiAuthController::class, 'managerLogin']);
        Route::post('/logout', [ApiAuthController::class, 'managerLogout']);
        Route::get('/profile', [ApiAuthController::class, 'managerProfile']);
    });

    // Protected Manager Routes with Role Middleware
    Route::middleware(['auth:sanctum', 'role:super_admin,admin'])->group(function () {

        /* ---- Super Admin & Admin Shared ---- */
        
        // Dashboard Stats
        Route::prefix('admin')->group(function () {
            // Dashboard
            Route::get('/dashboard', [AdminDashboardController::class, 'index'])
                ->middleware('can:viewAdminDashboard,' . Manager::class);
            
            // User Management
            Route::get('/users', [UserManagementController::class, 'index']);
            Route::post('/users', [UserManagementController::class, 'store']);
            Route::get('/users/{id}', [UserManagementController::class, 'show']);
            Route::put('/users/{id}', [UserManagementController::class, 'update']);
            Route::delete('/users/{id}', [UserManagementController::class, 'destroy']);
            
            // Role Assignment
            Route::put('/users/{id}/role', [UserManagementController::class, 'updateRole']);
            
            // System Logs
            Route::get('/logs', [AdminDashboardController::class, 'systemLogs'])
                ->middleware('can:viewSystemLogs,' . Manager::class);
            
            // Ride Monitoring
            Route::get('/rides', [RideController::class, 'adminRides']);
            Route::get('/rides/{id}', [RideController::class, 'adminRideDetail']);
            
            // Rider Approval
            Route::get('/riders/pending', [UserApprovalController::class, 'pendingRiders']);
            Route::put('/riders/{id}/approve', [UserApprovalController::class, 'approveRider']);
            Route::put('/riders/{id}/reject', [UserApprovalController::class, 'rejectRider']);
        });
    });

    // Super Admin Only
    Route::middleware(['auth:sanctum', 'role:super_admin'])->prefix('superadmin')->group(function () {
        // All superadmin features are covered in admin prefix
        // Additional superadmin-specific endpoints can be added here
    });

    /* ===========================
       ACCOUNTANT APIs
       =========================== */
       
    Route::middleware(['auth:sanctum', 'role:super_admin,accountant'])->group(function () {
        
        // Finance
        Route::prefix('finance')->group(function () {
            // Revenue Summary
            Route::get('/summary', [FinanceController::class, 'revenueSummary']);
            
            // Transactions
            Route::get('/transactions', [FinanceController::class, 'transactions']);
            
            // Export Reports
            Route::get('/export', [ExportController::class, 'export'])
                ->middleware('can:exportFinance,' . Manager::class);
            Route::get('/export/pdf', [ExportController::class, 'exportPdf'])
                ->middleware('can:exportFinance,' . Manager::class);
            Route::get('/export/csv', [ExportController::class, 'exportCsv'])
                ->middleware('can:exportFinance,' . Manager::class);
        });

        Route::middleware(['role:accountant'])->prefix('accountant')->group(function () {
            Route::get('/daily-earnings', [PayoutController::class, 'dailyEarnings']);
            Route::get('/daily-earnings/export', [PayoutController::class, 'exportDailyEarningsCsv']);
            Route::post('/payout/{driver}', [PayoutController::class, 'payout']);
            Route::post('/bulk-payout', [PayoutController::class, 'bulkPayout']);
            Route::get('/commissions', [PayoutController::class, 'commissionSummary']);
        });
    });

    /* ===========================
       OFFICER (Ticket Provider) APIs
       =========================== */
       
    Route::middleware(['auth:sanctum', 'role:super_admin,admin,officer'])->group(function () {
        
        // Tickets
        Route::prefix('tickets')->group(function () {
            // CRUD
            Route::post('/', [TicketController::class, 'store']);
            Route::get('/', [TicketController::class, 'index']);
            Route::get('/{id}', [TicketController::class, 'show']);
            Route::put('/{id}', [TicketController::class, 'update']);
            Route::delete('/{id}', [TicketController::class, 'destroy']);
            
            // Validation
            Route::put('/{id}/validate', [TicketController::class, 'validate']);
        });
    });

    /* ===========================
       ANALYTICS API (SUPER_ADMIN + ACCOUNTANT)
       =========================== */

    Route::middleware(['auth:sanctum', 'role:super_admin,accountant'])
        ->prefix('analytics')
        ->group(function () {
            Route::get('/revenue',            [AnalyticsController::class, 'revenue']);
            Route::get('/driver-performance', [AnalyticsController::class, 'driverPerformance']);
            Route::get('/commission-trend',   [AnalyticsController::class, 'commissionTrend']);
            Route::get('/fraud-risk',         [AnalyticsController::class, 'fraudRisk']);
        });

    /* ===========================
       HEALTH CHECK API (internal / monitoring)
       =========================== */

    Route::prefix('health')->group(function () {
        Route::get('/finance',    [HealthCheckController::class, 'finance']);
        Route::get('/settlement', [HealthCheckController::class, 'settlement']);
        Route::get('/warehouse',  [HealthCheckController::class, 'warehouse']);
    });

    /* ===========================
       FALLBACK - 404 for API
       =========================== */
       
    Route::fallback(function () {
        return response()->json([
            'success' => false,
            'message' => 'API endpoint not found',
            'error' => [
                'code' => 'ENDPOINT_NOT_FOUND',
                'description' => 'The requested API endpoint does not exist'
            ]
        ], 404);
    });
});

/* ===========================
   API VERSION 2 (Future)
   =========================== */

// Uncomment when ready to release v2
// Route::prefix('v2')->group(function () {
//     // v2 endpoints
// });

/* ===========================
   DEPRECATED - Redirect to v1
   =========================== */

// Legacy route handling (for backward compatibility)
// These will redirect to v1 endpoints with deprecation headers
Route::prefix('api')->group(function () {
    Route::fallback(function () {
        return response()->json([
            'success' => false,
            'message' => 'API version not specified. Please use /api/v1/',
            'error' => [
                'code' => 'VERSION_REQUIRED',
                'description' => 'Please specify API version in the URL. Use /api/v1/ for current version.'
            ]
        ], 400);
    });
});
