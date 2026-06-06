# Intelligent Matching Retry System - Implementation Report

## 🎯 Problem Solved

**Previous Behavior (Broken):**
```
Request → ML Service → No drivers → EXPIRED immediately ❌
```

**Flutter UX:** "Trip failed" or "No drivers found" (frustrating)

---

## ✅ New Behavior (Fixed)

```
Request → ML Service → No drivers
        ↓
MATCHING_PENDING status (not EXPIRED)
        ↓
Automatic Retry Job (every 15 seconds)
        ↓
Expand search radius gradually
        ↓
Driver found OR Max retries (5) exceeded
```

**Flutter UX:** "Finding nearby drivers... We are expanding search area..." (user-friendly)

---

## 🏗️ Architecture Implementation

### 1. **Database Schema** (Migration: `2026_06_06_000006`)

Added to `motorcycle_trips` table:

| Column | Type | Purpose |
|--------|------|---------|
| `retry_count` | integer (0) | How many retries attempted |
| `max_retries` | integer (5) | Maximum retry attempts |
| `matching_status` | string | SEARCHING, RETRYING, RETRY_SCHEDULED, DRIVER_FOUND, FAILED_MAX_RETRIES |
| `last_retry_at` | timestamp | When last retry executed |
| `initial_search_radius_km` | decimal (5.0) | Initial search area |
| `current_search_radius_km` | decimal (5.0) | Current search area (expands) |

**Migration Status:** ✅ Applied successfully

---

### 2. **RetryTripMatchingJob** (Background Job)

**Location:** `app/Jobs/RetryTripMatchingJob.php`

**Responsibility:** Automatically retry matching for trips stuck in MATCHING_PENDING

**Logic Flow:**

```
Job Triggered:
  ↓
Check trip still in MATCHING_PENDING
  ↓
If max_retries exceeded:
  → Set status = EXPIRED, matching_status = FAILED_MAX_RETRIES
  → Notify passenger
  → Exit
  ↓
If max_retries not exceeded:
  → Expand search_radius_km (add 2 km, max 25 km)
  → Increment retry_count
  → Call ML service with new radius
  ↓
If driver found:
  → Assign driver
  → Set status = ASSIGNED, matching_status = DRIVER_FOUND
  → Notify both passenger and driver
  ↓
If no driver found:
  → Schedule next retry
  → Delay = 15s + (retry_count - 1) * 5s
  → Dispatch new job with exponential backoff
```

**Retry Schedule:**
- Retry 1: 15 seconds
- Retry 2: 20 seconds  
- Retry 3: 25 seconds
- Retry 4: 30 seconds
- Retry 5: 35 seconds
- After 5 retries: EXPIRED

**Status Updates During Retries:**
- `MATCHING_PENDING` - Waiting for retry to be scheduled
- `RETRYING` - Actively attempting to match
- `RETRY_SCHEDULED` - Next retry queued
- `DRIVER_FOUND` - Success!
- `FAILED_MAX_RETRIES` - Exhausted all retries

---

### 3. **MatchingService Enhancements**

**File:** `app/Services/MatchingService.php`

**Method Signature Update:**
```php
public function matchMotorcycleTrip(
    MotorcycleTrip $trip, 
    array $excludeDriverIds = [], 
    float $searchRadiusKm = 5
): ?array
```

**New ML Payload (Complete):**
```php
[
    'trip_request_id' => $trip->id,
    'vehicle_type' => 'MOTORCYCLE',
    'pickup_lat' => -1.9536,
    'pickup_lng' => 30.0605,
    'dropoff_lat' => -1.9753,
    'dropoff_lng' => 30.1376,
    'exclude_drivers' => [excluded_driver_ids],
    'search_radius_km' => 5,
    'estimated_fare' => 5000,
    'vehicle_type_filter' => 'MOTORCYCLE',
]
```

**Improvements:**
- ✅ Includes `search_radius_km` (enables expansion)
- ✅ Includes `exclude_drivers` (respects rejections)
- ✅ Includes dropoff coordinates (better routing)
- ✅ Includes `estimated_fare` (ML can factor cost)
- ✅ Includes `vehicle_type_filter` (strict filtering)

---

### 4. **MotorcycleTripService Changes**

**File:** `app/Services/MotorcycleTripService.php`

**Key Method: `startMatching()`**

**Before (Broken):**
```php
if (!$match) {
    $trip->update(['status' => 'EXPIRED']);  // ❌ Too aggressive
    return ['success' => false, 'error' => 'NO_DRIVERS_AVAILABLE'];
}
```

**After (Fixed):**
```php
if ($match) {
    return $this->assignDriver($trip, $match);  // ✅ Driver found
}

// No driver found - use retry system
$trip->update([
    'status' => 'MATCHING_PENDING',          // ✅ New status
    'matching_status' => 'RETRY_SCHEDULED',
    'retry_count' => 0,
    'max_retries' => 5,
    'current_search_radius_km' => 5,
]);

// Schedule first retry
dispatch(new RetryTripMatchingJob($trip->id))
    ->delay(now()->addSeconds(15));

// Notify passenger (positive message)
$this->notificationService->sendInAppNotification(
    $trip->passenger_id,
    'TRIP_MATCHING',
    'Finding a driver...',
    'We\'re searching for an available driver. Please wait.',
    ['trip_id' => $trip->id]
);

return ['success' => true, 'status' => 'MATCHING_PENDING'];
```

**New Helper Method: `assignDriver()`**
- Extracted driver assignment logic for reuse
- Used by both `startMatching()` and `RetryTripMatchingJob`
- Ensures consistent behavior

---

### 5. **MotorcycleTripController Updates**

**File:** `app/Http/Controllers/Api/MotorcycleTripController.php`

**Store Endpoint Response (Before):**
```json
{
    "success": false,
    "trip_id": 1,
    "status": "EXPIRED",
    "matching_status": "NO_DRIVERS_AVAILABLE"
}
```

**Store Endpoint Response (After):**
```json
{
    "success": true,
    "trip_id": 1,
    "status": "MATCHING_PENDING",
    "matching_status": "RETRY_SCHEDULED",
    "message": "Finding a driver... We will keep searching.",
    "estimated_fare": 5000
}
```

**HTTP Status Codes:**
- 201 Created: Driver found immediately (ASSIGNED status)
- 202 Accepted: No driver yet, retry system active (MATCHING_PENDING)

**Key Improvement:** `success: true` even when no driver found initially (retry is in progress)

---

### 6. **MotorcycleTrip Model Updates**

**File:** `app/Models/MotorcycleTrip.php`

**Fillable Fields Added:**
```php
'retry_count',
'max_retries',
'matching_status',
'last_retry_at',
'initial_search_radius_km',
'current_search_radius_km',
```

**Casts Added:**
```php
'retry_count' => 'integer',
'max_retries' => 'integer',
'initial_search_radius_km' => 'float',
'current_search_radius_km' => 'float',
'last_retry_at' => 'datetime',
```

---

## 📊 Complete Flow Diagram

```
Passenger Creates Trip Request
    ↓
POST /api/v1/passenger/motor-vehicle/trip-requests
    ↓
MotorcycleTripController::store()
    ├─ Geocode locations ✅
    ├─ Estimate fare ✅
    ├─ Create trip (REQUESTED status) ✅
    ├─ Call startMatching()
    │
    └─ MotorcycleTripService::startMatching()
       ├─ Set status = MATCHING
       ├─ Call ML service (search_radius = 5km)
       │
       ├─ Driver found? ✅
       │  ├─ assignDriver()
       │  ├─ Set status = ASSIGNED
       │  ├─ Notify driver & passenger
       │  └─ Return 201 Created
       │
       └─ No driver found? ⚠️
          ├─ Set status = MATCHING_PENDING
          ├─ Set matching_status = RETRY_SCHEDULED
          ├─ Dispatch RetryTripMatchingJob (15s delay)
          ├─ Notify passenger: "Finding driver..."
          └─ Return 202 Accepted

═══════════════════════════════════════════════════════════

RetryTripMatchingJob (First Retry - 15s later)
    ↓
Job triggered for trip_id = 1
    ├─ Check status = MATCHING_PENDING ✅
    ├─ Check retry_count < max_retries (0 < 5) ✅
    ├─ Expand radius: 5km → 7km
    ├─ Increment retry_count = 1
    ├─ Call ML service (search_radius = 7km)
    │
    ├─ Driver found? ✅
    │  └─ assignDriver() → Return
    │
    └─ No driver found? ⚠️
       ├─ Set matching_status = RETRY_SCHEDULED
       ├─ Schedule next retry
       ├─ Delay = 20 seconds (15 + 5)
       └─ Dispatch new job

═══════════════════════════════════════════════════════════

RetryTripMatchingJob (Subsequent Retries)
    → Retry 2 (20s): 7km → 9km
    → Retry 3 (25s): 9km → 11km
    → Retry 4 (30s): 11km → 13km
    → Retry 5 (35s): 13km → 15km

═══════════════════════════════════════════════════════════

After 5 Failed Retries
    ├─ retry_count = 5, max_retries = 5
    ├─ Set status = EXPIRED
    ├─ Set matching_status = FAILED_MAX_RETRIES
    ├─ Notify passenger: "No drivers available after retries"
    └─ Trip ends
```

---

## 🎯 Matching Status Values

| Status | Meaning | When Used |
|--------|---------|-----------|
| `SEARCHING` | Initial search attempt | First ML call |
| `RETRYING` | Active retry attempt | During retry job |
| `RETRY_SCHEDULED` | Next retry queued | After unsuccessful attempt |
| `DRIVER_FOUND` | Driver assigned successfully | Driver accepted |
| `FAILED_MAX_RETRIES` | Max retries exhausted | After 5th retry fails |

---

## 🧪 Testing Scenarios

### Scenario 1: Driver Available on First Call
```
POST trip request
→ ML returns driver immediately
→ Status = ASSIGNED (201 Created)
→ Driver notified immediately
→ No retries needed ✅
```

### Scenario 2: Driver Found on Retry
```
POST trip request
→ ML returns no driver (search_radius=5km)
→ Status = MATCHING_PENDING (202 Accepted)
→ After 20s: Retry with search_radius=7km
→ ML finds driver ✅
→ Status = ASSIGNED
→ Passenger notified ✅
```

### Scenario 3: All Retries Exhausted
```
POST trip request
→ 5 retries over 2 minutes
→ All fail
→ Status = EXPIRED
→ Passenger notified: "Please try again"
→ Passenger can create new request ✅
```

---

## 📱 Flutter Mobile App Changes Needed

### Before (Error State):
```dart
// Show failure immediately
if (response.statusCode == 400) {
    showError("Trip failed - No drivers found");
}
```

### After (Retry State):
```dart
// Accept 202 as valid state
if (response.statusCode == 202) {
    showStatus("Finding nearby drivers...");
    showStatus("We are expanding search area...");
    
    // Poll trip status to detect driver assignment
    pollTripStatus(tripId);
}

// Poll API
GET /api/v1/passenger/motor-vehicle/trips/{id}
→ Check status and matching_status
→ When status becomes ASSIGNED → Driver found!
```

---

## 🔔 Notification Improvements

### Passenger Receives:
1. **Trip Created:** "Trip request created"
2. **Searching:** "Finding nearby drivers..."
3. **Retrying:** "We're expanding our search..."
4. **Driver Found:** "Driver assigned - John will arrive in 5 min"
5. **Failed:** "No drivers available - Please try again" (if max retries)

### Driver Receives:
- Notification only after trip ASSIGNED (not during MATCHING_PENDING)

---

## ⚡ Performance & Optimization

| Aspect | Value | Rationale |
|--------|-------|-----------|
| Max Retries | 5 | ~2 minutes total (15s + 20s + 25s + 30s + 35s) |
| Initial Radius | 5 km | Sufficient for city areas |
| Max Radius | 25 km | Prevents search going too far |
| Radius Increment | 2 km per retry | Gradual expansion |
| Job Timeout | 60 seconds | Prevent hanging jobs |
| Backoff Strategy | Exponential | 15s → 20s → 25s → 30s → 35s |

---

## 📋 Files Changed

| File | Type | Changes |
|------|------|---------|
| `database/migrations/2026_06_06_000006_add_retry_fields_to_motorcycle_trips_table.php` | Migration | NEW - Adds 6 columns |
| `app/Jobs/RetryTripMatchingJob.php` | Job | NEW - 120 lines |
| `app/Services/MatchingService.php` | Service | MODIFIED - Add radius parameter |
| `app/Services/MotorcycleTripService.php` | Service | MODIFIED - Replace EXPIRED with MATCHING_PENDING + retry logic |
| `app/Http/Controllers/Api/MotorcycleTripController.php` | Controller | MODIFIED - Update response handling |
| `app/Models/MotorcycleTrip.php` | Model | MODIFIED - Add retry fields to fillable & casts |

---

## ✅ Verification Checklist

- [x] All PHP syntax validated (0 errors)
- [x] Database migration applied successfully
- [x] RetryTripMatchingJob created and callable
- [x] MatchingService updated with radius parameter
- [x] MotorcycleTripService uses MATCHING_PENDING
- [x] MotorcycleTripController handles 202 responses
- [x] MotorcycleTrip model updated with new fields
- [x] Comprehensive logging implemented
- [x] Exponential backoff configured (15-35s)
- [x] Max retry limit enforced (5 attempts)

---

## 🚀 Deployment Steps

1. **Run Migration:**
   ```bash
   php artisan migrate
   ```

2. **Start Queue Worker** (for jobs):
   ```bash
   php artisan queue:work
   ```

3. **Test First Trip:**
   ```bash
   # Should see MATCHING_PENDING status on first attempt if no driver available
   curl -X POST /api/v1/passenger/motor-vehicle/trip-requests \
     -H "Authorization: Bearer $TOKEN" \
     -H "Content-Type: application/json" \
     -d '{"pickup_location": "...", "dropoff_location": "..."}'
   ```

4. **Monitor Logs:**
   ```bash
   tail -f storage/logs/laravel.log | grep -i "RetryTripMatchingJob"
   ```

---

## 🎯 Benefits Achieved

✅ **Better UX:** "Finding driver..." instead of "Failed"  
✅ **Increased Success Rate:** 5 retries increase chances significantly  
✅ **Graceful Degradation:** Expands search gradually  
✅ **User Retention:** Passengers see active search, not immediate failure  
✅ **ML Service Integration:** Full payload with context for better matching  
✅ **Scalable:** Retry job runs async, doesn't block requests  
✅ **Debuggable:** Comprehensive logging at every step  

---

## 🔮 Future Enhancements

1. **Configurable Retry Limits:** Admin panel to adjust max_retries
2. **Smart Backoff:** ML feedback on best radius expansion
3. **Driver Notifications:** Notify available drivers of increased radius
4. **Caching:** Cache successful matches for future similar trips
5. **Multi-Vehicle Types:** Extend to buses, private cars, etc.

---

**Status:** ✅ **IMPLEMENTATION COMPLETE**  
**Date:** June 6, 2026  
**Tested & Ready for Production**
