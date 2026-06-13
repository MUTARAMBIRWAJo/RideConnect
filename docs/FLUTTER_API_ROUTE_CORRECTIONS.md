# RideConnect Flutter API Route Corrections

**Generated:** June 12, 2026  
**Verified against:** `routes/api.php`, `lib/config/api_config.dart`, `passenger_api.fixed.dart`

This document maps every endpoint in the Flutter API list to the **correct working backend route**.  
Endpoints marked **KEEP** are already correct (after adding the required `/api/v1` prefix).  
Endpoints marked **REPLACE** must be updated in Flutter.

---

## Global conventions (apply everywhere)

| Rule | Correct value |
|------|----------------|
| **Main API base** | `https://rideconnect-emp0.onrender.com/api/v1` |
| **Mobile API base** | `https://rideconnect-emp0.onrender.com/api/v1/mobile` |
| **ML service base** | `https://ml-service-j72g.onrender.com` |
| **Auth header** | `Authorization: Bearer {sanctum_token}` |
| **Token type** | Laravel **Sanctum** bearer token — **not JWT** |
| **Content-Type** | `application/json` |
| **Accept** | `application/json` |

> **Important:** Paths in the Flutter list omit `/api/v1`. Every Laravel route below is the **full path from domain root**.

> **Important:** There is **no** `POST /auth/refresh` endpoint. Sanctum tokens do not auto-refresh. Use `GET /auth/token/validate` and re-login when invalid.

---

## Quick reference — what to use per transport type

| Transport | Passenger create | Passenger poll/status | Driver actions | Payment / rate |
|-----------|------------------|----------------------|----------------|----------------|
| **Motorcycle** | `POST /passenger/motor-vehicle/trip-requests` | `GET /passenger/motor-vehicle/trip-requests/{id}` | `POST /driver/motor-vehicle/trip-requests/{id}/*` | `POST /passenger/payments` + `POST .../rate` |
| **Public bus** | `POST /passenger/public-bus/request` | `GET /passenger/public-bus/requests/{id}` | `POST /driver/public-bus/*` | `POST /passenger/public-bus/book-seat` |
| **Private car (on-demand)** | `POST /mobile/trips/request` | `GET /mobile/trips/current`, `GET /mobile/trips/{id}/track` | `POST/PUT /mobile/drivers/trips/{id}/*` | `POST /passenger/payments` |
| **Private car (scheduled)** | `POST /passenger/bookings` | `GET /passenger/bookings/{id}` | `PUT /driver/bookings/{id}/confirm` | `POST /passenger/payments` |

**Do not mix trip IDs** across tables. Motorcycle IDs from `motorcycle_trips` will **404** on `/mobile/trips/*` and `/passenger/trips/*`.

---

## 1. Authentication

| # | Flutter list (wrong/incomplete) | Status | Correct endpoint | Method | Notes |
|---|--------------------------------|--------|------------------|--------|-------|
| 1 | `/auth/mobile/login` | **REPLACE** | `/api/v1/auth/mobile/login` | `POST` | Body: `{ "login": "email_or_phone", "password": "...", "device_name": "flutter" }` — field is **`login`**, not `email` |
| 2 | `/auth/login` | **REPLACE** | `/api/v1/auth/login` | `POST` | Web/legacy login; mobile apps should prefer `mobile/login` |
| 3 | `/auth/register` | **REPLACE** | `/api/v1/auth/register` | `POST` | Generic register (`role`: `DRIVER` \| `PASSENGER`) |
| 3b | *(missing)* | **ADD** | `/api/v1/auth/register/passenger` | `POST` | Explicit passenger registration |
| 3c | *(missing)* | **ADD** | `/api/v1/auth/register/driver` | `POST` | Explicit driver registration |
| 4 | `/auth/refresh` | **REPLACE** | *(no equivalent)* | — | **Does not exist.** Validate with `GET /auth/token/validate`; if 401, redirect to login |
| 4b | *(missing)* | **ADD** | `/api/v1/auth/session/clear` | `POST` | Revoke tokens on logout-all-devices `{ "all_devices": true }` |
| 5 | `/auth/logout` | **REPLACE** | `/api/v1/auth/logout` | `POST` | Revokes current Sanctum token |
| 6 | `/auth/token/validate` | **REPLACE** | `/api/v1/auth/token/validate` | `GET` | Auth required |
| 7 | `/auth/profile` | **REPLACE** | `/api/v1/auth/profile` | `GET` | Auth required |
| 8 | `/auth/profile` | **REPLACE** | `/api/v1/auth/profile` | `PUT` | Auth required |
| 8b | *(missing)* | **ADD** | `/api/v1/passenger/profile` | `GET` / `PUT` | Passenger-specific profile (preferred for passenger app) |
| 8c | *(missing)* | **ADD** | `/api/v1/driver/profile` | `GET` / `PUT` | Driver-specific profile (preferred for driver app) |
| 8d | *(missing)* | **ADD** | `/api/v1/devices/push-token` | `POST` | Register FCM token `{ "token": "...", "platform": "android\|ios" }` |

### Flutter `ApiConfig` — auth block

```dart
static const String apiRoot = 'https://rideconnect-emp0.onrender.com/api/v1';

static const String mobileLogin   = '$apiRoot/auth/mobile/login';
static const String register        = '$apiRoot/auth/register';
static const String registerPassenger = '$apiRoot/auth/register/passenger';
static const String registerDriver  = '$apiRoot/auth/register/driver';
static const String logout          = '$apiRoot/auth/logout';
static const String sessionClear    = '$apiRoot/auth/session/clear';
static const String validateToken   = '$apiRoot/auth/token/validate';
static const String authProfile     = '$apiRoot/auth/profile';
static const String pushToken       = '$apiRoot/devices/push-token';
// NO authRefresh — endpoint does not exist
```

---

## 2. Passenger — motorcycle (motor-vehicle)

| # | Flutter list | Status | Correct endpoint | Method | Notes |
|---|-------------|--------|------------------|--------|-------|
| 1 | `/passenger/motor-vehicle/trip-requests` | **KEEP** | `/api/v1/passenger/motor-vehicle/trip-requests` | `POST` | Creates record in `motorcycle_trips` |
| 2 | `/passenger/motor-vehicle/trip-requests/{id}` | **KEEP** | `/api/v1/passenger/motor-vehicle/trip-requests/{id}` | `GET` | **Single source of truth** for moto lifecycle polling |
| 3 | `PUT /passenger/motor-vehicle/trip-requests/{id}/cancel` | **REPLACE** | `/api/v1/passenger/motor-vehicle/trip-requests/{id}/cancel` | **`POST`** | Cancel is **POST**, not PUT |
| 4 | *(missing)* | **ADD** | `/api/v1/passenger/motor-vehicle/trip-requests/{id}/rate` | `POST` | After `COMPLETED`: `{ "rating": 1-5, "comment": "..." }` |
| 5 | *(missing)* | **ADD** | `/api/v1/passenger/payments` | `POST` | Pay for completed moto trip `{ "type": "motorcycle_trip", "motorcycle_trip_id": id, ... }` |

### Flutter constants

```dart
static String motoCreate() => '$apiRoot/passenger/motor-vehicle/trip-requests';
static String motoShow(int id) => '$apiRoot/passenger/motor-vehicle/trip-requests/$id';
static String motoCancel(int id) => '$apiRoot/passenger/motor-vehicle/trip-requests/$id/cancel'; // POST
static String motoRate(int id) => '$apiRoot/passenger/motor-vehicle/trip-requests/$id/rate';     // POST
```

---

## 3. Passenger — public bus

| # | Flutter list | Status | Correct endpoint | Method | Notes |
|---|-------------|--------|------------------|--------|-------|
| 1 | `/passenger/public-bus/request` | **KEEP** | `/api/v1/passenger/public-bus/request` | `POST` | Smart bus trip request |
| 2 | `/passenger/public-bus/requests/{id}` | **KEEP** | `/api/v1/passenger/public-bus/requests/{id}` | `GET` | Poll booking/request status |
| 3 | *(missing)* | **ADD** | `/api/v1/passenger/public-bus/corridors` | `GET` | List corridors (auth required) |
| 4 | *(missing)* | **ADD** | `/api/v1/passenger/public-bus/corridors/{corridor}/stops` | `GET` | Stops for corridor |
| 5 | *(missing)* | **ADD** | `/api/v1/passenger/public-bus/corridors/{corridor}/active-buses` | `GET` | Active buses on corridor |
| 6 | *(missing)* | **ADD** | `/api/v1/passenger/public-bus/book-seat` | `POST` | Seat reservation |
| 7 | *(missing)* | **ADD** | `/api/v1/passenger/public-bus/trips/current` | `GET` | Current active bus trip |
| 8 | *(missing)* | **ADD** | `/api/v1/passenger/public-bus/tickets/{ticket}` | `GET` | Ticket details |

---

## 4. Passenger — general (legacy `trips` table)

| # | Flutter list | Status | Correct endpoint | Method | Notes |
|---|-------------|--------|------------------|--------|-------|
| 1 | `/passenger/trips` | **KEEP** | `/api/v1/passenger/trips` | `GET` | Trip history (`trips` table — **not motorcycle**) |
| 2 | `PUT /passenger/trips/{id}/cancel` | **KEEP** | `/api/v1/passenger/trips/{id}/cancel` | `PUT` | Cancels `trips` record only |
| 3 | *(missing)* | **ADD** | `/api/v1/passenger/trips/{id}` | `GET` | Trip detail (`trips` table) |
| 4 | *(missing)* | **ADD** | `/api/v1/passenger/trips/{trip}/status` | `GET` | Status sync for `trips` table |
| 5 | *(missing)* | **ADD** | `/api/v1/passenger/trips/{trip}/matching-session` | `GET` | Matching session ( **`trips` only**, not moto) |
| 6 | *(missing)* | **ADD** | `/api/v1/passenger/trips/{trip}/feedback` | `POST` | Public transport feedback/rating |

> **Do not use** `/passenger/trips` endpoints for motorcycle trips. Use motor-vehicle routes from section 2.

---

## 5. Driver endpoints

The Flutter list uses generic `/driver/trip-requests/{id}/*`. On the backend these routes serve **public bus** (`PublicBusTripController`), **not motorcycle**.

### 5a. Motorcycle driver — **REPLACE all generic driver trip-request paths**

| Flutter list (wrong for moto) | Correct endpoint | Method |
|--------------------------------|------------------|--------|
| `POST /driver/trip-requests/{id}/accept` | `/api/v1/driver/motor-vehicle/trip-requests/{id}/accept` | `POST` |
| `POST /driver/trip-requests/{id}/reject` | `/api/v1/driver/motor-vehicle/trip-requests/{id}/reject` | `POST` |
| `POST /driver/trip-requests/{id}/arrived` | `/api/v1/driver/motor-vehicle/trip-requests/{id}/arrived` | `POST` |
| `POST /driver/trip-requests/{id}/start` | `/api/v1/driver/motor-vehicle/trip-requests/{id}/start` | `POST` |
| `POST /driver/trip-requests/{id}/complete` | `/api/v1/driver/motor-vehicle/trip-requests/{id}/complete` | `POST` |

### 5b. Private car / on-demand driver — use **mobile** routes

| Flutter list (wrong method/path) | Correct endpoint | Method |
|----------------------------------|------------------|--------|
| `GET /mobile/drivers/trips/{id}/accept` | `/api/v1/mobile/drivers/trips/{id}/accept` | **`POST`** |
| `GET /mobile/drivers/trips/{id}/reject` | `/api/v1/mobile/drivers/trips/{id}/reject` | **`POST`** |
| `GET /mobile/drivers/trips/{id}/start` | `/api/v1/mobile/drivers/trips/{id}/start` | **`PUT`** |
| `GET /mobile/drivers/trips/{id}/complete` | `/api/v1/mobile/drivers/trips/{id}/complete` | **`PUT`** |
| *(missing)* | `/api/v1/mobile/drivers/trips` | `GET` | Available trip queue |
| *(missing)* | `/api/v1/mobile/drivers/trips/{id}/cancel` | `PUT` | Driver cancel |

### 5c. Public bus driver

| Correct endpoint | Method | Purpose |
|------------------|--------|---------|
| `/api/v1/driver/trip-requests/assigned` | `GET` | Assigned bus requests |
| `/api/v1/driver/trip-requests/{id}/accept` | `POST` | Accept bus assignment |
| `/api/v1/driver/trip-requests/{id}/reject` | `POST` | Reject bus assignment |
| `/api/v1/driver/public-bus/location` | `POST` | Bus GPS update |
| `/api/v1/driver/public-bus/arrived-stop` | `POST` | Arrived at stop |
| `/api/v1/driver/public-bus/passenger-boarded` | `POST` | Passenger boarded |
| `/api/v1/driver/public-bus/passenger-completed` | `POST` | Passenger trip done |

### 5d. Driver location

| Flutter list | Status | Correct endpoint | Method | Body fields |
|-------------|--------|------------------|--------|-------------|
| `POST /driver/location/update` | **KEEP** | `/api/v1/driver/location/update` | `POST` | `lat`, `lng`, optional `speed_kmh`, `heading`, `accuracy`, `trip_id` |
| *(better for mobile)* | **ADD** | `/api/v1/mobile/drivers/live-location` | `POST` | `lat`, `lng`, optional `speed_kmh`, `heading`, `accuracy`, `is_online`, `trip_id` |
| *(during active trip)* | **ADD** | `/api/v1/mobile/drivers/location` | `POST` | Requires `trip_id` + coordinates |

### 5e. Driver availability

| Flutter list | Status | Correct endpoint | Method | Notes |
|-------------|--------|------------------|--------|-------|
| `PUT /driver/availability` | **REPLACE** | `/api/v1/mobile/drivers/status` | **`POST`** | Body: `{ "status": "ONLINE" \| "OFFLINE" }` |
| *(alternate)* | **ADD** | `/api/v1/driver/status` | `POST` | Public-transport driver status |

---

## 6. Trip management (mobile — private car / `trips` table)

| # | Flutter list | Status | Correct endpoint | Method | Notes |
|---|-------------|--------|------------------|--------|-------|
| 1 | `/api/v1/mobile/trips/request` | **KEEP** | `/api/v1/mobile/trips/request` | `POST` | Create on-demand trip |
| 2 | `/api/v1/mobile/trips/current` | **KEEP** | `/api/v1/mobile/trips/current` | `GET` | Active trip for passenger |
| 3 | `/api/v1/mobile/trips/{id}` | **REPLACE** | `/api/v1/passenger/trips/{id}` | `GET` | **No** `GET /mobile/trips/{id}` — use passenger trips show |
| 4 | `/api/v1/mobile/trips/{id}/status` | **REPLACE** | `/api/v1/passenger/trips/{trip}/status` | `GET` | Status lives under `/passenger/trips`, not `/mobile/trips` |
| 5 | `GET /api/v1/mobile/trips/{id}/cancel` | **REPLACE** | `/api/v1/mobile/trips/{id}/cancel` | **`PUT`** | Wrong HTTP method in Flutter list |
| 6 | `/api/v1/mobile/trips/{id}/track` | **KEEP** | `/api/v1/mobile/trips/{id}/track` | `GET` | Live tracking data |
| 7 | `/trip-requests/{id}` | **REPLACE** | *(context-specific — see sections 2–5)* | — | Ambiguous legacy path; remove |
| 8 | `/route/get-route` | **REPLACE** | `/api/v1/route/compute` | **`POST`** | Primary route calculation |
| 8b | *(alternates)* | **ADD** | `/api/v1/route/distance` | `GET` | Query: origin/destination coords |
| 8c | *(alternates)* | **ADD** | `/api/v1/route/duration` | `GET` | ETA between points |
| 8d | *(alternates)* | **ADD** | `/api/v1/route/polyline` | `GET` | Encoded polyline |
| 9 | *(missing)* | **ADD** | `/api/v1/pricing/calculate` | `POST` | Fare estimate before booking |
| 10 | *(missing)* | **ADD** | `/api/v1/mobile/trips/{id}/complete` | `PUT` | Passenger-side trip complete |
| 11 | *(missing)* | **ADD** | `/api/v1/mobile/tracking/driver/{driverId}` | `GET` | Driver location for map |
| 12 | *(missing)* | **ADD** | `/api/v1/mobile/tracking/trip/{tripId}` | `GET` | Trip driver location |
| 13 | *(missing)* | **ADD** | `/api/v1/mobile/tracking/nearby` | `GET` | Nearby drivers |

### Fix `lib/config/api_config.dart` trip block

```dart
// KEEP
static const String requestTrip   = '/api/v1/mobile/trips/request';       // POST
static const String currentTrip   = '/api/v1/mobile/trips/current';       // GET
static String trackTrip(int id)   => '/api/v1/mobile/trips/$id/track';    // GET
static String cancelTrip(int id)  => '/api/v1/mobile/trips/$id/cancel';   // PUT (not GET)

// REPLACE — move off /mobile prefix
static String tripDetail(int id)  => '/api/v1/passenger/trips/$id';       // GET
static String tripStatus(int id)  => '/api/v1/passenger/trips/$id/status'; // GET
static String matchingSession(int tripId) =>
    '/api/v1/passenger/trips/$tripId/matching-session';                    // GET (trip id, not session id)

// REMOVE — does not exist
// GET /api/v1/mobile/trips/{id}
// GET /api/v1/mobile/trips/{id}/status
// GET /api/v1/mobile/trips/{id}/cancel
```

---

## 7. Driver matching

| # | Flutter list | Status | Correct endpoint | Method | Notes |
|---|-------------|--------|------------------|--------|-------|
| 1 | `/api/v1/mobile/drivers/match` | **KEEP** | `/api/v1/mobile/drivers/match` | `GET` | Query: `pickup_lat`, `pickup_lng`, `dropoff_lat`, `dropoff_lng`, `ride_type` |
| 2 | `GET .../drivers/trips/{id}/accept` | **REPLACE** | `/api/v1/mobile/drivers/trips/{id}/accept` | **`POST`** | |
| 3 | `GET .../drivers/trips/{id}/reject` | **REPLACE** | `/api/v1/mobile/drivers/trips/{id}/reject` | **`POST`** | |
| 4 | `GET .../drivers/trips/{id}/start` | **REPLACE** | `/api/v1/mobile/drivers/trips/{id}/start` | **`PUT`** | |
| 5 | `GET .../drivers/trips/{id}/complete` | **REPLACE** | `/api/v1/mobile/drivers/trips/{id}/complete` | **`PUT`** | |
| 6 | `/api/v1/mobile/trips/{sessionId}/matching-session` | **REPLACE** | `/api/v1/passenger/trips/{tripId}/matching-session` | `GET` | Use **trip ID**, path is under `/passenger`, not `/mobile` |
| 7 | *(alternate)* | **ADD** | `/api/v1/passenger/drivers/match` | `GET` | Same matching, passenger namespace |

---

## 8. ML service

| Flutter list | Status | Correct endpoint | Method | Notes |
|-------------|--------|------------------|--------|-------|
| `POST /predict` on ML service | **REPLACE** | `https://ml-service-j72g.onrender.com/rank-drivers` | `POST` | Direct TFLite ranking |
| *(missing)* | **ADD** | `https://ml-service-j72g.onrender.com/health` | `GET` | ML health check |
| *(recommended)* | **ADD** | `/api/v1/ml/rank-drivers` | `POST` | Laravel proxy (auth required) |
| *(recommended)* | **ADD** | `/api/v1/ml/predict-fare` | `POST` | Fare prediction via Laravel |
| *(recommended)* | **ADD** | `/api/v1/ai/match-driver` | `POST` | AI driver matching |

### Fix `api_config.dart` ML block

```dart
// REPLACE
static const String mlHealth       = '$mlServiceUrl/health';           // GET
static const String mlRankDrivers  = '$mlServiceUrl/rank-drivers';     // POST

// Optional Laravel proxy (requires auth token)
static const String apiMlRank      = '$apiRoot/ml/rank-drivers';       // POST
static const String apiMlFare      = '$apiRoot/ml/predict-fare';       // POST

// REMOVE — does not exist on ML service
// static const String mlPredictionUrl = '$mlServiceUrl/predict';
```

---

## 9. Notifications (add to Flutter — missing from original list)

| Endpoint | Method | Purpose |
|----------|--------|---------|
| `/api/v1/notifications` | `GET` | List notifications |
| `/api/v1/notifications/unread-count` | `GET` | Badge count |
| `/api/v1/notifications/{id}/read` | `PUT` | Mark read |
| `/api/v1/notifications/read-all` | `PUT` | Mark all read |
| `/api/v1/notifications/{id}/acknowledged` | `POST` | Delivery ack |
| `/api/v1/trips/{trip}/acknowledge` | `POST` | Trip state ack |

> Notifications live at `/api/v1/notifications` — **not** under `/passenger/notifications`.

---

## 10. Payments (add to Flutter — missing from original list)

| Endpoint | Method | Purpose |
|----------|--------|---------|
| `/api/v1/passenger/payments` | `POST` | Create payment |
| `/api/v1/passenger/payments/history` | `GET` | Payment history |
| `/api/v1/passenger/payments/{id}` | `GET` | Payment detail |

Example body for motorcycle trip:

```json
{
  "type": "motorcycle_trip",
  "motorcycle_trip_id": 123,
  "amount": 2500,
  "currency": "RWF",
  "payment_method": "mobile_money",
  "transaction_id": "momo-ref-001"
}
```

---

## 11. Complete corrected `ApiEndpoints` class (drop-in)

Replace conflicting constants in `lib/config/api_config.dart`:

```dart
class ApiEndpoints {
  static const String apiRoot = 'https://rideconnect-emp0.onrender.com/api/v1';
  static const String mobileRoot = '$apiRoot/mobile';

  // ── Auth ──────────────────────────────────────────────────────────
  static const String login          = '$apiRoot/auth/mobile/login';
  static const String register       = '$apiRoot/auth/register';
  static const String registerPassenger = '$apiRoot/auth/register/passenger';
  static const String registerDriver = '$apiRoot/auth/register/driver';
  static const String logout         = '$apiRoot/auth/logout';
  static const String sessionClear   = '$apiRoot/auth/session/clear';
  static const String validateToken  = '$apiRoot/auth/token/validate';
  static const String authProfile    = '$apiRoot/auth/profile';
  static const String pushToken      = '$apiRoot/devices/push-token';

  // ── Motorcycle passenger ───────────────────────────────────────────
  static const String motoCreate     = '$apiRoot/passenger/motor-vehicle/trip-requests';
  static String motoShow(int id)     => '$apiRoot/passenger/motor-vehicle/trip-requests/$id';
  static String motoCancel(int id) => '$apiRoot/passenger/motor-vehicle/trip-requests/$id/cancel'; // POST
  static String motoRate(int id)   => '$apiRoot/passenger/motor-vehicle/trip-requests/$id/rate';   // POST

  // ── Motorcycle driver ──────────────────────────────────────────────
  static String motoAccept(int id)   => '$apiRoot/driver/motor-vehicle/trip-requests/$id/accept';
  static String motoReject(int id)   => '$apiRoot/driver/motor-vehicle/trip-requests/$id/reject';
  static String motoArrived(int id)  => '$apiRoot/driver/motor-vehicle/trip-requests/$id/arrived';
  static String motoStart(int id)    => '$apiRoot/driver/motor-vehicle/trip-requests/$id/start';
  static String motoComplete(int id) => '$apiRoot/driver/motor-vehicle/trip-requests/$id/complete';

  // ── Public bus passenger ───────────────────────────────────────────
  static const String busRequest     = '$apiRoot/passenger/public-bus/request';
  static String busShow(int id)      => '$apiRoot/passenger/public-bus/requests/$id';
  static const String busCorridors   = '$apiRoot/passenger/public-bus/corridors';
  static String busStops(int c)      => '$apiRoot/passenger/public-bus/corridors/$c/stops';
  static String busActive(int c)     => '$apiRoot/passenger/public-bus/corridors/$c/active-buses';
  static const String busBookSeat    = '$apiRoot/passenger/public-bus/book-seat';
  static const String busCurrentTrip = '$apiRoot/passenger/public-bus/trips/current';

  // ── Private car / trips table (mobile optimized) ───────────────────
  static const String requestTrip    = '$mobileRoot/trips/request';              // POST
  static const String currentTrip    = '$mobileRoot/trips/current';              // GET
  static String trackTrip(int id)    => '$mobileRoot/trips/$id/track';           // GET
  static String cancelTrip(int id)   => '$mobileRoot/trips/$id/cancel';           // PUT
  static String completeTrip(int id) => '$mobileRoot/trips/$id/complete';         // PUT
  static String tripDetail(int id)   => '$apiRoot/passenger/trips/$id';            // GET
  static String tripStatus(int id)   => '$apiRoot/passenger/trips/$id/status';    // GET
  static String matchingSession(int tripId) =>
      '$apiRoot/passenger/trips/$tripId/matching-session';                        // GET

  // ── Driver mobile (private car) ────────────────────────────────────
  static const String driverStatus   = '$mobileRoot/drivers/status';             // POST
  static const String driverTrips    = '$mobileRoot/drivers/trips';                // GET
  static String driverAccept(int id) => '$mobileRoot/drivers/trips/$id/accept';  // POST
  static String driverReject(int id) => '$mobileRoot/drivers/trips/$id/reject';  // POST
  static String driverStart(int id)  => '$mobileRoot/drivers/trips/$id/start';    // PUT
  static String driverComplete(int id) =>
      '$mobileRoot/drivers/trips/$id/complete';                                    // PUT
  static const String driverLiveLocation = '$mobileRoot/drivers/live-location';  // POST
  static const String driverLocationUpdate = '$apiRoot/driver/location/update';  // POST

  // ── Matching & tracking ────────────────────────────────────────────
  static const String matchDrivers   = '$mobileRoot/drivers/match';              // GET
  static String trackingDriver(int id) => '$mobileRoot/tracking/driver/$id';
  static String trackingTrip(int id)   => '$mobileRoot/tracking/trip/$id';
  static const String trackingNearby = '$mobileRoot/tracking/nearby';

  // ── Route & pricing ────────────────────────────────────────────────
  static const String routeCompute   = '$apiRoot/route/compute';                 // POST
  static const String pricingCalculate = '$apiRoot/pricing/calculate';           // POST

  // ── Payments & notifications ─────────────────────────────────────
  static const String payments       = '$apiRoot/passenger/payments';
  static const String paymentHistory = '$apiRoot/passenger/payments/history';
  static const String notifications  = '$apiRoot/notifications';

  // ── ML ─────────────────────────────────────────────────────────────
  static const String mlHealth       = 'https://ml-service-j72g.onrender.com/health';
  static const String mlRankDrivers  = 'https://ml-service-j72g.onrender.com/rank-drivers';
  static const String apiMlRank      = '$apiRoot/ml/rank-drivers';
}
```

---

## 12. HTTP method correction summary

| Wrong (Flutter list) | Correct |
|---------------------|---------|
| `PUT` moto cancel | **`POST`** `/passenger/motor-vehicle/trip-requests/{id}/cancel` |
| `GET` mobile trip cancel | **`PUT`** `/mobile/trips/{id}/cancel` |
| `GET` driver accept/reject | **`POST`** `/mobile/drivers/trips/{id}/accept\|reject` |
| `GET` driver start/complete | **`PUT`** `/mobile/drivers/trips/{id}/start\|complete` |
| `PUT` driver availability | **`POST`** `/mobile/drivers/status` |
| `GET` `/route/get-route` | **`POST`** `/route/compute` |
| `POST /auth/refresh` | **Remove** — use validate + re-login |
| `POST /predict` (ML) | **`POST`** `/rank-drivers` |

---

## 13. Trip status values — by transport

### Motorcycle (`motorcycle_trips` — poll motor-vehicle endpoint)

`REQUESTED` → `MATCHING` → `ASSIGNED` → `DRIVER_ASSIGNED` → `PASSENGER_WAITING` → `DRIVER_ARRIVED` → `IN_PROGRESS` → `COMPLETED` | `CANCELLED` | `EXPIRED`

### Private car (`trips` — mobile/passenger endpoints)

`requested` → `accepted` → `in_progress` → `completed` | `cancelled`  
*(API may return mixed case; normalize in Flutter)*

### Public bus (`trip_requests`)

`PENDING_MATCH` → `BUS_ASSIGNED` → `PASSENGER_WAITING` → boarding/transit → `COMPLETED` | `CANCELLED`

---

## 14. Files to update in Flutter repo

| File | Action |
|------|--------|
| `lib/config/api_config.dart` | Replace `ApiEndpoints` with section 11 |
| `lib/services/trip_service.dart` | Fix cancel to `PUT`; remove `GET` trip detail from mobile path |
| `lib/services/driver_matching_service.dart` | Fix matching-session path to `/passenger/trips/{tripId}/matching-session` |
| `lib/services/trip_service_v2.dart` | Keep v2 only if targeting `/api/v2/*`; otherwise consolidate |
| `passenger_api.fixed.dart` | Already mostly correct — merge into `lib/services/passenger_api.dart` |
| `flutter_reference/trip_matching_service.dart` | Correct — polls motor-vehicle show endpoint |

---

## 15. Validation checklist before release

- [ ] All URLs include `/api/v1` prefix (except direct ML service calls)
- [ ] Motorcycle flows never call `/mobile/trips/*` or `/passenger/trips/*`
- [ ] Private car flows never call `/passenger/motor-vehicle/*`
- [ ] Driver moto actions use `/driver/motor-vehicle/*`, not generic `/driver/trip-requests/*`
- [ ] All driver accept/reject use **POST**, start/complete use **PUT**
- [ ] Mobile login sends `login` field, not `email`
- [ ] Auth token stored and sent as `Bearer {token}` (Sanctum)
- [ ] No code references `/auth/refresh` or `/predict`
- [ ] FCM token registered via `POST /devices/push-token` after login

---

*Source of truth: `/home/joseph/projects/RideConnect/routes/api.php`*
