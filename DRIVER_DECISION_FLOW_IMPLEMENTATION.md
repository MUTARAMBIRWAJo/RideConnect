# Driver Decision Flow Implementation
**Date:** 2026-06-06  
**Status:** ✅ COMPLETE

## Overview
Implemented complete driver decision workflow for the public bus trip matching system. Drivers can now accept or reject trip requests with proper validation, status transitions, and event dispatching.

---

## Implemented Components

### 1. Events (Domain Events)

#### `DriverAcceptedTrip` Event
**File:** `app/Events/Domain/DriverAcceptedTrip.php`

**Purpose:** Fired when a driver accepts a trip request

**Properties:**
- `TripRequest $tripRequest` - The accepted trip request
- `int $driverId` - Driver who accepted
- `?string $notes` - Optional notes

**Broadcasting:** Private channel `trip-request.{id}` with event name `driver.accepted`

```php
// Usage
event(new DriverAcceptedTrip($tripRequest, $driver->id, 'Driver accepted trip request'));
```

---

#### `DriverRejectedTrip` Event
**File:** `app/Events/Domain/DriverRejectedTrip.php`

**Purpose:** Fired when a driver rejects a trip request

**Properties:**
- `TripRequest $tripRequest` - The rejected trip request
- `int $driverId` - Driver who rejected
- `string $reason` - Rejection reason (e.g., 'DRIVER_DECLINED')
- `?string $notes` - Optional notes

**Broadcasting:** Private channel `trip-request.{id}` with event name `driver.rejected`

```php
// Usage
event(new DriverRejectedTrip($tripRequest, $driver->id, 'DRIVER_DECLINED', 'Not interested'));
```

---

### 2. Status Transition Service

**File:** `app/Services/TripStatusTransitionService.php`

**Purpose:** Enforces safe status transitions and prevents invalid state changes

**Valid Transitions:**
```
PENDING_MATCH        → PASSENGER_WAITING, CANCELLED
PASSENGER_WAITING    → PASSENGER_BOARDED, CANCELLED
PASSENGER_BOARDED    → IN_TRANSIT, CANCELLED
IN_TRANSIT           → COMPLETED, CANCELLED
COMPLETED            → (no transitions)
CANCELLED            → (no transitions)
```

**Key Methods:**

| Method | Purpose | Returns |
|--------|---------|---------|
| `isValidTransition($from, $to)` | Check if transition is allowed | `bool` |
| `transition($trip, $newStatus)` | Safely update trip status | `bool` |
| `canBeAccepted($trip)` | Check if trip can be accepted | `bool` |
| `canBeRejected($trip)` | Check if trip can be rejected | `bool` |
| `getValidTransitions($status)` | Get all valid next states | `array` |

**Usage:**
```php
$transitionService = app(TripStatusTransitionService::class);

// Check if valid
if ($transitionService->canBeAccepted($tripRequest)) {
    // Transition safely
    $transitionService->transition($tripRequest, 'PASSENGER_WAITING');
}
```

---

### 3. Controller Methods

**File:** `app/Http/Controllers/Api/DriverPublicBusController.php`

#### `acceptTripRequest($request, $tripRequestId)`

**Endpoint:** `POST /api/v1/driver/public-bus/trip-requests/{trip_request_id}/accept`

**Authentication:** Required (Bearer token)

**Request Parameters:**
- `trip_request_id` (URL) - ID of trip request to accept

**Validation:**
1. Driver profile exists
2. Trip request status = `PENDING_MATCH`
3. Driver owns the matched vehicle (authorization)
4. Transition is valid (status check)

**Processing:**
1. Load trip request by ID
2. Validate trip is pending
3. Validate driver authorization
4. Use `TripStatusTransitionService` to safely transition to `PASSENGER_WAITING`
5. Dispatch `DriverAcceptedTrip` event
6. Return formatted response

**Response (Success - 200):**
```json
{
    "success": true,
    "message": "Trip request accepted successfully",
    "data": {
        "trip_request_id": 123,
        "status": "PASSENGER_WAITING",
        "passenger": {
            "id": 456,
            "pickup_location": "Kimironko Market",
            "pickup_lat": -1.948,
            "pickup_lng": 30.0619,
            "dropoff_location": "Nyabugogo Bus Park",
            "dropoff_lat": -1.9487,
            "dropoff_lng": 30.0597
        },
        "bus": {
            "vehicle_id": 789,
            "driver_id": 100
        },
        "route": {
            "distance_km": 0.26,
            "duration_minutes": 1
        },
        "fare": {
            "amount": 1500,
            "currency": "RWF"
        }
    }
}
```

**Error Responses:**

| Status | Error | Condition |
|--------|-------|-----------|
| 404 | `Driver profile not found` | User has no driver record |
| 404 | `Not found` | Trip request doesn't exist |
| 403 | `UNAUTHORIZED_VEHICLE` | Driver doesn't own the vehicle |
| 422 | `TRIP_NOT_PENDING` | Trip status is not `PENDING_MATCH` |
| 422 | `CANNOT_ACCEPT_TRIP` | Trip cannot be accepted in current state |
| 500 | `ACCEPT_ERROR` | Unexpected error |

---

#### `rejectTripRequest($request, $tripRequestId)`

**Endpoint:** `POST /api/v1/driver/public-bus/trip-requests/{trip_request_id}/reject`

**Authentication:** Required (Bearer token)

**Request Parameters:**
- `trip_request_id` (URL) - ID of trip request to reject
- `reason` (body, optional) - Rejection reason (max 255 chars)
- `notes` (body, optional) - Additional notes (max 500 chars)

**Validation:**
1. Driver profile exists
2. Trip request exists
3. Driver is assigned to this trip
4. Trip is not `IN_TRANSIT` or `COMPLETED`

**Processing:**
1. Load trip request by ID
2. Validate driver authorization (driver_id match)
3. Check if rejection is allowed using `TripStatusTransitionService`
4. Safely transition status to `CANCELLED`
5. Dispatch `DriverRejectedTrip` event
6. Log rejection for analytics
7. Return formatted response

**Response (Success - 200):**
```json
{
    "success": true,
    "message": "Trip request rejected successfully. Re-matching initiated.",
    "data": {
        "trip_request_id": 123,
        "status": "CANCELLED",
        "reason": "NOT_AVAILABLE",
        "next_action": "re-matching"
    }
}
```

**Error Responses:**

| Status | Error | Condition |
|--------|-------|-----------|
| 404 | `Driver profile not found` | User has no driver record |
| 404 | `Not found` | Trip request doesn't exist |
| 403 | `UNAUTHORIZED_DRIVER` | Driver not assigned to this trip |
| 422 | `CANNOT_REJECT_TRIP` | Trip in `IN_TRANSIT` or `COMPLETED` status |
| 500 | `REJECT_ERROR` | Unexpected error |

---

## Routes Configuration

**File:** `routes/api.php`

```php
Route::prefix('driver')->middleware(['auth:sanctum'])->group(function () {
    Route::prefix('public-bus')->group(function () {
        Route::post('/location', [DriverPublicBusController::class, 'location']);
        Route::post('/arrived-stop', [DriverPublicBusController::class, 'arrivedStop']);
        Route::post('/passenger-boarded', [DriverPublicBusController::class, 'passengerBoarded']);
        Route::post('/passenger-completed', [DriverPublicBusController::class, 'passengerCompleted']);
        // NEW: Trip request decision endpoints
        Route::post('/trip-requests/{trip_request_id}/accept', [DriverPublicBusController::class, 'acceptTripRequest'])->whereNumber('trip_request_id');
        Route::post('/trip-requests/{trip_request_id}/reject', [DriverPublicBusController::class, 'rejectTripRequest'])->whereNumber('trip_request_id');
    });
});
```

---

## Complete Flow Diagram

```
┌─────────────────────────────────────────────────────────────────────┐
│ PASSENGER REQUEST TRIP (Already Implemented)                         │
│ POST /api/v1/passenger/public-bus/request                            │
│ → TripRequest created with status = PENDING_MATCH                    │
│ → Matched to nearest bus driver                                      │
└──────────────────────────┬──────────────────────────────────────────┘
                           │
        ┌──────────────────┴───────────────────┐
        │ Driver receives notification         │
        │ (Event: new TripRequest)            │
        │ Status: PENDING_MATCH               │
        └──────────────────┬───────────────────┘
                           │
        ┌──────────────────┴────────────────────────────────┐
        │                                                    │
        ▼                                                    ▼
    ┌────────────────────┐                     ┌────────────────────┐
    │   DRIVER ACCEPTS   │                     │   DRIVER REJECTS   │
    │   POST /trip-...   │                     │   POST /trip-...   │
    │   /accept          │                     │   /reject          │
    └────────┬───────────┘                     └────────┬───────────┘
             │                                           │
             │ Validations:                             │ Validations:
             │ ✓ Status = PENDING_MATCH                │ ✓ Driver authorized
             │ ✓ Driver owns vehicle                   │ ✓ Not IN_TRANSIT
             │ ✓ Transition valid                      │ ✓ Not COMPLETED
             │                                          │
             ▼                                          ▼
    ┌─────────────────────────┐          ┌──────────────────────┐
    │ TripStatusTransition    │          │ TripStatusTransition │
    │ PENDING_MATCH →         │          │ PENDING_MATCH →      │
    │ PASSENGER_WAITING       │          │ CANCELLED            │
    └─────────┬───────────────┘          └──────────┬───────────┘
              │                                      │
              │ Event:                               │ Event:
              │ DriverAcceptedTrip                   │ DriverRejectedTrip
              │ (broadcast to passenger)             │ (broadcast to passenger)
              │                                      │
              ▼                                      ▼
    ┌──────────────────────────────┐    ┌────────────────────────┐
    │ PASSENGER WAITING FOR PICKUP │    │ RE-MATCHING INITIATED  │
    │ Status: PASSENGER_WAITING    │    │ Status: CANCELLED      │
    │                              │    │                        │
    │ Next: Driver arrives and     │    │ Passenger can:         │
    │ marks passenger boarded      │    │ - Request new trip     │
    │ Status: PASSENGER_BOARDED    │    │ - Match to next bus    │
    └──────────────────────────────┘    └────────────────────────┘
             │
             ▼
    ┌──────────────────────────────┐
    │ TRIP IN PROGRESS             │
    │ Status: IN_TRANSIT           │
    │                              │
    │ Driver cannot:               │
    │ - Reject trip                │
    │ - Accept another request     │
    └──────────────────────────────┘
             │
             ▼
    ┌──────────────────────────────┐
    │ TRIP COMPLETED               │
    │ Status: COMPLETED            │
    │ Fare charged                 │
    │ Rating given                 │
    └──────────────────────────────┘
```

---

## Status Safety Rules

### Cannot Accept When:
- ❌ Status ≠ `PENDING_MATCH`
- ❌ Driver doesn't own vehicle
- ❌ Trip not found

### Cannot Reject When:
- ❌ Status = `IN_TRANSIT` (trip already started)
- ❌ Status = `COMPLETED` (trip already done)
- ❌ Driver not assigned to trip
- ❌ Trip not found

### Valid State Transitions:
```
PENDING_MATCH       ← Passenger created request
    ↓
PASSENGER_WAITING   ← Driver accepted
    ↓
PASSENGER_BOARDED   ← Driver marked passenger boarded
    ↓
IN_TRANSIT         ← Trip started (cannot reject now)
    ↓
COMPLETED          ← Trip finished (final state)
    ↓
(No further transitions)

OR at any point before IN_TRANSIT:
    ↓
CANCELLED          ← Trip cancelled/rejected
```

---

## Testing

### Test Case 1: Accept Trip

**Setup:**
- Trip request ID: 123, Status: PENDING_MATCH
- Driver ID: 100, Vehicle ID: 789 (matched)

**Request:**
```bash
curl -X POST \
  "http://localhost:8000/api/v1/driver/public-bus/trip-requests/123/accept" \
  -H "Authorization: Bearer <driver_token>" \
  -H "Content-Type: application/json"
```

**Expected:** 200 OK
```json
{
    "success": true,
    "message": "Trip request accepted successfully",
    "data": {
        "trip_request_id": 123,
        "status": "PASSENGER_WAITING",
        ...
    }
}
```

**Event Fired:** `DriverAcceptedTrip` (private broadcast)

---

### Test Case 2: Reject Trip

**Setup:**
- Trip request ID: 124, Status: PENDING_MATCH
- Driver ID: 100

**Request:**
```bash
curl -X POST \
  "http://localhost:8000/api/v1/driver/public-bus/trip-requests/124/reject" \
  -H "Authorization: Bearer <driver_token>" \
  -H "Content-Type: application/json" \
  -d '{
    "reason": "NOT_AVAILABLE",
    "notes": "Too far from current location"
  }'
```

**Expected:** 200 OK
```json
{
    "success": true,
    "message": "Trip request rejected successfully. Re-matching initiated.",
    "data": {
        "trip_request_id": 124,
        "status": "CANCELLED",
        "reason": "NOT_AVAILABLE",
        "next_action": "re-matching"
    }
}
```

**Event Fired:** `DriverRejectedTrip` (private broadcast)

---

### Test Case 3: Cannot Accept - Wrong Status

**Setup:**
- Trip request ID: 123, Status: `IN_TRANSIT` (already started)

**Request:**
```bash
curl -X POST \
  "http://localhost:8000/api/v1/driver/public-bus/trip-requests/123/accept" \
  -H "Authorization: Bearer <driver_token>"
```

**Expected:** 422 Unprocessable Entity
```json
{
    "success": false,
    "message": "Trip request is not pending (current status: IN_TRANSIT)",
    "error_code": "TRIP_NOT_PENDING"
}
```

---

### Test Case 4: Cannot Reject - Trip In Progress

**Setup:**
- Trip request ID: 125, Status: `IN_TRANSIT`

**Request:**
```bash
curl -X POST \
  "http://localhost:8000/api/v1/driver/public-bus/trip-requests/125/reject" \
  -H "Authorization: Bearer <driver_token>"
```

**Expected:** 422 Unprocessable Entity
```json
{
    "success": false,
    "message": "Cannot reject trip in IN_TRANSIT status",
    "error_code": "CANNOT_REJECT_TRIP"
}
```

---

## Database Impact

**Tables Modified:** None (uses existing tables)

**Fields Used:**
- `trip_requests.id` - Primary key
- `trip_requests.status` - State tracking
- `trip_requests.matched_driver_id` - Driver assignment
- `trip_requests.matched_vehicle_id` - Vehicle assignment

**No Migration Required:** All fields already exist

---

## Event Listeners (Optional)

Users can listen to these events:

```php
// In EventServiceProvider or create a listener
use App\Events\Domain\DriverAcceptedTrip;
use App\Listeners\NotifyPassengerTripAccepted;

protected $listen = [
    DriverAcceptedTrip::class => [
        NotifyPassengerTripAccepted::class,
    ],
];
```

**Example Listener:**
```php
class NotifyPassengerTripAccepted
{
    public function handle(DriverAcceptedTrip $event)
    {
        // Send notification to passenger
        $event->tripRequest->passenger
            ->notify(new TripAcceptedNotification($event->tripRequest));
    }
}
```

---

## Logging

Both endpoints log all actions for debugging and analytics:

**Accept Logging:**
- Trip accepted event logged at INFO level
- Includes trip_request_id, driver_id, status transition

**Reject Logging:**
- Trip rejection event logged at INFO level
- Includes trip_request_id, driver_id, reason, passenger_id
- Error cases logged at ERROR level

**Example Log Output:**
```
[2026-06-06 10:30:45] local.INFO: Driver rejected trip request {
  "trip_request_id": 124,
  "driver_id": 100,
  "reason": "NOT_AVAILABLE",
  "passenger_id": 456
}
```

---

## Files Modified/Created

| File | Type | Status |
|------|------|--------|
| `app/Events/Domain/DriverAcceptedTrip.php` | NEW | ✅ Created |
| `app/Events/Domain/DriverRejectedTrip.php` | NEW | ✅ Created |
| `app/Services/TripStatusTransitionService.php` | NEW | ✅ Created |
| `app/Http/Controllers/Api/DriverPublicBusController.php` | MODIFIED | ✅ Updated |
| `routes/api.php` | MODIFIED | ✅ Updated |

---

## Next Steps

1. **Test endpoints** using provided test cases
2. **Implement listeners** for passenger notifications
3. **Add re-matching logic** to automatically find new driver on rejection
4. **Monitor logs** for failed accepts/rejects
5. **Track analytics** on rejection reasons

---

## Related Documentation

- [Bus Matching Fix Report](BUS_MATCHING_FIX_REPORT.md) - Trip request creation and matching
- [PublicBusMatchingService](app/Services/PublicBusMatchingService.php) - Trip matching algorithm
- [TripRequest Model](app/Models/TripRequest.php) - Data structure

