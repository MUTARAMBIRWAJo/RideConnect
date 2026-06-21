<?php

use App\Http\Controllers\Api\Accountant\PayoutController;
use App\Http\Controllers\Api\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Api\Admin\DemandHeatmapController;
use App\Http\Controllers\Api\Admin\LiveRequestsController;
use App\Http\Controllers\Api\Admin\MapDataController;
use App\Http\Controllers\Api\Admin\RideRouteHistoryController;
use App\Http\Controllers\Api\Admin\UserApprovalController;
use App\Http\Controllers\Api\Admin\UserManagementController;
use App\Http\Controllers\Api\AIController;
use App\Http\Controllers\Api\LocationApiController;
use App\Http\Controllers\Api\Analytics\AnalyticsController;
use App\Http\Controllers\Api\AuthController as ApiAuthController;
use App\Http\Controllers\Api\BookingController;
use App\Http\Controllers\Api\DeviceTokenController;
use App\Http\Controllers\Api\DriverMatchingController;
use App\Http\Controllers\Api\DriverController;
use App\Http\Controllers\Api\DriverPublicBusController;
use App\Http\Controllers\Api\DriverPublicTransportController;
use App\Http\Controllers\Api\DriverTripController;
use App\Http\Controllers\Api\MotorcycleTripController;
use App\Http\Controllers\Api\PublicBusTripController;
use App\Http\Controllers\Api\RouteController;
use App\Http\Controllers\API\DriverLocationController;
use App\Http\Controllers\Api\DriverTrackingController;
use App\Http\Controllers\Api\Finance\ExportController;
use App\Http\Controllers\Api\Finance\FinanceController;
use App\Http\Controllers\Api\HealthCheckController;
use App\Http\Controllers\Api\MlController;
use App\Http\Controllers\Api\MobileDriverController;
use App\Http\Controllers\Api\MobileNotificationController;
use App\Http\Controllers\Api\MobilePassengerController;
use App\Http\Controllers\Api\OfficerPublicBusController;
use App\Http\Controllers\Api\OfficerPublicTransportController;
use App\Http\Controllers\Api\PassengerController;
use App\Http\Controllers\Api\PassengerPublicBusController;
use App\Http\Controllers\Api\PassengerMatchingController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\PricingController;
use App\Http\Controllers\Api\PublicTransportController;
use App\Http\Controllers\Api\RideController;
use App\Http\Controllers\Api\RiderController;
use App\Http\Controllers\Api\TicketController;
use App\Http\Controllers\Api\TripController;
use App\Http\Controllers\Api\TripStatusController;
use App\Http\Controllers\Api\TripSyncController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\UnifiedPassengerTripController;
use App\Http\Controllers\Api\UnifiedDriverTripController;
use App\Http\Controllers\Api\Webhooks\MTNWebhookController;
use App\Http\Controllers\Api\Webhooks\StripeWebhookController;
use App\Http\Controllers\Api\PaymentVerificationController;
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
   API V3 (Trip System V3 - Isolated)
   =========================== */
Route::prefix('v3')->middleware(['auth:sanctum'])->group(function () {
    Route::get('/trips', [\App\Http\Controllers\Api\V3\TripControllerV3::class, 'passengerTrips']);
    Route::post('/trips/motor-vehicle/request', [\App\Http\Controllers\Api\V3\TripControllerV3::class, 'requestMotorVehicle']);
    Route::post('/trips/private-car/request', [\App\Http\Controllers\Api\V3\TripControllerV3::class, 'requestPrivateCar']);
    Route::post('/trips/public-bus/request', [\App\Http\Controllers\Api\V3\TripControllerV3::class, 'requestPublicBus']);
    
    // Driver Match Responses
    Route::get('/driver/trips', [\App\Http\Controllers\Api\V3\DriverTripControllerV3::class, 'driverTrips']);
    Route::get('/driver/trips/incoming', [\App\Http\Controllers\Api\V3\DriverTripControllerV3::class, 'incoming']);
    Route::post('/trips/{id}/accept', [\App\Http\Controllers\Api\V3\DriverTripControllerV3::class, 'accept']);
    Route::post('/trips/{id}/reject', [\App\Http\Controllers\Api\V3\DriverTripControllerV3::class, 'reject']);
    Route::post('/trips/{id}/cancel', [\App\Http\Controllers\Api\V3\DriverTripControllerV3::class, 'cancel']);
    Route::post('/trips/{id}/arrived', [\App\Http\Controllers\Api\V3\DriverTripControllerV3::class, 'arrived']);
    Route::post('/trips/{id}/start', [\App\Http\Controllers\Api\V3\DriverTripControllerV3::class, 'start']);
    Route::post('/trips/{id}/complete', [\App\Http\Controllers\Api\V3\DriverTripControllerV3::class, 'complete']);
    Route::post('/trips/{id}/pay', [\App\Http\Controllers\Api\V3\DriverTripControllerV3::class, 'pay']);
    Route::post('/trips/{id}/rate', [\App\Http\Controllers\Api\V3\DriverTripControllerV3::class, 'rate']);
    Route::post('/driver/location', [\App\Http\Controllers\Api\V3\DriverTripControllerV3::class, 'updateLocation']);
    
    // Status polling for fallback
    Route::get('/trips/{id}/matching-status', [\App\Http\Controllers\Api\V3\TripControllerV3::class, 'matchingStatus']);
    Route::get('/trips/{id}/status', [\App\Http\Controllers\Api\V3\TripControllerV3::class, 'status']);
    Route::post('/trips/{id}/notify-driver', [\App\Http\Controllers\Api\V3\TripControllerV3::class, 'notifyDriver']);
    Route::post('/trips/{id}/match', [\App\Http\Controllers\Api\V3\TripControllerV3::class, 'matchTrip']);
    Route::get('/drivers/online', [\App\Http\Controllers\Api\V3\TripControllerV3::class, 'onlineDrivers']);
    Route::post('/trips/{id}/select-driver', [\App\Http\Controllers\Api\V3\TripControllerV3::class, 'selectDriver']);

    // Notifications
    Route::get('/driver/notifications', [\App\Http\Controllers\Api\V3\DriverTripControllerV3::class, 'notifications']);
    Route::get('/notifications/unread-count', [\App\Http\Controllers\Api\V3\DriverTripControllerV3::class, 'unreadCount']);
    Route::get('/notifications', [\App\Http\Controllers\Api\V3\DriverTripControllerV3::class, 'userNotifications']);
    Route::put('/notifications/read-all', [\App\Http\Controllers\Api\V3\DriverTripControllerV3::class, 'markAllAsRead']);
    Route::delete('/notifications/clear-actioned', [\App\Http\Controllers\Api\V3\DriverTripControllerV3::class, 'clearActioned']);
    Route::put('/notifications/{id}/read', [\App\Http\Controllers\Api\V3\DriverTripControllerV3::class, 'markNotificationAsRead'])->whereNumber('id');
    Route::delete('/notifications/{id}', [\App\Http\Controllers\Api\V3\DriverTripControllerV3::class, 'deleteNotification'])->whereNumber('id');

    // Location Tracking
    Route::post('/location/update', [\App\Http\Controllers\Api\V3\LocationTrackingControllerV3::class, 'updateLocation']);
    Route::get('/location/live/{userId}', [\App\Http\Controllers\Api\V3\LocationTrackingControllerV3::class, 'getLiveLocation'])->whereNumber('userId');
    Route::get('/location/history/{userId}', [\App\Http\Controllers\Api\V3\LocationTrackingControllerV3::class, 'getLocationHistory'])->whereNumber('userId');
});

/* ===========================
   PAYMENT WEBHOOKS (No auth — verified by signature/key)
   =========================== */

Route::prefix('webhooks')->group(function () {
    Route::post('/stripe', [StripeWebhookController::class, 'handle'])->name('webhooks.stripe');
    Route::post('/mtn', [MTNWebhookController::class,   'handle'])->name('webhooks.mtn');
});

Route::get('/rides', [RideController::class, 'index']);
Route::get('/system/firebase-health', [\App\Http\Controllers\SystemHealthController::class, 'firebaseHealth']);
Route::get('/realtime-config', function () {
    return response()->json([
        'enabled' => (bool) config('realtime.enabled'),
        'provider' => config('realtime.provider'),
        'host' => config('realtime.host'),
        'port' => (int) config('realtime.port'),
        'scheme' => config('realtime.scheme'),
        'ws_path' => config('realtime.ws_path'),
        'key' => env('REVERB_APP_KEY'),
    ]);
});

Route::middleware(['auth:sanctum'])->group(function () {
    Route::post('/driver/location', [DriverLocationController::class, 'update']);
    Route::get('/driver/{driverId}/location', [DriverLocationController::class, 'getLocation']);
    Route::get('/drivers/nearby', [DriverLocationController::class, 'getNearbyDrivers']);

    // Deprecated: Use /mobile/tracking/* endpoints instead
    Route::get('/driver-tracking/{driverId}', [DriverTrackingController::class, 'getDriverLocation']);
    Route::get('/driver-tracking/trip/{tripId}', [DriverTrackingController::class, 'getTripDriverLocation']);
});

Route::prefix('v2')->middleware(['auth:sanctum', 'mobile'])->group(function () {
    Route::post('trips', [TripController::class, 'store']);
    Route::get('trips/{trip}', [TripController::class, 'show'])->whereNumber('trip');
    Route::put('trips/{trip}/respond', [DriverTripController::class, 'respond'])->whereNumber('trip');
    Route::put('trips/{trip}/status', [TripStatusController::class, 'update'])->whereNumber('trip');
    Route::post('trips/{trip}/cancel', [TripController::class, 'cancel'])->whereNumber('trip');

    Route::post('driver/location', [DriverLocationController::class, 'update']);
    Route::get('driver/location/{driver_id}', [DriverLocationController::class, 'show'])->whereNumber('driver_id');

    Route::get('notifications', [MobileNotificationController::class, 'index']);
    Route::put('notifications/{id}/read', [MobileNotificationController::class, 'markAsRead'])->whereNumber('id');
});

Route::middleware(['auth:sanctum', 'role:super_admin,admin'])->prefix('admin')->group(function () {
    Route::get('/map-data', [MapDataController::class, 'index']);
    Route::get('/demand-heatmap', [DemandHeatmapController::class, 'index']);
    Route::get('/live-requests', [LiveRequestsController::class, 'index']);
    Route::get('/rides/{ride}/route-history', [RideRouteHistoryController::class, 'show']);
    Route::get('/matching/debug/{tripId}', [\App\Http\Controllers\Admin\AdminMatchingMetricsController::class, 'matchingDebug']);
});

Route::prefix('v1')->group(function () {

    /* ===========================
       PUBLIC ROUTES - No Authentication
       =========================== */

    // Authentication
    Route::prefix('auth')->group(function () {
        // Generic mobile registration (role = DRIVER|PASSENGER)
        Route::post('/register', [ApiAuthController::class, 'register']);
        // Explicit role registration endpoints for Flutter apps
        Route::post('/register/driver', [ApiAuthController::class, 'registerDriver']);
        Route::post('/register/passenger', [ApiAuthController::class, 'registerPassenger']);

        // Legacy email login
        Route::post('/login', [ApiAuthController::class, 'login']);
        // Flutter/mobile login (email or phone)
        Route::post('/mobile/login', [ApiAuthController::class, 'mobileLogin']);
        
        // Password Reset
        Route::post('/forgot-password', [ApiAuthController::class, 'forgotPassword']);
        Route::post('/reset-password', [ApiAuthController::class, 'resetPassword']);
        Route::get('/verify-reset-token/{token}', [ApiAuthController::class, 'verifyResetToken']);
    });
    // Shared pricing calculator for UI auto pricing
    Route::post('/pricing/calculate', [PricingController::class, 'calculate']);

    // Realtime config — Flutter calls this to decide WS vs polling.
    /* ===========================
       MOBILE APP - SANCTUM AUTHENTICATED
       =========================== */

    Route::middleware(['auth:sanctum'])->group(function () {

        Route::middleware(['mobile'])->group(function () {
            Route::post('/trips', [TripController::class, 'store']);
            Route::get('/trips/{trip}', [TripController::class, 'show'])->whereNumber('trip');
            Route::put('/trips/{trip}/respond', [DriverTripController::class, 'respond'])->whereNumber('trip');
            Route::put('/trips/{trip}/status', [TripStatusController::class, 'update'])->whereNumber('trip');
            Route::post('/trips/{trip}/cancel', [TripController::class, 'cancel'])->whereNumber('trip');

            Route::post('/driver/location', [DriverLocationController::class, 'update']);
            Route::get('/driver/location/{driver_id}', [DriverLocationController::class, 'show'])->whereNumber('driver_id');

            Route::get('/notifications', [MobileNotificationController::class, 'index']);
            Route::put('/notifications/{id}/read', [MobileNotificationController::class, 'markAsRead'])->whereNumber('id');
        });

        /* ---- Authentication ---- */
        Route::prefix('auth')->group(function () {
            // Logout
            Route::post('/logout', [ApiAuthController::class, 'logout']);
            // Clear session/tokens (Flutter AuthSession.clear)
            Route::post('/session/clear', [ApiAuthController::class, 'clearSession']);
            // Validate bearer token before protected API calls
            Route::get('/token/validate', [ApiAuthController::class, 'validateToken']);
            // Get profile
            Route::get('/profile', [ApiAuthController::class, 'profile']);
            // Update profile
            Route::put('/profile', [ApiAuthController::class, 'updateProfile']);
        });

        /* ===========================
           ROUTE & NAVIGATION APIs
           =========================== */
        Route::prefix('route')->group(function () {
            Route::post('/compute', [RouteController::class, 'compute']);
            Route::get('/distance', [RouteController::class, 'distance']);
            Route::get('/duration', [RouteController::class, 'duration']);
            Route::get('/polyline', [RouteController::class, 'polyline']);
        });

        /* ===========================
           PASSENGER APIs
           =========================== */
        Route::prefix('passenger')->group(function () {
            // Profile
            Route::get('/profile', [PassengerController::class, 'profile']);
            Route::match(['put', 'patch'], '/profile', [PassengerController::class, 'updateProfile'])
                ->name('passenger.profile.update');
            Route::get('/stats', [PassengerController::class, 'stats']);


            Route::prefix('public-bus')->group(function () {
                // Corridor listing
                Route::get('/corridors', [PassengerPublicBusController::class, 'corridors']);
                Route::get('/corridors/{corridor}/stops', [PassengerPublicBusController::class, 'stops']);
                Route::get('/corridors/{corridor}/active-buses', [PassengerPublicBusController::class, 'activeBuses']);

                // Smart trip request and matching
                Route::post('/request', [PassengerPublicBusController::class, 'requestTrip'])
                    ->name('passenger.public-bus.request');
                Route::post('/trip-request', [\App\Http\Controllers\Api\PublicBusTripController::class, 'tripRequest'])
                    ->name('passenger.public-bus.trip-request');
                Route::get('/requests/{id}', [PassengerPublicBusController::class, 'showRequest'])
                    ->name('passenger.public-bus.show-request');

                // Test geocoding endpoint
                Route::get('/test-geocode', [PassengerPublicBusController::class, 'testGeocode'])
                    ->name('passenger.public-bus.test-geocode');

                // Seat booking
                Route::post('/book-seat', [PassengerPublicBusController::class, 'bookSeat']);
                Route::get('/trips/current', [PassengerPublicBusController::class, 'currentTrip']);
                Route::get('/tickets/{ticket}', [PassengerPublicBusController::class, 'ticket']);
            });

            // Motorcycle/Motor-vehicle Trip Routes
            Route::prefix('motor-vehicle')->group(function () {
                Route::post('/trip-requests', [MotorcycleTripController::class, 'store']);
                // Passenger-facing poll of a motorcycle/motor-vehicle trip (MotorcycleTrip model).
                // Required because TripSyncController operates on the Trip model and cannot read motorcycle_trips.
                Route::get('/trip-requests/{id}', [MotorcycleTripController::class, 'show'])->whereNumber('id');
                Route::post('/trip-requests/{id}/cancel', [MotorcycleTripController::class, 'cancel'])->whereNumber('id');
                Route::post('/trip-requests/{id}/rate', [MotorcycleTripController::class, 'rate'])->whereNumber('id');
            });

            // Standardized Unified Passenger Trip APIs (Fallback for other types)
            Route::prefix('{type}')->group(function () {
                Route::get('/trip-requests/{id}', [UnifiedPassengerTripController::class, 'show'])->whereNumber('id');
                Route::get('/trip-history', [UnifiedPassengerTripController::class, 'history']);
                Route::post('/trip-request', [UnifiedPassengerTripController::class, 'store']);
                Route::post('/trip-cancel', [UnifiedPassengerTripController::class, 'cancel']);
                Route::post('/trip-rate/{id}', [\App\Http\Controllers\Api\ReviewController::class, 'store'])->whereNumber('id');
            });

            Route::get('/public-transport/corridors', [PassengerController::class, 'corridors']);
            Route::get('/public-transport/routes', [PassengerController::class, 'routes']);
            Route::get('/public-transport/available', [PublicTransportController::class, 'available']);
            Route::get('/trips/current', [PublicTransportController::class, 'currentTrip']);
            Route::get('/trips/{trip}/ticket', [PublicTransportController::class, 'ticket'])->whereNumber('trip');
            Route::post('/trips/{trip}/feedback', [PublicTransportController::class, 'feedback'])->whereNumber('trip');

            // Public Bus Trip Execution
            Route::post('/trips/{id}/board', [\App\Http\Controllers\Api\PublicBusTripController::class, 'board'])->whereNumber('id');
            Route::post('/trips/{id}/start', [\App\Http\Controllers\Api\PublicBusTripController::class, 'start'])->whereNumber('id');
            Route::post('/trips/{id}/complete', [\App\Http\Controllers\Api\PublicBusTripController::class, 'complete'])->whereNumber('id');

            // Rides - Discovery + Booking
            Route::get('/rides/available', [RideController::class, 'index']);
            Route::post('/rides', [RideController::class, 'bookRide']);
            Route::get('/rides', [PassengerController::class, 'rideHistory']);
            Route::get('/drivers/online', [PassengerController::class, 'onlineDrivers']);
            Route::get('/drivers/match', [DriverMatchingController::class, 'index']);
            Route::post('/ride-requests', [PassengerController::class, 'requestRide']);

            // Ride History
            Route::get('/rides/history', [PassengerController::class, 'rideHistory']);
            Route::get('/rides/{id}', [RideController::class, 'showRide'])->whereNumber('id');
Route::post('/matching/driver/{tripId}', [PassengerMatchingController::class, 'match']); // Passenger driver matching
            Route::put('/rides/{id}/cancel', [RideController::class, 'cancelRide'])->whereNumber('id');

            // Bookings
            Route::get('/bookings', [BookingController::class, 'index']);
            Route::get('/bookings/my', [BookingController::class, 'myBookings']);
            Route::get('/bookings/{id}', [BookingController::class, 'show']);
            Route::post('/bookings', [BookingController::class, 'store']);
            Route::put('/bookings/{id}', [BookingController::class, 'update']);
            Route::put('/bookings/{id}/cancel', [BookingController::class, 'cancel']);

            // Trips
            Route::get('/trips', [TripController::class, 'myTrips']);
            Route::get('/trips/{id}', [TripController::class, 'show'])->whereNumber('id');
            Route::get('/trips/{trip}/status', [TripSyncController::class, 'status'])->whereNumber('trip');
            Route::get('/trips/{trip}/matching-session', [TripSyncController::class, 'matchingSession'])->whereNumber('trip');
            Route::post('/trips', [TripController::class, 'store']); // ON_DEMAND only
            Route::post('/trips/create-from-booking', [TripController::class, 'createFromBooking']); // SCHEDULED only
            Route::put('/trips/{id}/cancel', [TripController::class, 'cancel'])->whereNumber('id');

            // Payments
            Route::post('/payments', [PaymentController::class, 'createPayment']);
            Route::get('/payments/history', [PaymentController::class, 'paymentHistory']);
            Route::get('/payments/{id}', [PaymentController::class, 'show'])->whereNumber('id');
            
            // Payment Verification (MTN MoMo Pay Code)
            Route::get('/payment-verification/trips/{tripId}/instructions', [PaymentVerificationController::class, 'getPaymentInstructions'])->whereNumber('tripId');
            Route::post('/payment-verification/submit', [PaymentVerificationController::class, 'submitPaymentEvidence']);
            Route::get('/payment-verification/submissions/{submissionId}', [PaymentVerificationController::class, 'getSubmissionStatus'])->whereNumber('submissionId');
            Route::get('/payment-verification/submissions', [PaymentVerificationController::class, 'getUserSubmissions']);
        });

        /* ===========================
           DRIVER APIs
           =========================== */
        Route::prefix('driver')->group(function () {
            // Profile + stats
            Route::get('/profile', [DriverController::class, 'profile']);
            Route::put('/profile', [DriverController::class, 'updateProfile']);
            Route::get('/stats', [DriverController::class, 'stats']);

            Route::prefix('trips')->group(function () {
                Route::get('/active', [UnifiedDriverTripController::class, 'active']);
                Route::post('/{id}/accept', [UnifiedDriverTripController::class, 'accept'])->whereNumber('id');
                Route::post('/{id}/arrived', [UnifiedDriverTripController::class, 'arrived'])->whereNumber('id');
                Route::post('/{id}/start', [UnifiedDriverTripController::class, 'start'])->whereNumber('id');
                Route::post('/{id}/complete', [UnifiedDriverTripController::class, 'complete'])->whereNumber('id');
            });
            
            // Trip request decision endpoints - standardized API design
            Route::get('/trip-requests/assigned', [PublicBusTripController::class, 'getAssigned']);
            Route::post('/trip-requests/{id}/accept', [DriverPublicBusController::class, 'acceptTripRequest'])->whereNumber('id');
            Route::post('/trip-requests/{id}/reject', [DriverPublicBusController::class, 'rejectTripRequest'])->whereNumber('id');
            
            Route::prefix('public-bus')->group(function () {
                Route::post('/location', [DriverPublicBusController::class, 'location']);
                Route::post('/arrived-stop', [DriverPublicBusController::class, 'arrivedStop']);
                Route::post('/passenger-boarded', [DriverPublicBusController::class, 'passengerBoarded']);
                Route::post('/passenger-completed', [DriverPublicBusController::class, 'passengerCompleted']);
            });

            // Motorcycle/Motor-vehicle Trip Routes
            Route::prefix('motor-vehicle')->group(function () {
                Route::post('/trip-requests/{id}/accept', [MotorcycleTripController::class, 'accept'])->whereNumber('id');
                Route::post('/trip-requests/{id}/reject', [MotorcycleTripController::class, 'reject'])->whereNumber('id');
                Route::post('/trip-requests/{id}/arrived', [MotorcycleTripController::class, 'arrived'])->whereNumber('id');
                Route::post('/trip-requests/{id}/start', [MotorcycleTripController::class, 'start'])->whereNumber('id');
                Route::post('/trip-requests/{id}/complete', [MotorcycleTripController::class, 'complete'])->whereNumber('id');
            });

            // Location Tracking Routes
            Route::prefix('location')->group(function () {
                Route::post('/update', [DriverLocationController::class, 'update']);
                Route::get('/current', [DriverLocationController::class, 'current']);
            });

            Route::post('/status', [DriverPublicTransportController::class, 'updateStatus']);
            Route::get('/assignment/current', [DriverPublicTransportController::class, 'currentAssignment']);
            Route::post('/assignments/{attempt}/accept', [DriverPublicTransportController::class, 'accept'])->whereNumber('attempt');
            Route::post('/assignments/{attempt}/reject', [DriverPublicTransportController::class, 'reject'])->whereNumber('attempt');
            Route::post('/trips/{trip}/pickup-verify', [DriverPublicTransportController::class, 'pickupVerify'])->whereNumber('trip');
            Route::post('/trips/{trip}/start', [DriverPublicTransportController::class, 'start'])->whereNumber('trip');
            Route::post('/trips/{trip}/complete', [DriverPublicTransportController::class, 'complete'])->whereNumber('trip');

            // Rides managed by the driver
            Route::get('/rides', [RideController::class, 'myRides']);
            Route::post('/rides', [RideController::class, 'store']);
            Route::put('/rides/{id}', [RideController::class, 'update']);
            Route::delete('/rides/{id}', [RideController::class, 'destroy']);

            // Operational data
            Route::get('/bookings', [DriverController::class, 'bookings']);
            Route::put('/bookings/{id}/confirm', [BookingController::class, 'confirm']);
            Route::put('/bookings/{id}/cancel', [BookingController::class, 'cancel']);
            Route::get('/trips', [DriverController::class, 'myTrips']);
            Route::get('/trip-requests', [RiderController::class, 'rideRequests']);
            
            // Legacy PUT routes (kept for backwards compatibility with older mobile clients)
            // NOTE: POST routes for /trip-requests/{id}/accept|reject are defined above
            Route::put('/trip-requests/{id}/accept', [RiderController::class, 'acceptRequest']);
            Route::put('/trip-requests/{id}/reject', [RiderController::class, 'rejectRequest']);
            Route::put('/trip-requests/{id}/complete', [RiderController::class, 'completeRequest']);
            Route::put('/trips/{id}/accept', [TripController::class, 'accept']); // Driver accepts trip
            Route::put('/trips/{id}/start', [TripController::class, 'start']); // Driver starts trip
            Route::put('/trips/{id}/complete', [TripController::class, 'complete']); // Driver completes trip
            Route::put('/trips/{id}/cancel', [TripController::class, 'cancel']);
            Route::put('/status', [RiderController::class, 'updateStatus']);
            Route::get('/requests', [RiderController::class, 'rideRequests']);
            Route::put('/requests/{id}/accept', [RiderController::class, 'acceptRequest']);
            Route::put('/requests/{id}/reject', [RiderController::class, 'rejectRequest']);
            Route::put('/requests/{id}/complete', [RiderController::class, 'completeRequest']);

            // Earnings + documents
            Route::get('/earnings', [RiderController::class, 'earnings']);
            Route::get('/earnings/monthly', [RiderController::class, 'monthlyEarnings']);
            Route::post('/documents', [RiderController::class, 'uploadDocument']);
            Route::get('/documents', [RiderController::class, 'listDocuments']);
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

        Route::prefix('officer')->group(function () {
            Route::prefix('public-bus')->group(function () {
                Route::post('/corridors', [OfficerPublicBusController::class, 'corridors']);
                Route::post('/stops', [OfficerPublicBusController::class, 'stops']);
                Route::post('/assign-driver', [OfficerPublicBusController::class, 'assignDriver']);
                Route::get('/live-monitoring', [OfficerPublicBusController::class, 'liveMonitoring']);
            });
        });

        /* ===========================
           SHARED - All Authenticated Users
           =========================== */

        // User Profile (alias for /auth/profile)
        Route::get('/user/profile', [ApiAuthController::class, 'profile']);
        Route::put('/user/password', [UserController::class, 'updatePassword']);

        // Mobile app notifications (driver/passenger)
        Route::get('/notifications', [MobileNotificationController::class, 'index']);
        Route::get('/notifications/unread-count', [MobileNotificationController::class, 'unreadCount']);
        Route::put('/notifications/{id}/read', [MobileNotificationController::class, 'markAsRead']);
        Route::post('/notifications/{id}/acknowledged', [TripSyncController::class, 'acknowledgeNotification'])->whereNumber('id');
        Route::put('/notifications/{id}/acknowledge', [TripSyncController::class, 'acknowledgeNotificationPUT'])->whereNumber('id');
        Route::put('/notifications/read-all', [MobileNotificationController::class, 'markAllAsRead']);
        Route::delete('/notifications/clear-actioned', [MobileNotificationController::class, 'clearActioned']);
        Route::delete('/notifications/{id}', [MobileNotificationController::class, 'destroy']);
        Route::post('/trips/{trip}/acknowledge', [TripSyncController::class, 'acknowledgeTrip'])->whereNumber('trip');

        // Mobile push token registration (FCM/APNs)
        Route::post('/devices/push-token', [DeviceTokenController::class, 'store']);
        Route::delete('/devices/push-token/{token}', [DeviceTokenController::class, 'destroy']);

        /* ===========================
           MOBILE APP APIs - Flutter Optimized
           =========================== */
        Route::prefix('mobile')->group(function () {
            // Passenger Mobile APIs
            Route::get('/rides', [MobilePassengerController::class, 'getRides']);
            Route::get('/drivers/match', [DriverMatchingController::class, 'index']);
            Route::post('/bookings', [MobilePassengerController::class, 'createBooking']);
            Route::post('/trips/request', [MobilePassengerController::class, 'requestTrip']);
            Route::get('/trips/current', [MobilePassengerController::class, 'getCurrentTrip']);
            Route::get('/trips/{id}/track', [MobilePassengerController::class, 'trackTrip'])->whereNumber('id');
            Route::put('/trips/{id}/cancel', [MobilePassengerController::class, 'cancelTrip'])->whereNumber('id');
            Route::put('/trips/{id}/complete', [MobilePassengerController::class, 'completeTrip'])->whereNumber('id');

            // Driver Mobile APIs
            Route::prefix('drivers')->group(function () {
                Route::post('/status', [MobileDriverController::class, 'updateStatus']);
                Route::get('/trips', [MobileDriverController::class, 'getAvailableTrips']);
                Route::post('/trips/{id}/accept', [MobileDriverController::class, 'acceptTrip'])->whereNumber('id');
                Route::post('/trips/{id}/reject', [MobileDriverController::class, 'rejectTrip'])->whereNumber('id');
                Route::post('/location', [MobileDriverController::class, 'updateLocation']);
                Route::post('/live-location', [MobileDriverController::class, 'updateLiveLocation']);
                Route::put('/trips/{id}/start', [MobileDriverController::class, 'startTrip'])->whereNumber('id');
                Route::put('/trips/{id}/complete', [MobileDriverController::class, 'completeTrip'])->whereNumber('id');
                Route::put('/trips/{id}/cancel', [MobileDriverController::class, 'cancelTrip'])->whereNumber('id');
            });

            // Real-time Driver Tracking APIs (for passengers)
            Route::prefix('tracking')->group(function () {
                Route::get('/driver/{driverId}', [DriverTrackingController::class, 'getDriverLocation']);
                Route::get('/trip/{tripId}', [DriverTrackingController::class, 'getTripDriverLocation']);
                Route::get('/nearby', [DriverTrackingController::class, 'getNearbyDrivers']);
            });
        });

        // Internal AI integration endpoints
        Route::prefix('ai')->group(function () {
            Route::post('/match-driver', [AIController::class, 'matchDriver']);
            Route::post('/predict-eta', [AIController::class, 'predictETA']);
            Route::post('/predict-demand', [AIController::class, 'predictDemand']);
            Route::post('/calculate-surge', [AIController::class, 'calculateSurge']);
            Route::post('/optimize-route', [AIController::class, 'optimizeRoute']);
            Route::post('/analyze-driver', [AIController::class, 'analyzeDriver']);
            Route::post('/detect-fare-anomaly', [AIController::class, 'detectFareAnomaly']);
            Route::get('/driver-redistribution', [AIController::class, 'driverRedistribution']);
            Route::post('/route-monitor', [AIController::class, 'routeMonitor']);
            Route::get('/driver-idle', [AIController::class, 'driverIdle']);
            Route::get('/cancellation-anomalies', [AIController::class, 'cancellationAnomalies']);
            Route::get('/system-health', [AIController::class, 'systemHealth']);
        });

        // FastAPI ML proxy endpoints
        Route::prefix('ml')->group(function () {
            Route::post('/predict-fare', [MlController::class, 'predictFare']);
            Route::post('/rank-drivers', [MlController::class, 'rankDrivers']);
            Route::post('/predict-demand', [MlController::class, 'predictDemand']);
            Route::post('/detect-anomaly', [MlController::class, 'detectAnomaly']);
            Route::get('/health', [MlController::class, 'health']);
            Route::post('/reload-models', [MlController::class, 'reloadModels']);
            Route::post('/retrain', [MlController::class, 'retrain']);
        });
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
                ->middleware('can:viewAdminDashboard,'.Manager::class);
            Route::get('/dashboard/demand-notifications', [AdminDashboardController::class, 'demandNotifications'])
                ->middleware('can:viewAdminDashboard,'.Manager::class);

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
                ->middleware('can:viewSystemLogs,'.Manager::class);

            // Ride Monitoring
            Route::get('/rides', [RideController::class, 'adminRides']);
            Route::get('/rides/{id}', [RideController::class, 'adminRideDetail']);
            Route::post('/rides/corridor', [RideController::class, 'createRideWithCorridor']);

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
                ->middleware('can:exportFinance,'.Manager::class);
            Route::get('/export/pdf', [ExportController::class, 'exportPdf'])
                ->middleware('can:exportFinance,'.Manager::class);
            Route::get('/export/csv', [ExportController::class, 'exportCsv'])
                ->middleware('can:exportFinance,'.Manager::class);
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

        // Officer Booking & Trip Management
        Route::prefix('officer')->name('officer.')->group(function () {
            // Passenger management
            Route::get('/passengers', [\App\Http\Controllers\Api\OfficerBookingTripController::class, 'getPassengers'])->name('passengers');
            Route::post('/passengers', [\App\Http\Controllers\Api\OfficerBookingTripController::class, 'createPassenger'])->name('passengers.create');

            // Public transport operations
            Route::get('/public-transport/available', [OfficerPublicTransportController::class, 'available'])->name('public-transport.available');
            Route::post('/assisted-bookings', [OfficerPublicTransportController::class, 'assistedBooking'])->name('assisted-bookings');
            Route::post('/trips/{trip}/reassign', [OfficerPublicTransportController::class, 'reassign'])->whereNumber('trip')->name('trips.reassign');
            Route::post('/payments/{payment}/verify', [OfficerPublicTransportController::class, 'verifyPayment'])->whereNumber('payment')->name('payments.verify');
            Route::get('/seat-monitoring', [OfficerPublicTransportController::class, 'seatMonitoring'])->name('seat-monitoring');

            // Location & Corridor search
            Route::get('/corridors', [\App\Http\Controllers\Api\OfficerBookingTripController::class, 'getCorridors'])->name('corridors');
            Route::get('/locations/search', [\App\Http\Controllers\Api\OfficerBookingTripController::class, 'searchLocations'])->name('locations.search');
            Route::get('/rides/available', [\App\Http\Controllers\Api\OfficerBookingTripController::class, 'getAvailableRides'])->name('rides.available');

            // Booking & Trip creation
            Route::post('/bookings', [\App\Http\Controllers\Api\OfficerBookingTripController::class, 'createBooking'])->name('bookings');
            Route::post('/trips', [\App\Http\Controllers\Api\OfficerBookingTripController::class, 'createTrip'])->name('trips');
        });
    });

    /* ===========================
       ANALYTICS API (SUPER_ADMIN + ACCOUNTANT)
       =========================== */

    Route::middleware(['auth:sanctum', 'role:super_admin,accountant'])
        ->prefix('analytics')
        ->group(function () {
            Route::get('/revenue', [AnalyticsController::class, 'revenue']);
            Route::get('/driver-performance', [AnalyticsController::class, 'driverPerformance']);
            Route::get('/commission-trend', [AnalyticsController::class, 'commissionTrend']);
            Route::get('/fraud-risk', [AnalyticsController::class, 'fraudRisk']);
        });

    /* ===========================
       HEALTH CHECK API (internal / monitoring)
       =========================== */

    Route::prefix('health')->group(function () {
        Route::get('/finance', [HealthCheckController::class, 'finance']);
        Route::get('/settlement', [HealthCheckController::class, 'settlement']);
        Route::get('/warehouse', [HealthCheckController::class, 'warehouse']);
    });

    /* ===========================
       LOCATION API (Places / Geocoding — all roles)
       =========================== */

    Route::prefix('locations')->group(function () {
        // Public — no auth required for geocoding help
        Route::get('/search', [\App\Http\Controllers\Api\LocationApiController::class, 'search'])
            ->name('locations.search');
        Route::get('/place-details', [\App\Http\Controllers\Api\LocationApiController::class, 'placeDetails'])
            ->name('locations.place_details');
        Route::get('/reverse-geocode', [\App\Http\Controllers\Api\LocationApiController::class, 'reverseGeocode'])
            ->name('locations.reverse_geocode');
        Route::get('/geocode', [\App\Http\Controllers\Api\LocationApiController::class, 'geocode'])
            ->name('locations.geocode');
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
                'description' => 'The requested API endpoint does not exist',
            ],
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
                'description' => 'Please specify API version in the URL. Use /api/v1/ for current version.',
            ],
        ], 400);
    });
});
