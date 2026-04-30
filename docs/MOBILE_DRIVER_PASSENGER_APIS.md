# Mobile Driver + Passenger APIs (Authenticated)

This is the production mobile API map for ride discovery, booking, trip request lifecycle, and in-app notifications between passenger and driver users.

## Base Requirements

- Production Base URL: `https://rideconnect-emp0.onrender.com/api/v1`
- Local Base URL: `http://localhost:8000/api/v1`
- Auth: Laravel Sanctum bearer token
- Header: `Authorization: Bearer <token>`
- Content type: `application/json`

## Flutter Integration Checklist

Use this as the minimal API surface for the Passenger and Driver Flutter apps.

### Authentication

- [ ] `POST /api/v1/auth/register/driver`
- [ ] `POST /api/v1/auth/register/passenger`
- [ ] `POST /api/v1/auth/register`
- [ ] `POST /api/v1/auth/mobile/login`
- [ ] `GET /api/v1/auth/token/validate`
- [ ] `POST /api/v1/auth/session/clear`

### Shared App Services

- [ ] `GET /api/v1/notifications`
- [ ] `GET /api/v1/notifications/unread-count`
- [ ] `PUT /api/v1/notifications/{id}/read`
- [ ] `PUT /api/v1/notifications/read-all`
- [ ] `DELETE /api/v1/notifications/clear-actioned`
- [ ] `DELETE /api/v1/notifications/{id}`
- [ ] `POST /api/v1/devices/push-token`
- [ ] `DELETE /api/v1/devices/push-token/{token}`

### Passenger App

- [ ] `GET /api/v1/passenger/profile`
- [ ] `PUT /api/v1/passenger/profile`
- [ ] `GET /api/v1/passenger/stats`
- [ ] `GET /api/v1/passenger/rides/available`
- [ ] `GET /api/v1/passenger/rides/{id}`
- [ ] `GET /api/v1/passenger/rides`
- [ ] `GET /api/v1/passenger/rides/history`
- [ ] `POST /api/v1/passenger/rides`
- [ ] `PUT /api/v1/passenger/rides/{id}/cancel`
- [ ] `GET /api/v1/passenger/bookings`
- [ ] `GET /api/v1/passenger/bookings/my`
- [ ] `GET /api/v1/passenger/bookings/{id}`
- [ ] `POST /api/v1/passenger/bookings`
- [ ] `PUT /api/v1/passenger/bookings/{id}`
- [ ] `PUT /api/v1/passenger/bookings/{id}/cancel`
- [ ] `GET /api/v1/passenger/drivers/online`
- [ ] `POST /api/v1/passenger/ride-requests`
- [ ] `GET /api/v1/passenger/trips`
- [ ] `GET /api/v1/passenger/trips/{id}`
- [ ] `PUT /api/v1/passenger/trips/{id}/cancel`
- [ ] `POST /api/v1/passenger/trips/create-from-booking`
- [ ] `POST /api/v1/passenger/payments`
- [ ] `GET /api/v1/passenger/payments/history`

### Driver App

- [ ] `GET /api/v1/driver/profile`
- [ ] `PUT /api/v1/driver/profile`
- [ ] `GET /api/v1/driver/stats`
- [ ] `PUT /api/v1/driver/status`
- [ ] `GET /api/v1/driver/rides`
- [ ] `POST /api/v1/driver/rides`
- [ ] `PUT /api/v1/driver/rides/{id}`
- [ ] `DELETE /api/v1/driver/rides/{id}`
- [ ] `GET /api/v1/driver/bookings`
- [ ] `PUT /api/v1/driver/bookings/{id}/confirm`
- [ ] `PUT /api/v1/driver/bookings/{id}/cancel`
- [ ] `GET /api/v1/driver/requests`
- [ ] `PUT /api/v1/driver/requests/{id}/accept`
- [ ] `PUT /api/v1/driver/requests/{id}/reject`
- [ ] `PUT /api/v1/driver/requests/{id}/complete`
- [ ] `GET /api/v1/driver/trip-requests`
- [ ] `PUT /api/v1/driver/trip-requests/{id}/accept`
- [ ] `PUT /api/v1/driver/trip-requests/{id}/reject`
- [ ] `PUT /api/v1/driver/trip-requests/{id}/complete`
- [ ] `GET /api/v1/driver/trips`
- [ ] `PUT /api/v1/driver/trips/{id}/start`
- [ ] `PUT /api/v1/driver/trips/{id}/cancel`
- [ ] `GET /api/v1/driver/earnings`
- [ ] `GET /api/v1/driver/earnings/monthly`
- [ ] `POST /api/v1/driver/documents`
- [ ] `GET /api/v1/driver/documents`

### Route Notes

- `/api/v1/driver/requests` and `/api/v1/driver/trip-requests` are aliases for the same driver request queue.
- `/api/v1/auth/profile` and `/api/v1/user/profile` return the same authenticated user profile.
- `/api/driver/location` is available for live driver location updates, but it is outside the `v1` prefix.

## Flutter Integration Matrix

Unless noted otherwise, protected endpoints require `Authorization: Bearer <token>` and return the standard `success/message/data` envelope.

### Authentication And Shared Services

| Endpoint | Method | Auth | Payload / Notes | Expected Response |
| --- | --- | --- | --- | --- |
| `/api/v1/auth/register/driver` | `POST` | No | `full_name`, `email`, `phone_number`, `password`, `password_confirmation` | Driver account created; approval may still be required |
| `/api/v1/auth/register/passenger` | `POST` | No | `name`, `email`, `phone`, `password`, `password_confirmation` | Passenger account created; approval may still be required |
| `/api/v1/auth/register` | `POST` | No | `name` or `full_name`, `email`, `phone` or `phone_number`, `role`, `password`, `password_confirmation` | Account created with explicit role |
| `/api/v1/auth/mobile/login` | `POST` | No | `login`, `password`, optional `device_name` | Auth user object + bearer token |
| `/api/v1/auth/login` | `POST` | No | Legacy email/password login | Auth user object + bearer token |
| `/api/v1/auth/token/validate` | `GET` | Yes | No payload | Current user + token metadata |
| `/api/v1/auth/session/clear` | `POST` | Yes | Optional `all_devices` boolean | Token(s) revoked |
| `/api/v1/auth/logout` | `POST` | Yes | No payload | Current session ended |
| `/api/v1/auth/profile` | `GET` | Yes | No payload | Authenticated user profile |
| `/api/v1/auth/profile` | `PUT` | Yes | Profile fields such as `name`, `phone` | Updated authenticated user profile |
| `/api/v1/user/profile` | `GET` | Yes | Alias of auth profile | Authenticated user profile |
| `/api/v1/user/password` | `PUT` | Yes | Password update fields | Password updated |
| `/api/v1/notifications` | `GET` | Yes | Optional query flags like `unread_only`, `only_clearable`, `only_action_required` | Notification list |
| `/api/v1/notifications/unread-count` | `GET` | Yes | No payload | Unread count object |
| `/api/v1/notifications/{id}/read` | `PUT` | Yes | No payload | Notification marked read |
| `/api/v1/notifications/read-all` | `PUT` | Yes | No payload | All notifications marked read |
| `/api/v1/notifications/clear-actioned` | `DELETE` | Yes | No payload | Actioned notifications cleared |
| `/api/v1/notifications/{id}` | `DELETE` | Yes | No payload | Deletes actioned notification only |
| `/api/v1/devices/push-token` | `POST` | Yes | `platform`, `device_token`, `device_id` | Push token registered or refreshed |
| `/api/v1/devices/push-token/{token}` | `DELETE` | Yes | Push token path parameter | Push token removed |

### Passenger Integration Matrix

| Endpoint | Method | Auth | Payload / Notes | Expected Response |
| --- | --- | --- | --- | --- |
| `/api/v1/passenger/profile` | `GET` | Yes | No payload | Passenger profile |
| `/api/v1/passenger/profile` | `PUT` | Yes | Profile fields such as `name`, `phone` | Updated passenger profile |
| `/api/v1/passenger/stats` | `GET` | Yes | No payload | Passenger stats summary |
| `/api/v1/passenger/rides/available` | `GET` | Yes | No payload, optional paging if supported | Available rides list |
| `/api/v1/passenger/rides/{id}` | `GET` | Yes | Ride id in path | Ride details |
| `/api/v1/passenger/rides` | `GET` | Yes | Ride history/list | Passenger rides list |
| `/api/v1/passenger/rides/history` | `GET` | Yes | Alias of passenger rides list | Passenger ride history |
| `/api/v1/passenger/rides` | `POST` | Yes | `ride_id`, `seats`, `pickup_address`, `dropoff_address` | Ride booking created |
| `/api/v1/passenger/rides/{id}/cancel` | `PUT` | Yes | Optional `reason` | Ride booking cancelled |
| `/api/v1/passenger/bookings` | `GET` | Yes | No payload | Booking list |
| `/api/v1/passenger/bookings/my` | `GET` | Yes | No payload | Passenger bookings |
| `/api/v1/passenger/bookings/{id}` | `GET` | Yes | Booking id in path | Booking details |
| `/api/v1/passenger/bookings` | `POST` | Yes | Booking creation fields | Booking created |
| `/api/v1/passenger/bookings/{id}` | `PUT` | Yes | Booking update fields | Booking updated |
| `/api/v1/passenger/bookings/{id}/cancel` | `PUT` | Yes | Optional cancel reason | Booking cancelled |
| `/api/v1/passenger/drivers/online` | `GET` | Yes | Optional `limit` query | Online driver list |
| `/api/v1/passenger/ride-requests` | `POST` | Yes | `driver_id`, pickup/dropoff coordinates and addresses, `fare` | Direct trip request created |
| `/api/v1/passenger/trips` | `GET` | Yes | No payload | Passenger trip list |
| `/api/v1/passenger/trips/{id}` | `GET` | Yes | Trip id in path | Trip details |
| `/api/v1/passenger/trips/{id}/cancel` | `PUT` | Yes | Optional cancel reason | Trip cancelled |
| `/api/v1/passenger/trips/create-from-booking` | `POST` | Yes | Booking-to-trip conversion payload | Trip created from booking |
| `/api/v1/passenger/payments` | `POST` | Yes | Payment creation payload | Payment initiated |
| `/api/v1/passenger/payments/history` | `GET` | Yes | No payload | Payment history |

### Driver Integration Matrix

| Endpoint | Method | Auth | Payload / Notes | Expected Response |
| --- | --- | --- | --- | --- |
| `/api/v1/driver/profile` | `GET` | Yes | No payload | Driver profile |
| `/api/v1/driver/profile` | `PUT` | Yes | Profile fields such as `name`, `phone` | Updated driver profile |
| `/api/v1/driver/stats` | `GET` | Yes | No payload | Driver stats summary |
| `/api/v1/driver/status` | `PUT` | Yes | `status`, optional `latitude`, `longitude` | Driver availability/location updated |
| `/api/v1/driver/rides` | `GET` | Yes | No payload | Driver ride list |
| `/api/v1/driver/rides` | `POST` | Yes | Ride creation payload | Driver ride created |
| `/api/v1/driver/rides/{id}` | `PUT` | Yes | Ride update payload | Driver ride updated |
| `/api/v1/driver/rides/{id}` | `DELETE` | Yes | Ride id in path | Driver ride removed |
| `/api/v1/driver/bookings` | `GET` | Yes | No payload | Booking queue |
| `/api/v1/driver/bookings/{id}/confirm` | `PUT` | Yes | No payload | Booking confirmed |
| `/api/v1/driver/bookings/{id}/cancel` | `PUT` | Yes | Optional cancel reason | Booking cancelled by driver |
| `/api/v1/driver/requests` | `GET` | Yes | Alias of trip-request queue | Incoming trip requests |
| `/api/v1/driver/requests/{id}/accept` | `PUT` | Yes | No payload | Trip request accepted |
| `/api/v1/driver/requests/{id}/reject` | `PUT` | Yes | Optional rejection reason | Trip request rejected |
| `/api/v1/driver/requests/{id}/complete` | `PUT` | Yes | Optional trip completion metrics | Trip request completed |
| `/api/v1/driver/trip-requests` | `GET` | Yes | Alias of request queue | Incoming trip requests |
| `/api/v1/driver/trip-requests/{id}/accept` | `PUT` | Yes | No payload | Trip request accepted |
| `/api/v1/driver/trip-requests/{id}/reject` | `PUT` | Yes | Optional rejection reason | Trip request rejected |
| `/api/v1/driver/trip-requests/{id}/complete` | `PUT` | Yes | Optional trip completion metrics | Trip request completed |
| `/api/v1/driver/trips` | `GET` | Yes | No payload | Driver trip list |
| `/api/v1/driver/trips/{id}/start` | `PUT` | Yes | No payload | Trip started |
| `/api/v1/driver/trips/{id}/cancel` | `PUT` | Yes | Optional cancel reason | Trip cancelled |
| `/api/v1/driver/earnings` | `GET` | Yes | Optional date range query if supported | Earnings summary |
| `/api/v1/driver/earnings/monthly` | `GET` | Yes | No payload | Monthly earnings trend |
| `/api/v1/driver/documents` | `POST` | Yes | Multipart document upload | Driver document uploaded |
| `/api/v1/driver/documents` | `GET` | Yes | No payload | Driver document list |
| `/api/driver/location` | `POST` | Yes | Live location update payload | Driver location updated |

### Integration Notes

- Use the Passenger matrix for passenger role screens only.
- Use the Driver matrix for driver role screens only.
- Treat `/api/v1/driver/requests` and `/api/v1/driver/trip-requests` as the same queue in Flutter state management.
- Treat `/api/v1/passenger/rides` and `/api/v1/passenger/rides/history` as history/list views that can share the same repository method if needed.

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
