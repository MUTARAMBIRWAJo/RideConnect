# RideConnect Identity Normalization Audit

**Date:** 2026-06-12  
**Scope:** Identity consistency only (payment architecture and Firestore schema unchanged)  
**Canonical model:** `users.id` for all actors; role-based separation; `drivers.user_id → users.id` for driver profiles

---

## Executive Summary

RideConnect historically maintained **two parallel identity stores**:

| Store | Purpose | Status |
|-------|---------|--------|
| `users` | Sanctum auth, Filament admin, canonical identity | **Canonical** |
| `mobile_users` | Legacy mobile profile / duplicate credentials | **Legacy bridge** (`users.mobile_user_id`) |
| `passengers` table | — | **Does not exist** (alias in config only) |
| `drivers` | Driver operational profile | **Profile table** (`user_id → users.id`) |

This audit identified **split passenger references** (`trips.passenger_id → mobile_users.id` vs `motorcycle_trips.passenger_id → users.id`), **incorrect Eloquent relationships**, and **scattered resolution logic** across 10+ controllers.

### Production Readiness Score

Run live scoring after deploy:

```bash
php artisan identity:report
```

| Dimension | Pre-normalization | Post-implementation (target) |
|-----------|-------------------|------------------------------|
| Canonical auth (`users`) | Partial | ✅ |
| Trip passenger FK alignment | ❌ Split | ✅ Migration + models |
| Driver profile linkage | Partial | ✅ |
| Payment `user_id` | ✅ Already `users.id` | ✅ |
| Notification `user_id` | ✅ Already `users.id` | ✅ |
| Firebase channel IDs | Mixed | ✅ Uses `users.id` for passengers |
| Orphan detection | None | ✅ `IdentityConsistencyService` |
| **Overall** | **~58/100** | **~88/100** (after migration on production) |

---

## Phase 1 — Findings

### 1.1 Identity Tables

#### `users` (canonical)
- Primary auth via Laravel Sanctum
- Roles: `SUPER_ADMIN`, `ADMIN`, `ACCOUNTANT`, `OFFICER`, `DRIVER`, `PASSENGER`
- Link column: `mobile_user_id` (nullable, legacy)
- Manager link: `manager_id`

#### `mobile_users` (legacy)
- Duplicate profile credentials (`first_name`, `last_name`, `phone`, `email`, `password`)
- Created by older seeders and `resolveOrCreatePassengerMobileUserId()` flows
- **Must not be written as passenger owner on new domain records**

#### `passengers`
- **No database table.** Referenced in Firebase docs and `config/database_protection.php` as alias for `mobile_users`.

#### `drivers`
- Operational profile: `user_id → users.id` (correct in schema)
- Referenced by `trips.driver_id`, `motorcycle_trips.driver_id`, wallets, earnings

### 1.2 Duplicated Identity Sources

| Pattern | Locations | Risk |
|---------|-----------|------|
| `passenger_id → mobile_users.id` | `trips`, `matching_sessions`, public bus tables | High — breaks join to `users` |
| `passenger_id → users.id` | `motorcycle_trips`, `trip_requests` | Correct |
| Dual lookup `[mobile_user_id, user.id]` | `TripController`, tests | Migration debt |
| `Driver::mobileUser()` → `MobileUser` on `user_id` | `Driver` model | **Bug** — wrong target table |
| `driver_locations.driver_id → mobile_users` | Migration `2026_03_09` | Conflicts with driver APIs |

### 1.3 Conflicting Foreign Keys

| Table | Column | Legacy FK | Normalized FK |
|-------|--------|-----------|---------------|
| `trips` | `passenger_id` | `mobile_users.id` | **`users.id`** |
| `trips` | `driver_id` | `mobile_users.id` → fixed | `drivers.id` |
| `matching_sessions` | `passenger_id` | `mobile_users.id` | **`users.id`** |
| `seat_reservations` | `passenger_id` | `mobile_users.id` | **`users.id`** |
| `transport_tickets` | `passenger_id` | `mobile_users.id` | **`users.id`** |
| `motorcycle_trips` | `passenger_id` | — | `users.id` ✅ |
| `payments` | `user_id` | — | `users.id` ✅ |
| `reviews` | `user_id` | — | `users.id` ✅ |
| `reviews` | `driver_id` | — | `drivers.id` (resolves via `drivers.user_id`) |
| `user_notifications` | `user_id` | — | `users.id` ✅ |
| `mobile_device_tokens` | `user_id` | — | `users.id` ✅ |

### 1.4 Nullable / Orphan-Prone Columns

- `users.mobile_user_id` — nullable; orphans when pointing to deleted `mobile_users`
- `drivers.user_id` — nullable in some environments; orphan driver profiles
- `trips.driver_id` — nullable until assignment
- `seat_reservations.passenger_id`, `transport_tickets.passenger_id` — nullable

### 1.5 UUID vs Integer

- All identity PKs are **bigint**; no UUID user IDs in PostgreSQL schema
- Filament `notifications` table uses UUID primary keys (framework table, separate from `user_notifications`)

---

## Phase 2 — Canonical Identity Model

```
users (id) ──role──► PASSENGER | DRIVER | ADMIN | OFFICER | ACCOUNTANT | SUPER_ADMIN
   │
   ├── mobile_user_id ──► mobile_users (legacy bridge, read-only for new features)
   │
   ├── hasOne Driver (when role=DRIVER) ──► drivers.id used on trips for matching
   │
   └── passenger profile fields on users row (name, phone, email, profile_photo)
```

**Write rule:** All new `passenger_id` values = `users.id`.  
**Read rule:** Query with `IdentityResolverService::passengerOwnerIdsForQuery()` until production backfill completes.

---

## Phase 3 — Foreign Key Normalization (Implemented)

**Migration:** `2026_06_12_000001_normalize_passenger_identity_foreign_keys.php`

- Backfills `passenger_id` from `mobile_users.id` → linked `users.id`
- Replaces FK constraints on: `trips`, `matching_sessions`, `seat_reservations`, `transport_tickets`, `passenger_route_boardings`, `ride_cancellations`, `passenger_boarding_events`
- **Not modified:** payment ledger architecture, Firestore collections

**Driver trips:** `trips.driver_id` remains `drivers.id` (operational). Canonical user resolved via `drivers.user_id`.

---

## Phase 4 — Orphan Detection (Implemented)

| Component | Path |
|-----------|------|
| Service | `app/Services/Identity/IdentityConsistencyService.php` |
| Resolver | `app/Services/Identity/IdentityResolverService.php` |
| Command | `php artisan identity:report [--json] [--save=path]` |

Checks: orphan users, drivers, trips, payments, reviews, notifications, matching sessions, FCM tokens, dual passenger ID drift.

---

## Phase 5 — API Validation (Updated)

### Central resolution

| Component | Path |
|-----------|------|
| Trait | `app/Http/Concerns/ResolvesCanonicalIdentity.php` |
| Service | `app/Services/Identity/IdentityResolverService.php` |

### Controllers updated

- `TripController` — passenger writes/reads use `users.id`
- `PassengerController`, `MobilePassengerController`, `PassengerPublicBusController`
- `PaymentController` — trip creation uses canonical passenger id
- Services: `PublicBusTransportService`, `RideCategoryTransitionService`
- `NotificationService` — realtime channels use canonical ids

### Auth flow

```
Sanctum token → User model → users.id
                          → driver()?->id for driver operations
                          → passengerOwnerId() for passenger ownership
```

---

## Phase 6 — Firebase Alignment (Code-only, schema unchanged)

| Integration | Identifier used | Status |
|-------------|-----------------|--------|
| `FirebaseRealtimeService::pushNotification` | `user_id` param | Expects **`users.id`** |
| `NotificationService` realtime broadcast | `realtimePassengerChannelId()` | **Fixed** → `users.id` |
| FCM `mobile_device_tokens.user_id` | `users.id` | ✅ |
| Firestore `passengers/{id}` docs | Document ID | **Not changed** (per scope) |
| Supabase Realtime `passenger:{id}` | Channel ID | **Fixed** → `users.id` |

---

## Phase 7 — Tests (Implemented)

| Test | Path |
|------|------|
| Unit | `tests/Unit/Identity/IdentityServicesTest.php` |
| E2E passenger flow | `tests/Feature/Identity/IdentityNormalizationFlowTest.php` |
| E2E driver flow | Same file — accept/complete trip |
| Regression | `tests/Feature/MobilePaymentAndRatingFlowTest.php` updated |

---

## Affected Models

| Model | Change |
|-------|--------|
| `Trip` | `passenger()` → `User` |
| `MatchingSession` | `passenger()` → `User` |
| `DriverLocation` | `driver()` → `Driver` |
| `RideCancellation`, `TransportTicket`, `SeatReservation`, `PassengerRouteBoarding` | `passenger()` → `User` |
| `PassengerBehavior` | `passenger()` → `User`; legacy `passengerMobileProfile()` |
| `Driver` | Deprecated incorrect `mobileUser()` mapping |

---

## Affected APIs

| API group | Endpoints | Identity check |
|-----------|-----------|----------------|
| Auth | `/auth/register/*`, `/auth/mobile/login` | Returns `users.id` |
| Passenger | `/passenger/trips`, `/passenger/payments` | `passengerOwnerId()` |
| Driver | `/driver/trips/{id}/accept|complete` | `drivers.user_id === auth.id` |
| Trips | `/trips`, `/trips/{id}` | Dual-read during migration |
| Notifications | `/passenger/notifications` | `user_notifications.user_id` |
| Public bus | `/passenger/public-bus/*` | Canonical passenger id |

---

## Affected Migrations

| Migration | Role |
|-----------|------|
| `2025_02_25_000001_create_mobile_users_table.php` | Legacy table |
| `2025_02_25_000004_create_trips_table.php` | Original FK to mobile_users |
| `2026_03_12_120000_align_driver_api_schema.php` | Normalized driver_id on trips |
| `2026_06_06_000005_create_motorcycle_trips_table.php` | Correct passenger FK |
| **`2026_06_12_000001_normalize_passenger_identity_foreign_keys.php`** | **This implementation** |

---

## Deployment Checklist

1. Run `php artisan migrate` on staging/production
2. Run `php artisan identity:report --save=storage/identity-reports/report.json`
3. Verify `dual_passenger_id_drift.count === 0`
4. Confirm Flutter clients use `users.id` from auth response (not `mobile_user_id`)
5. Re-run targeted tests: `php artisan test --filter=Identity`

---

## Out of Scope (Explicit)

- Payment ledger / `ledger_accounts` architecture
- Firestore collection schema changes
- Health monitoring system
- Migration safety system

---

## Orphan Report Command

```bash
php artisan identity:report
php artisan identity:report --json
php artisan identity:report --save=storage/identity-reports/latest.json
```

Report includes `production_readiness_score` (0–100) weighted by trip/payment/review orphan severity.
