# Mobile Driver + Passenger APIs (Authenticated)

This is the production mobile API map for ride discovery, booking, trip request lifecycle, and in-app notifications between passenger and driver users.

## Base Requirements

- Production Base URL: `https://rideconnect-emp0.onrender.com/api/v1`
- Local Base URL: `http://localhost:8000/api/v1`
- Auth: Laravel Sanctum bearer token
- Header: `Authorization: Bearer <token>`
- Content type: `application/json`

## Lifecycle Flows

### Flow A: Passenger books a scheduled ride

1. Passenger fetches available rides.
2. Passenger creates booking.
3. Driver receives booking notification.
4. Driver confirms or cancels booking.
5. Passenger receives status notification.

### Flow B: Passenger requests immediate trip from online driver

1. Passenger fetches online drivers.
2. Passenger sends ride request to selected driver.
3. Driver receives trip request notification.
4. Driver accepts/rejects request.
5. Passenger receives decision notification.
6. Driver starts trip.
7. Passenger receives trip-start notification.
8. Driver completes or cancels trip.
9. Passenger receives completion/cancel notification.

## Shared Mobile APIs

### Notifications

- `GET /notifications`
- `GET /notifications/unread-count`
- `PUT /notifications/{id}/read`
- `PUT /notifications/read-all`

### Push tokens

- `POST /devices/push-token`
- `DELETE /devices/push-token/{token}`

`POST /devices/push-token` payload:

```json
{
	"platform": "fcm",
	"device_token": "fcm_token_here",
	"device_id": "android-device-uuid"
}
```

## Passenger APIs

### Profile + stats

- `GET /passenger/profile`
- `PUT /passenger/profile`
- `GET /passenger/stats`

### Ride discovery

- `GET /passenger/rides/available`
- `GET /passenger/rides/{id}`
- `GET /passenger/rides`
- `GET /passenger/rides/history`

### Ride booking (scheduled)

- `POST /passenger/rides`
- `PUT /passenger/rides/{id}/cancel`

`POST /passenger/rides` payload:

```json
{
	"ride_id": 21,
	"seats": 2,
	"pickup_address": "Kigali Convention Centre",
	"dropoff_address": "Kimironko"
}
```

### Booking management

- `GET /passenger/bookings`
- `GET /passenger/bookings/my`
- `GET /passenger/bookings/{id}`
- `POST /passenger/bookings`
- `PUT /passenger/bookings/{id}`
- `PUT /passenger/bookings/{id}/cancel`

### Trip request (driver-targeted)

- `GET /passenger/drivers/online`
- `POST /passenger/ride-requests`
- `GET /passenger/trips`
- `GET /passenger/trips/{id}`
- `PUT /passenger/trips/{id}/cancel`

`POST /passenger/ride-requests` payload:

```json
{
	"driver_id": 8,
	"pickup_location": "Kigali Heights",
	"pickup_lat": -1.9441,
	"pickup_lng": 30.0619,
	"dropoff_location": "Kimironko Market",
	"dropoff_lat": -1.9411,
	"dropoff_lng": 30.1098,
	"fare": 3500
}
```

### Passenger payments

- `POST /passenger/payments`
- `GET /passenger/payments/history`

## Driver APIs

### Profile + status

- `GET /driver/profile`
- `PUT /driver/profile`
- `GET /driver/stats`
- `PUT /driver/status`

### Driver ride management

- `GET /driver/rides`
- `POST /driver/rides`
- `PUT /driver/rides/{id}`
- `DELETE /driver/rides/{id}`

### Booking queue

- `GET /driver/bookings`
- `PUT /driver/bookings/{id}/confirm`
- `PUT /driver/bookings/{id}/cancel`

### Trip request queue

- `GET /driver/requests`
- `PUT /driver/requests/{id}/accept`
- `PUT /driver/requests/{id}/reject`
- `PUT /driver/requests/{id}/complete`

Alias routes (same behavior):

- `GET /driver/trip-requests`
- `PUT /driver/trip-requests/{id}/accept`
- `PUT /driver/trip-requests/{id}/reject`
- `PUT /driver/trip-requests/{id}/complete`

### Trip lifecycle

- `GET /driver/trips`
- `PUT /driver/trips/{id}/start`
- `PUT /driver/trips/{id}/cancel`

### Earnings + documents

- `GET /driver/earnings`
- `GET /driver/earnings/monthly`
- `POST /driver/documents`
- `GET /driver/documents`

## Notification Events Produced by Backend

- `ride_request_received` -> driver
- `ride_request_accepted` -> passenger
- `ride_request_rejected` -> passenger
- `trip_started` -> passenger
- `trip_completed` -> passenger
- `trip_cancelled` -> passenger and driver
- `booking_request_received` -> driver
- `booking_confirmed` -> passenger
- `booking_cancelled` -> passenger and driver

## Expected Response Envelope

All endpoints return:

```json
{
	"success": true,
	"message": "optional",
	"data": {}
}
```

## Safe Codex Prompt Templates for Flutter

Use these prompts in Codex to generate Flutter networking code safely and consistently.

### Prompt 1: Generate typed API client

```text
Generate Dart API client code for Flutter using Dio.
Use base URL {{BASE_URL}} and Sanctum Bearer token interceptor.
Implement methods only for these endpoints:
- GET /api/v1/passenger/rides/available
- POST /api/v1/passenger/rides
- GET /api/v1/passenger/bookings/my
- GET /api/v1/passenger/trips
- GET /api/v1/notifications
- GET /api/v1/notifications/unread-count
- PUT /api/v1/notifications/{id}/read
- POST /api/v1/devices/push-token

Requirements:
1) Strongly typed request/response models.
2) Unified ApiResult<T> with success/error branches.
3) Timeout, retry (max 2), and cancellation token support.
4) Never log bearer tokens, passwords, or raw device tokens.
5) Parse backend envelope: success/message/data.
6) Include unit tests with mocked Dio responses.
```

### Prompt 2: Generate trip request flow state machine

```text
Generate Flutter Riverpod state management for passenger trip request flow.
Flow:
1) GET /api/v1/passenger/drivers/online
2) POST /api/v1/passenger/ride-requests
3) Poll GET /api/v1/passenger/trips/{id} every 5 seconds until status in [ACCEPTED, CANCELLED]
4) Subscribe to notifications endpoint to show push/in-app updates.

Constraints:
- Debounce repeated submit taps.
- Prevent duplicate trip requests while one is pending.
- Handle 401 by forcing re-login.
- Handle 403 as approval-required state.
- Keep polling cancellable when screen is disposed.
```

### Prompt 3: Generate driver queue workflow

```text
Generate Flutter code for driver request queue and actions using Bloc.
Endpoints:
- GET /api/v1/driver/trip-requests
- PUT /api/v1/driver/trip-requests/{id}/accept
- PUT /api/v1/driver/trip-requests/{id}/reject
- PUT /api/v1/driver/trip-requests/{id}/complete
- PUT /api/v1/driver/trips/{id}/start

Requirements:
1) Optimistic UI with rollback on failure.
2) Show action guards for invalid transitions.
3) Persist pending actions offline and retry when back online.
4) Separate transport errors from domain errors.
```

### Prompt 4: Security hardening checklist

```text
Review the Flutter API integration and enforce security controls:
- No secrets in logs.
- Redact authorization headers in interceptors.
- Validate all numeric and enum inputs before sending.
- Enforce HTTPS only in production.
- Rotate and re-register push tokens on login/logout.
- Add automated tests for 401, 403, 422, 500 handling.
Return code patches only.
```
