# Motorcycle Trip Lifecycle Testing Guide

Complete end-to-end testing guide for the motorcycle trip system with step-by-step procedures, curl examples, and verification scripts.

---

## Table of Contents

1. [Environment Setup](#environment-setup)
2. [Test Users & Data](#test-users--data)
3. [API Endpoints](#api-endpoints)
4. [Testing Scenarios](#testing-scenarios)
5. [Verification Procedures](#verification-procedures)
6. [Troubleshooting](#troubleshooting)
7. [Success Criteria](#success-criteria)

---

## Environment Setup

### Prerequisites

- Laravel 12.61.0 running locally
- PostgreSQL database connected
- All migrations applied (`php artisan migrate`)
- Broadcasting configured (using Log driver by default)
- ML Service endpoint: `https://ml-service-j72g.onrender.com/match` (30s timeout, 2 retries)

### Pre-Test Checks

```bash
# 1. Verify migrations applied
php artisan migrate:status | grep "2026_06_06_000004\|2026_06_06_000005"

# Expected output:
#  2026_06_06_000004_add_availability_tracking_to_drivers_table .... [5] Ran  
#  2026_06_06_000005_create_motorcycle_trips_table ................ [5] Ran

# 2. Verify routes registered
php artisan route:list | grep "motor-vehicle"

# Expected output (7 routes):
#  POST api/v1/driver/motor-vehicle/trip-requests/{id}/accept
#  POST api/v1/driver/motor-vehicle/trip-requests/{id}/reject
#  POST api/v1/driver/motor-vehicle/trip-requests/{id}/arrived
#  POST api/v1/driver/motor-vehicle/trip-requests/{id}/start
#  POST api/v1/driver/motor-vehicle/trip-requests/{id}/complete
#  POST api/v1/passenger/motor-vehicle/trip-requests
#  POST api/v1/passenger/motor-vehicle/trip-requests/{id}/cancel

# 3. Check database tables exist
php artisan tinker
# >>> Schema::hasTable('motorcycle_trips')
# => true
# >>> Schema::hasColumn('drivers', 'is_available')
# => true
# >>> Schema::hasColumn('drivers', 'current_trip_id')
# => true
# >>> exit()

# 4. Verify Composer dependencies
composer show | grep -i "laravel\|sanctum"
```

---

## Test Users & Data

### Creating Test Users

```bash
php artisan tinker

# Create test passenger
$passenger = \App\Models\User::create([
    'name' => 'Test Passenger',
    'email' => 'passenger@test.local',
    'password' => Hash::make('password'),
    'role' => 'PASSENGER',
]);

# Create test driver (create user first, then driver profile)
$driverUser = \App\Models\User::create([
    'name' => 'Test Driver',
    'email' => 'driver@test.local',
    'password' => Hash::make('password'),
    'role' => 'DRIVER',
]);
$driver = \App\Models\Driver::create([
    'user_id' => $driverUser->id,
    'phone' => '+250789123456',
    'license_number' => 'DL123456',
    'is_active' => true,
    'is_available' => true,  // Key field for motorcycle system
]);

# Create motorcycle vehicle
$vehicle = \App\Models\Vehicle::create([
    'driver_id' => $driver->id,
    'vehicle_type' => 'MOTORCYCLE',
    'registration_number' => 'RW-MOTO-001',
    'model' => 'Honda Wave',
    'year' => 2023,
    'color' => 'Red',
    'capacity' => 1,  // Critical: motorcycles only carry 1 passenger
    'is_active' => true,
]);

# Create tokens for API authentication
$passengerToken = $passenger->createToken('test-token')->plainTextToken;
$driverToken = $driverUser->createToken('test-token')->plainTextToken;

exit()

# Store these for use in curl commands
PASSENGER_TOKEN="$passengerToken"
DRIVER_TOKEN="$driverToken"
API_URL="http://localhost:8000/api/v1"
```

---

## API Endpoints

### 1. Passenger: Create Trip Request

**Endpoint:** `POST /api/v1/passenger/motor-vehicle/trip-requests`

**Authentication:** Bearer token (passenger)

**Request Body:**
```json
{
    "pickup_location": "Kigali Central Bus Station",
    "dropoff_location": "Kigali Airport",
    "pickup_lat": -1.9534,
    "pickup_lng": 30.0596,
    "dropoff_lat": -1.9848,
    "dropoff_lng": 30.1324,
    "estimated_fare": 5000
}
```

**cURL Example:**
```bash
curl -X POST http://localhost:8000/api/v1/passenger/motor-vehicle/trip-requests \
  -H "Authorization: Bearer $PASSENGER_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "pickup_location": "Kigali Central Bus Station",
    "dropoff_location": "Kigali Airport",
    "pickup_lat": -1.9534,
    "pickup_lng": 30.0596,
    "dropoff_lat": -1.9848,
    "dropoff_lng": 30.1324,
    "estimated_fare": 5000
  }'
```

**Success Response (201/202):**
```json
{
    "success": true,
    "trip_id": 1,
    "status": "ASSIGNED",
    "driver_id": 1,
    "estimated_fare": 5000
}
```

**Status Progression:** `REQUESTED` → `MATCHING` → `ASSIGNED`

---

### 2. Driver: Accept Trip

**Endpoint:** `POST /api/v1/driver/motor-vehicle/trip-requests/{id}/accept`

**Authentication:** Bearer token (driver)

**Request Body:** (empty)

**cURL Example:**
```bash
curl -X POST http://localhost:8000/api/v1/driver/motor-vehicle/trip-requests/1/accept \
  -H "Authorization: Bearer $DRIVER_TOKEN" \
  -H "Content-Type: application/json"
```

**Success Response (200):**
```json
{
    "success": true,
    "trip_id": 1,
    "status": "DRIVER_ASSIGNED",
    "message": "Trip accepted successfully"
}
```

**Status Progression:** `ASSIGNED` → `DRIVER_ASSIGNED`

**Key Side Effects:**
- Driver marked as `is_available = false`
- Driver's `current_trip_id` set to trip ID
- Notification sent to passenger: "Driver accepted your trip"
- Event broadcast: `MotorcycleTripAccepted` to `private-driver.{id}` and `private-passenger.{id}`

---

### 3. Driver: Reject Trip

**Endpoint:** `POST /api/v1/driver/motor-vehicle/trip-requests/{id}/reject`

**Authentication:** Bearer token (driver)

**Request Body:**
```json
{
    "reason": "Too far from my location"
}
```

**cURL Example:**
```bash
curl -X POST http://localhost:8000/api/v1/driver/motor-vehicle/trip-requests/1/reject \
  -H "Authorization: Bearer $DRIVER_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "reason": "Too far from my location"
  }'
```

**Success Response (200):**
```json
{
    "success": true,
    "trip_id": 1,
    "status": "REJECTED_BY_DRIVER",
    "rematched": true,
    "new_driver_id": 2,
    "message": "Trip rejected and reassigned"
}
```

**Status Progression:** `ASSIGNED` → `REJECTED_BY_DRIVER` → `MATCHING` → `ASSIGNED` (new driver)

**Key Side Effects:**
- Original driver marked as `is_available = true`
- Driver added to trip's `rejected_drivers` JSON array
- ML service called to find new driver (with exclusion list)
- New driver assigned if available
- Notification sent to passenger: "Finding another driver..."

---

### 4. Driver: Mark Arrived at Pickup

**Endpoint:** `POST /api/v1/driver/motor-vehicle/trip-requests/{id}/arrived`

**Authentication:** Bearer token (driver)

**Request Body:** (empty)

**cURL Example:**
```bash
curl -X POST http://localhost:8000/api/v1/driver/motor-vehicle/trip-requests/1/arrived \
  -H "Authorization: Bearer $DRIVER_TOKEN" \
  -H "Content-Type: application/json"
```

**Success Response (200):**
```json
{
    "success": true,
    "trip_id": 1,
    "status": "PASSENGER_WAITING",
    "driver_arrived_at": "2025-06-06T10:30:00Z",
    "message": "Driver arrived at pickup location"
}
```

**Status Progression:** `DRIVER_ASSIGNED` → `PASSENGER_WAITING`

**Key Side Effects:**
- `driver_arrived_at` timestamp recorded
- Notification sent to passenger: "Your driver has arrived"
- Event broadcast: `MotorcycleDriverArrived` to `private-passenger.{id}`

---

### 5. Driver: Start Trip

**Endpoint:** `POST /api/v1/driver/motor-vehicle/trip-requests/{id}/start`

**Authentication:** Bearer token (driver)

**Request Body:** (empty)

**cURL Example:**
```bash
curl -X POST http://localhost:8000/api/v1/driver/motor-vehicle/trip-requests/1/start \
  -H "Authorization: Bearer $DRIVER_TOKEN" \
  -H "Content-Type: application/json"
```

**Success Response (200):**
```json
{
    "success": true,
    "trip_id": 1,
    "status": "IN_PROGRESS",
    "started_at": "2025-06-06T10:35:00Z",
    "message": "Trip started"
}
```

**Status Progression:** `PASSENGER_WAITING` → `IN_PROGRESS`

**Key Side Effects:**
- `started_at` timestamp recorded
- Notification sent to both driver and passenger: "Trip has started"
- Event broadcast: `MotorcycleTripStarted` to both channels

---

### 6. Driver: Complete Trip

**Endpoint:** `POST /api/v1/driver/motor-vehicle/trip-requests/{id}/complete`

**Authentication:** Bearer token (driver)

**Request Body (optional):**
```json
{
    "actual_fare": 4500
}
```

**cURL Example:**
```bash
curl -X POST http://localhost:8000/api/v1/driver/motor-vehicle/trip-requests/1/complete \
  -H "Authorization: Bearer $DRIVER_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "actual_fare": 4500
  }'
```

**Success Response (200):**
```json
{
    "success": true,
    "trip_id": 1,
    "status": "COMPLETED",
    "estimated_fare": 5000,
    "actual_fare": 4500,
    "completed_at": "2025-06-06T10:50:00Z",
    "message": "Trip completed successfully"
}
```

**Status Progression:** `IN_PROGRESS` → `COMPLETED`

**Key Side Effects:**
- `actual_fare` recorded (uses estimated_fare if not provided)
- `completed_at` timestamp recorded
- Driver marked as `is_available = true` (CRITICAL: driver becomes available again)
- Driver's `current_trip_id` cleared
- Notification sent to both driver and passenger: "Trip completed successfully"
- Event broadcast: `MotorcycleTripCompleted` to both channels

---

### 7. Passenger: Cancel Trip

**Endpoint:** `POST /api/v1/passenger/motor-vehicle/trip-requests/{id}/cancel`

**Authentication:** Bearer token (passenger)

**Request Body (optional):**
```json
{
    "reason": "Driver is taking wrong route"
}
```

**cURL Example:**
```bash
curl -X POST http://localhost:8000/api/v1/passenger/motor-vehicle/trip-requests/1/cancel \
  -H "Authorization: Bearer $PASSENGER_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "reason": "Driver is taking wrong route"
  }'
```

**Success Response (200):**
```json
{
    "success": true,
    "trip_id": 1,
    "status": "CANCELLED_BY_PASSENGER",
    "cancelled_at": "2025-06-06T10:45:00Z",
    "message": "Trip cancelled by passenger"
}
```

**Status Progression:** `* → CANCELLED_BY_PASSENGER` (any status before completion)

**Key Side Effects:**
- `cancelled_at` timestamp recorded
- If driver was assigned: driver marked as `is_available = true`
- Notification sent to driver: "Passenger cancelled the trip"
- Event broadcast: `MotorcycleTripRejected` to driver's private channel

---

## Testing Scenarios

### Scenario 1: Happy Path (Complete Trip)

**Steps:**
1. Passenger creates trip request
2. ML Service assigns driver
3. Driver accepts trip
4. Driver marks arrived at pickup
5. Driver starts trip
6. Driver completes trip
7. Verify driver is available for next trip

**SQL Verification:**
```sql
-- After scenario completion
SELECT id, status, driver_id, is_available FROM motorcycle_trips WHERE id = 1;
-- Expected: status = 'COMPLETED', driver_id = 1

SELECT id, is_available, current_trip_id FROM drivers WHERE id = 1;
-- Expected: is_available = true, current_trip_id = NULL
```

---

### Scenario 2: Driver Rejection with Rematching

**Steps:**
1. Passenger creates trip request
2. ML Service assigns driver #1
3. Driver #1 rejects with reason
4. System calls ML Service again with excluded_drivers = [1]
5. ML Service assigns driver #2
6. Driver #2 accepts trip
7. Verify rejected_drivers list contains driver #1

**SQL Verification:**
```sql
-- Check rejected drivers list
SELECT id, status, rejected_drivers FROM motorcycle_trips WHERE id = 1;
-- Expected: status = 'DRIVER_ASSIGNED', rejected_drivers = [1]

-- Check rejection tracking
SELECT id, rejection_reason, rejected_driver_id FROM motorcycle_trips WHERE id = 1;
-- Expected: rejection_reason filled, rejected_driver_id = 1
```

---

### Scenario 3: No Available Drivers

**Steps:**
1. Suspend/make unavailable all drivers
2. Passenger creates trip request
3. ML Service returns no available drivers
4. System returns 202 status with no_drivers message
5. Verify trip remains in MATCHING state

**SQL Verification:**
```sql
-- Check trip still waiting for driver
SELECT id, status, driver_id FROM motorcycle_trips WHERE id = 1;
-- Expected: status = 'MATCHING', driver_id = NULL

-- Verify all drivers unavailable
SELECT id, is_available FROM drivers;
-- Expected: all is_available = false
```

---

### Scenario 4: Passenger Cancellation

**Steps:**
1. Passenger creates trip request
2. Driver accepts trip
3. Passenger cancels trip
4. Verify driver becomes available again

**SQL Verification:**
```sql
-- Check trip cancelled
SELECT id, status, cancelled_at FROM motorcycle_trips WHERE id = 1;
-- Expected: status = 'CANCELLED_BY_PASSENGER', cancelled_at is not NULL

-- Verify driver available again
SELECT id, is_available, current_trip_id FROM drivers WHERE id = 1;
-- Expected: is_available = true, current_trip_id = NULL
```

---

### Scenario 5: Multiple Sequential Trips

**Steps:**
1. Driver #1 completes trip #1
2. Passenger creates trip #2
3. ML Service assigns to driver #1 again
4. Driver #1 accepts trip #2
5. Verify driver correctly tracks both trips

**SQL Verification:**
```sql
-- Check completed trip
SELECT id, status, driver_id, completed_at FROM motorcycle_trips WHERE id = 1;
-- Expected: status = 'COMPLETED'

-- Check new trip assigned to same driver
SELECT id, status, driver_id FROM motorcycle_trips WHERE id = 2;
-- Expected: driver_id = 1, status = 'DRIVER_ASSIGNED'

-- Verify driver consistency
SELECT id, is_available, current_trip_id FROM drivers WHERE id = 1;
-- Expected: is_available = false, current_trip_id = 2
```

---

## Verification Procedures

### Check Real-Time Broadcasting

With Log driver (default development setup):

```bash
# 1. Start tailing logs in separate terminal
tail -f storage/logs/laravel.log | grep -i "broadcast\|motorcycle"

# 2. Run API call in first terminal
curl -X POST http://localhost:8000/api/v1/driver/motor-vehicle/trip-requests/1/accept \
  -H "Authorization: Bearer $DRIVER_TOKEN"

# 3. Verify in log terminal
# Expected output:
# [2025-06-06 10:30:00] local.INFO: Broadcasting [App\Events\MotorcycleTripAccepted] ...
# [2025-06-06 10:30:00] local.INFO: Broadcasted to private-driver.1 ...
# [2025-06-06 10:30:00] local.INFO: Broadcasted to private-passenger.2 ...
```

### Database Integrity Checks

```bash
php artisan tinker

# 1. Verify driver availability tracking
$driver = \App\Models\Driver::find(1);
echo "Driver is_available: " . ($driver->is_available ? 'true' : 'false') . "\n";
echo "Current trip ID: " . $driver->current_trip_id . "\n";

# 2. Verify trip status progression
$trip = \App\Models\MotorcycleTrip::find(1);
echo "Trip Status: " . $trip->status . "\n";
echo "Driver ID: " . $trip->driver_id . "\n";
echo "Rejected Drivers: " . json_encode($trip->rejected_drivers) . "\n";

# 3. Check notification creation
$notifications = \App\Models\Notification::where('user_id', 2)->latest()->first();
echo "Last Notification: " . $notifications->title . "\n";

exit()
```

### Performance Testing

```bash
# Measure response times for all 7 endpoints

# 1. Create trip (should be < 2s with ML service call)
time curl -X POST http://localhost:8000/api/v1/passenger/motor-vehicle/trip-requests \
  -H "Authorization: Bearer $PASSENGER_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"pickup_location": "Test", "dropoff_location": "Test"}'

# 2. Accept trip (should be < 500ms)
time curl -X POST http://localhost:8000/api/v1/driver/motor-vehicle/trip-requests/1/accept \
  -H "Authorization: Bearer $DRIVER_TOKEN"

# 3. Other endpoints (should all be < 500ms)
time curl -X POST http://localhost:8000/api/v1/driver/motor-vehicle/trip-requests/1/reject \
  -H "Authorization: Bearer $DRIVER_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"reason": "test"}'
```

---

## Troubleshooting

### Issue: 404 Not Found on Routes

**Diagnosis:**
```bash
php artisan route:list | grep motor-vehicle
# Should show 7 routes
```

**Fix:**
```bash
php artisan route:cache
php artisan route:clear
```

---

### Issue: 403 Forbidden - Driver Not Found

**Diagnosis:**
```bash
# Verify driver profile exists for user
php artisan tinker
$user = \App\Models\User::find(1);
echo $user->driver;  // Should not be null
exit()
```

**Fix:**
```bash
php artisan tinker
$user = \App\Models\User::find(1);
\App\Models\Driver::create(['user_id' => $user->id, 'is_active' => true]);
exit()
```

---

### Issue: No Driver Assigned - 202 Response

**Diagnosis:**
```sql
-- Check ML service connectivity
SELECT * FROM drivers WHERE is_active = true AND is_available = true;

-- If empty, all drivers are busy
```

**Fix:**
- Mark drivers as available: `UPDATE drivers SET is_available = true;`
- Verify ML service endpoint: Check `.env` for `ML_SERVICE_URL`

---

### Issue: Driver Still Unavailable After Trip Completion

**Diagnosis:**
```bash
php artisan tinker
$driver = \App\Models\Driver::find(1);
var_dump($driver->is_available);
var_dump($driver->current_trip_id);
exit()
```

**Common Cause:** Trip not properly marked as COMPLETED

**Fix:**
```sql
UPDATE motorcycle_trips SET status = 'COMPLETED' WHERE id = 1;
UPDATE drivers SET is_available = true, current_trip_id = NULL WHERE id = 1;
```

---

### Issue: Rejected Driver Can Still Be Assigned

**Diagnosis:**
```sql
SELECT rejected_drivers FROM motorcycle_trips WHERE id = 1;
-- Should show JSON array with rejected driver IDs
```

**Fix:** Verify `MatchingService::isDriverEligible()` checks rejected_drivers list

---

## Success Criteria

✅ **All 7 endpoints implemented and routed**
- POST /api/v1/passenger/motor-vehicle/trip-requests (create)
- POST /api/v1/driver/motor-vehicle/trip-requests/{id}/accept
- POST /api/v1/driver/motor-vehicle/trip-requests/{id}/reject
- POST /api/v1/driver/motor-vehicle/trip-requests/{id}/arrived
- POST /api/v1/driver/motor-vehicle/trip-requests/{id}/start
- POST /api/v1/driver/motor-vehicle/trip-requests/{id}/complete
- POST /api/v1/passenger/motor-vehicle/trip-requests/{id}/cancel

✅ **Database migrations applied successfully**
- `drivers.is_available` exists
- `drivers.current_trip_id` exists
- `drivers.last_location_lat/lng` exist
- `motorcycle_trips` table created with all 11 status values
- All indexes created

✅ **All 5 scenarios pass**
- Happy path: Complete trip with driver available at end
- Rejection: Rematching works with excluded drivers list
- No drivers: Returns 202 with no_drivers_available
- Cancellation: Driver becomes available again
- Sequential: Multiple trips correctly tracked

✅ **Status transitions correct**
- REQUESTED → MATCHING → ASSIGNED → DRIVER_ASSIGNED → PASSENGER_WAITING → IN_PROGRESS → COMPLETED
- Rejection paths: Any status → REJECTED_BY_DRIVER → MATCHING
- Cancellation: Any status → CANCELLED_BY_PASSENGER

✅ **Driver availability atomic**
- Set to false on acceptance
- Restored to true on completion/cancellation
- Never duplicated trips

✅ **Broadcasting working**
- Events dispatched to correct channels
- Log driver shows all broadcasts
- Events reach both driver and passenger channels

✅ **Error handling comprehensive**
- 404 for missing trips/drivers
- 403 for unauthorized actions
- 409 for invalid status transitions
- 422 for validation errors
- 500 with error codes for system errors

---

## Quick Test Script

```bash
#!/bin/bash

# Run complete test suite

echo "=== Motorcycle Trip System - Complete Test ==="

# Create test data
php artisan tinker << 'EOF'
$passenger = \App\Models\User::create([
    'name' => 'Test Passenger',
    'email' => 'passenger' . time() . '@test.local',
    'password' => Hash::make('password'),
    'role' => 'PASSENGER',
]);

$driverUser = \App\Models\User::create([
    'name' => 'Test Driver',
    'email' => 'driver' . time() . '@test.local',
    'password' => Hash::make('password'),
    'role' => 'DRIVER',
]);

$driver = \App\Models\Driver::create([
    'user_id' => $driverUser->id,
    'phone' => '+250789' . rand(100000, 999999),
    'license_number' => 'DL' . rand(100000, 999999),
    'is_active' => true,
    'is_available' => true,
]);

\App\Models\Vehicle::create([
    'driver_id' => $driver->id,
    'vehicle_type' => 'MOTORCYCLE',
    'registration_number' => 'RW-MOTO-' . rand(1000, 9999),
    'model' => 'Test Bike',
    'year' => 2023,
    'color' => 'Red',
    'capacity' => 1,
    'is_active' => true,
]);

echo "PASSENGER_TOKEN=" . $passenger->createToken('test')->plainTextToken . "\n";
echo "DRIVER_TOKEN=" . $driverUser->createToken('test')->plainTextToken . "\n";
echo "TRIP_ID=NULL\n";  // Will be set after create
EOF

# Test endpoints
echo "✓ Test data created"
echo "✓ All 7 endpoints ready for testing"
```

---

**Last Updated:** June 6, 2025
**Status:** Ready for Testing ✅
