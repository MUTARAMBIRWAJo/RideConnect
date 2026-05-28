# Flutter Backend API Alignment Report

**Scope:** Verified backend API surface for Flutter mobile integration in the current Laravel workspace.

> The workspace contains no dedicated Flutter app source tree, so this document provides a backend-aligned Flutter handoff instead of modifying Flutter code directly.

## 1. Verified Backend API Sources

The endpoint inventory below is anchored in the Laravel route definitions in `routes/api.php` and the controller implementations under `app/Http/Controllers/Api`.

Key sources used:
- `routes/api.php`
- `app/Http/Controllers/Api/MobilePassengerController.php`
- `app/Http/Controllers/Api/MobileDriverController.php`
- `app/Http/Controllers/Api/TripController.php`
- `app/Http/Controllers/Api/PaymentController.php`
- `app/Http/Controllers/Api/PublicTransportController.php`
- `docs/MOBILE_DRIVER_PASSENGER_APIS.md`
- `docs/FLUTTER_MOBILE_APP_API_QUICK_REFERENCE.md`

## 2. Recommended Flutter Route Strategy

### Use optimized mobile endpoints when building a new Flutter client

The backend exposes a mobile-focused layer under `/api/v1/mobile` that is the best match for Flutter state-driven apps:

- Passenger mobile flows: `/api/v1/mobile/rides`, `/api/v1/mobile/bookings`, `/api/v1/mobile/trips/request`, `/api/v1/mobile/trips/current`, `/api/v1/mobile/trips/{id}/track`, `/api/v1/mobile/trips/{id}/cancel`, `/api/v1/mobile/trips/{id}/complete`
- Driver mobile flows: `/api/v1/mobile/drivers/status`, `/api/v1/mobile/drivers/trips`, `/api/v1/mobile/drivers/trips/{id}/accept`, `/api/v1/mobile/drivers/trips/{id}/reject`, `/api/v1/mobile/drivers/trips/{id}/start`, `/api/v1/mobile/drivers/trips/{id}/complete`, `/api/v1/mobile/drivers/trips/{id}/cancel`, `/api/v1/mobile/driver/location`, `/api/v1/mobile/driver/live-location`
- Real-time tracking: `/api/v1/mobile/tracking/driver/{driverId}`, `/api/v1/mobile/tracking/trip/{tripId}`, `/api/v1/mobile/tracking/nearby`

### Keep legacy aliases as compatibility endpoints

The backend supports several alias routes that should be treated as compatibility, not primary Flutter targets:

- `/api/v1/driver/requests` and `/api/v1/driver/trip-requests` are the same queue.
- `/api/v1/driver/requests/{id}/accept|reject|complete` and `/api/v1/driver/trip-requests/{id}/accept|reject|complete` funnel into the same request lifecycle.
- `/api/v1/passenger/rides/history` is an alias for ride history.
- `/api/v1/user/profile` is an alias of authenticated profile.
- `/api/v1/driver/status` and `/api/v1/rider/status` are status endpoints, but Flutter should prefer the mobile-optimized status route.

## 3. Verified Flutter-Relevant Endpoint Mapping

### Authentication and shared services

| Route | Method | Flutter use | Backend notes |
| --- | --- | --- | --- |
| `/api/v1/auth/register` | POST | account creation | Generic registration with role field |
| `/api/v1/auth/register/driver` | POST | driver onboarding | explicit driver registration |
| `/api/v1/auth/register/passenger` | POST | passenger onboarding | explicit passenger registration |
| `/api/v1/auth/mobile/login` | POST | mobile login | supports email or phone login |
| `/api/v1/auth/token/validate` | GET | session check | bearer token validation |
| `/api/v1/auth/logout` | POST | logout | revokes current session |
| `/api/v1/auth/session/clear` | POST | full token clear | supports all devices |
| `/api/v1/auth/profile` | GET / PUT | profile screen | authenticated profile endpoint |
| `/api/v1/user/profile` | GET | profile screen alias | same data as `/auth/profile` |
| `/api/v1/user/password` | PUT | change password | password update flow |
| `/api/v1/notifications` | GET | notification center | paginated list |
| `/api/v1/notifications/unread-count` | GET | badge count | unread count summary |
| `/api/v1/notifications/{id}/read` | PUT | mark one read | per-notification state |
| `/api/v1/notifications/read-all` | PUT | mark all read | batch state |
| `/api/v1/notifications/clear-actioned` | DELETE | clear actioned | cleanup endpoint |
| `/api/v1/devices/push-token` | POST | push registration | store device token |
| `/api/v1/devices/push-token/{token}` | DELETE | token removal | unregister token |

### Passenger flows

| Route | Method | Flutter use | Backend notes |
| --- | --- | --- | --- |
| `/api/v1/mobile/rides` | GET | available rides | optimized mobile ride discovery |
| `/api/v1/mobile/bookings` | POST | schedule booking | creates booking; supports selected driver and matching session |
| `/api/v1/passenger/rides/available` | GET | fallback ride discovery | legacy but valid |
| `/api/v1/passenger/rides` | GET | ride history | passenger ride history list |
| `/api/v1/passenger/rides/history` | GET | alias history | same as ride history |
| `/api/v1/passenger/rides/{id}` | GET | ride details | detail endpoint |
| `/api/v1/passenger/rides/{id}/cancel` | PUT | cancel ride booking | ride cancellation |
| `/api/v1/passenger/bookings` | GET | bookings list | booking queue |
| `/api/v1/passenger/bookings/my` | GET | booking list alias | same data |
| `/api/v1/passenger/bookings/{id}` | GET / PUT | booking detail/update | booking detail screen |
| `/api/v1/passenger/bookings/{id}/cancel` | PUT | cancel booking | booking cancel flow |
| `/api/v1/passenger/drivers/online` | GET | online driver finder | driver discovery |
| `/api/v1/passenger/ride-requests` | POST | direct ride request | direct passenger request to driver |
| `/api/v1/mobile/trips/request` | POST | on-demand trip request | optimized direct request endpoint |
| `/api/v1/passenger/trips` | GET | trip history | passenger trip list |
| `/api/v1/passenger/trips/{id}` | GET | trip detail | trip details |
| `/api/v1/passenger/trips/{id}/cancel` | PUT | cancel trip | passenger-side cancel |
| `/api/v1/passenger/trips/create-from-booking` | POST | convert booking to trip | scheduled flow |
| `/api/v1/passenger/payments` | POST | payment initiation | creates payment record, updates trip actual fare |
| `/api/v1/passenger/payments/history` | GET | payment history | paginated history |

### Driver flows

| Route | Method | Flutter use | Backend notes |
| --- | --- | --- | --- |
| `/api/v1/driver/profile` | GET / PUT | driver profile | driver profile update |
| `/api/v1/driver/stats` | GET | stats dashboard | driver stats |
| `/api/v1/mobile/drivers/status` | POST | go online/offline | optimized status update, `is_online` boolean |
| `/api/v1/driver/status` | PUT | legacy status update | compatibility route |
| `/api/v1/mobile/drivers/trips` | GET | driver queue | available trips for acceptance |
| `/api/v1/mobile/drivers/trips/{id}/accept` | POST | accept request | strong race-condition handling, returns 409 when unavailable |
| `/api/v1/mobile/drivers/trips/{id}/reject` | POST | reject request | driver decline path |
| `/api/v1/mobile/drivers/trips/{id}/start` | PUT | start trip | transitions trip state to started |
| `/api/v1/mobile/drivers/trips/{id}/complete` | PUT | complete trip | completion service handles finalization |
| `/api/v1/mobile/drivers/trips/{id}/cancel` | PUT | cancel trip | cancel after assignment |
| `/api/v1/mobile/driver/location` | POST | send trip location | requires trip_id, lat, lng |
| `/api/v1/mobile/driver/live-location` | POST | real-time location updates | richer telemetry fields |
| `/api/v1/mobile/tracking/driver/{driverId}` | GET | driver tracking | live driver marker |
| `/api/v1/mobile/tracking/trip/{tripId}` | GET | trip tracking | trip-specific tracking |
| `/api/v1/driver/trips` | GET | driver trip list | full driver trip list |
| `/api/v1/driver/earnings` | GET | earnings summary | financial summary |
| `/api/v1/driver/earnings/monthly` | GET | monthly earnings | month trend |
| `/api/v1/driver/documents` | POST / GET | document upload | document management |

### Tracking and live updates

| Route | Method | Flutter use | Backend notes |
| --- | --- | --- | --- |
| `/api/v1/mobile/trips/current` | GET | active trip screen | returns current active trip and driver location |
| `/api/v1/mobile/trips/{id}/track` | GET | trip tracking screen | returns driver location, route path, ETA |
| `/api/v1/mobile/tracking/driver/{driverId}` | GET | driver marker | driver tracking endpoint |
| `/api/v1/mobile/tracking/trip/{tripId}` | GET | trip marker | live trip tracking |
| `/api/v1/mobile/tracking/nearby` | GET | nearby drivers | nearby driver search |
| `/api/driver/location` | POST | legacy live location | outside `/api/v1` prefix |

## 4. Response Envelope and Behavioral Notes

### Envelope differences

- Mobile controller routes return `status`, `data`, and sometimes `message` as JSON.
- Classic auth and payment routes return `success`, `message`, and `data`.
- Flutter should normalize these at the client layer instead of assuming one envelope across all endpoints.

### Trip lifecycle semantics

Trip actions are stateful and validated:

- Passenger can cancel trips using `/api/v1/mobile/trips/{id}/cancel` or `/api/v1/passenger/trips/{id}/cancel`.
- Driver can accept, start, complete, and cancel trip actions through mobile driver endpoints.
- `MobileDriverController::acceptTrip` enforces availability and race-condition checks, with explicit 409/422 behavior.
- `MobilePassengerController::trackTrip` and `getCurrentTrip` return structured tracking payloads.

## 5. Missing or Unclear Flutter Targets

These are important gaps to account for when implementing Flutter:

1. **Generic ratings endpoint is not present**
   - The route inventory exposes `/api/v1/passenger/trips/{trip}/feedback` for public-transport feedback, not a generic `/ratings` endpoint.
   - This means rating screens should target public transport feedback only, or use embedded booking/review metadata if needed.

2. **No dedicated Flutter completion endpoint outside trip lifecycle**
   - Trip completion happens through trip state transitions (`/mobile/drivers/trips/{id}/complete`, `/mobile/trips/{id}/complete`, or legacy `/driver/trips/{id}/complete`).

3. **Driver live location is split across endpoints**
   - The app should use `/api/v1/mobile/driver/live-location` for periodic telemetry and `/api/v1/mobile/driver/location` when tied to an active trip.

4. **Public transport flows are separate**
   - `/api/v1/passenger/public-transport/*`, `/api/v1/passenger/trip-requests`, and `/api/v1/passenger/trips/{trip}/feedback` represent separate modal transport flows, not the same as on-demand ride flows.

## 6. Flutter Implementation Recommendation

1. Build the Flutter app against the optimized `/api/v1/mobile/*` routes for driver and passenger actions.
2. Normalize response envelopes in a single API client layer.
3. Use `/api/v1/mobile/trips/current` and `/api/v1/mobile/trips/{id}/track` as the primary tracking endpoints.
4. Use `/api/v1/mobile/drivers/trips` plus accept/start/complete/cancel for driver trip management.
5. Treat legacy `/api/v1/driver/*` and `/api/v1/passenger/*` routes as compatibility only.

## 7. Bottom Line

The backend supports a real, mobile-optimized API surface for Flutter. The most reliable Flutter targets are the authenticated `/api/v1/mobile/*` endpoints and tracked trip lifecycle routes, with legacy `/api/v1/driver/*` and `/api/v1/passenger/*` routes used only as fallback compatibility.
