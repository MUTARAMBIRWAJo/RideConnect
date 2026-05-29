# Flutter RideConnect Integration Guide

## Complete Trip Flow: Driver Matching → Trip Creation → Driver Acceptance

---

## **PHASE 1: Driver Matching (Already Implemented ✅)**

### Endpoint
```
POST /api/driver-matching
```

### Request
```dart
{
  "transport_type": "moto",
  "pickup_lat": -1.9399,
  "pickup_lng": 29.7733,
  "dropoff_lat": -1.9500,
  "dropoff_lng": 29.7800,
  "limit": 10
}
```

### Response (Success)
```dart
{
  "success": true,
  "data": {
    "transport_type": "motor_vehicle",
    "matching_session_id": "550e8400-e29b-41d4-a716-446655440000",  // SAVE THIS!
    "expires_at": "2026-05-28T20:15:30Z",  // Valid for 20 seconds
    "drivers": [
      {
        "driver_id": 364,
        "driver_name": "Jean Claude Moto",
        "rating": 4.5,
        "distance_km": 0.0,
        "estimated_arrival_minutes": 1,
        "estimated_fare": 900,
        "current_location": {
          "latitude": -1.9399,
          "longitude": 29.7733
        },
        "vehicle": {
          "vehicle_type": "motorcycle",
          "plate_number": "RAC-MOTO-001",
          "color": "Red"
        }
      }
    ]
  }
}
```

**CRITICAL**: Save the `matching_session_id` and `expires_at` timestamp!

---

## **PHASE 2: Request Trip with Selected Driver ✨ (Next Step)**

### Endpoint
```
POST /api/mobile/trips/request
```

### Request (After User Selects Driver)
```dart
{
  "selected_driver_id": 364,                    // Driver ID from matching response
  "matching_session_id": "550e8400-e29b-41d4-a716-446655440000",  // From Phase 1
  "transport_type": "motor_vehicle",            // Optional but recommended
  "pickup_location": "Kigali City Center",
  "pickup_lat": -1.9399,
  "pickup_lng": 29.7733,
  "pickup_place_name": "Pickup Location",       // Optional
  "dropoff_location": "Kigali Business District",
  "dropoff_lat": -1.9500,
  "dropoff_lng": 29.7800,
  "dropoff_place_name": "Dropoff Location",    // Optional
  "fare": 900                                   // From driver matching or calculate
}
```

### Response (Success)
```dart
{
  "status": "success",
  "data": {
    "id": 12345,                    // Trip ID - save for tracking
    "trip_state": "PENDING",        // Waiting for driver to accept
    "driver_id": 364,
    "driver_action_required": true  // Driver must accept/decline
  }
}
```

### Response (Error Cases)
```dart
// Driver no longer available
{
  "status": "error",
  "message": "Selected driver is no longer available",
  "code": 409
}

// Validation failed
{
  "status": "error",
  "message": "Pickup and dropoff locations are required",
  "errors": { "pickup_lat": ["The pickup lat field is required"] }
}
```

---

## **PHASE 3: Monitor Trip Status (Poll or WebSocket)**

### Endpoint
```
GET /api/mobile/trips/current
```

### Response While Waiting for Driver
```dart
{
  "status": "success",
  "data": {
    "trip_id": 12345,
    "trip_state": "PENDING",        // Not yet accepted by driver
    "driver": {
      "id": 364,
      "name": "Jean Claude Moto"
    },
    "vehicle": {
      "make": "Generic",
      "model": "Moto",
      "color": "Red"
    },
    "driver_location": {
      "latitude": -1.9399,
      "longitude": 29.7733
    },
    "eta": 1,                        // Minutes
    "fare": 900
  }
}
```

### Possible Trip States
- **PENDING**: Waiting for driver to accept (timeout in 60-90 seconds)
- **ACCEPTED**: Driver accepted, heading to pickup
- **STARTED**: Driver arrived and trip is in progress
- **COMPLETED**: Trip finished

---

## **PHASE 4: Track Driver Location (Real-time)**

### Endpoint
```
GET /api/mobile/trips/{trip_id}/track
```

### Response
```dart
{
  "status": "success",
  "data": {
    "driver_location": {
      "latitude": -1.9399,
      "longitude": 29.7733
    },
    "route_path": [],    // Placeholder (implement maps integration)
    "eta": 5             // Updated ETA in minutes
  }
}
```

---

## **Complete Flutter Implementation Example**

### Step 1: Match Drivers
```dart
Future<DriverMatchingResponse> matchDrivers(
  double pickupLat,
  double pickupLng,
  double dropoffLat,
  double dropoffLng,
) async {
  final response = await apiClient.post(
    '/api/driver-matching',
    data: {
      'transport_type': 'moto',
      'pickup_lat': pickupLat,
      'pickup_lng': pickupLng,
      'dropoff_lat': dropoffLat,
      'dropoff_lng': dropoffLng,
    },
  );

  if (response['success']) {
    return DriverMatchingResponse.fromJson(response['data']);
  }
  throw Exception(response['message']);
}
```

### Step 2: Save Session & Display Drivers
```dart
// Save these for the next step
final matchingSessionId = response.matchingSessionId;
final expiresAt = response.expiresAt;
final drivers = response.drivers; // Display in UI

// Show driver list with: name, rating, ETA, fare, photo
```

### Step 3: User Selects Driver → Request Trip
```dart
Future<TripResponse> requestTrip(
  DriverMatchingResponse matchingResult,
  Driver selectedDriver,
  String pickupLocation,
  double pickupLat,
  double pickupLng,
  String dropoffLocation,
  double dropoffLat,
  double dropoffLng,
) async {
  final response = await apiClient.post(
    '/api/mobile/trips/request',
    data: {
      'selected_driver_id': selectedDriver.driverId,
      'matching_session_id': matchingResult.matchingSessionId,
      'transport_type': 'motor_vehicle',
      'pickup_location': pickupLocation,
      'pickup_lat': pickupLat,
      'pickup_lng': pickupLng,
      'dropoff_location': dropoffLocation,
      'dropoff_lat': dropoffLat,
      'dropoff_lng': dropoffLng,
      'fare': selectedDriver.estimatedFare,
    },
    headers: {
      'X-Idempotency-Key': generateUUID(), // For idempotency
    },
  );

  if (response['status'] == 'success') {
    return TripResponse.fromJson(response['data']);
  }
  throw Exception(response['message']);
}
```

### Step 4: Poll for Trip Status
```dart
Timer? _statusPoller;

void startPollingTripStatus(int tripId) {
  _statusPoller = Timer.periodic(Duration(seconds: 2), (_) async {
    final response = await apiClient.get('/api/mobile/trips/current');
    
    if (response['data'] == null) {
      // No active trip
      return;
    }

    final tripState = response['data']['trip_state'];

    if (tripState == 'ACCEPTED') {
      // Driver accepted! Show driver location & ETA
      showDriverAcceptedScreen(response['data']);
    } else if (tripState == 'STARTED') {
      // Trip in progress
      showTripStartedScreen(response['data']);
    } else if (tripState == 'COMPLETED') {
      // Trip finished
      stopPolling();
      showTripCompletedScreen(response['data']);
    } else if (tripState == 'PENDING') {
      // Still waiting - check if timeout exceeded
      checkPendingTimeout();
    }
  });
}

void stopPolling() {
  _statusPoller?.cancel();
}
```

---

## **Error Handling & Recovery**

### Driver Not Available Anymore
```dart
if (response['code'] == 409) {
  // Go back to driver matching
  // matching_session_id has expired or driver went offline
  showSnackbar('Driver is no longer available. Searching again...');
  await matchDrivers(...); // Retry matching
}
```

### Pending Trip Timeout (No driver acceptance)
```dart
// If trip stays in PENDING for > 90 seconds, show:
showSnackbar('No drivers available right now');
showRetryButton(() => matchDrivers(...));
```

### Network Error During Request
```dart
try {
  final trip = await requestTrip(...);
} catch (e) {
  // Use idempotency key to retry without creating duplicate trip
  // Send same X-Idempotency-Key header
  final trip = await requestTrip(...);
}
```

---

## **Testing with Test Drivers**

The backend now has 5 test drivers available around Kigali:

| Driver ID | Name | Type | Location |
|-----------|------|------|----------|
| 364 | Jean Claude Moto | Motorcycle | -1.9399, 29.7733 |
| 365 | Patrick Express | Motorcycle | -1.9379, 29.7753 |
| 366 | Sophie Rider | Motorcycle | -1.9429, 29.7743 |
| 367 | Michel Transporteur | Sedan | -1.9389, 29.7713 |
| 368 | Therese Voiture | SUV | -1.9419, 29.7723 |

**Test Request:**
```dart
// Should find 3 moto drivers
await matchDrivers(
  pickupLat: -1.9399,
  pickupLng: 29.7733,
  dropoffLat: -1.9500,
  dropoffLng: 29.7800,
);
```

---

## **Important Notes**

1. **Plus Code Conversion**: Convert `23R7+46G` to `-1.9399, 29.7733` before API calls
2. **Session Expiry**: Driver matching results expire after 20 seconds
3. **Idempotency**: Always include `X-Idempotency-Key` header to prevent duplicate trips
4. **Polling Frequency**: Poll every 2-3 seconds (adjust based on server load)
5. **Timeout Handling**: If trip stays PENDING > 90s, consider it failed

---

## **API Response Codes**

| Code | Meaning | Action |
|------|---------|--------|
| 200/201 | Success | Proceed |
| 400 | Bad request | Check parameters |
| 422 | Validation error | Show error to user, fix input |
| 409 | Conflict (driver unavailable) | Show retry option |
| 403 | Unauthorized/Account not approved | Show account approval screen |

---

## **Summary: Complete Flow**

```
Flutter App                              Backend
    |                                        |
    |-- POST /api/driver-matching -------> Server
    |                                        |
    |<-- List of drivers with session_id -- |
    |                                        |
    | (User selects driver)                  |
    |                                        |
    |-- POST /api/mobile/trips/request ---> Server
    |   (with session_id & driver_id)        |
    |                                        |
    |<-- Trip created (PENDING state) ------- |
    |                                        |
    | Poll every 2s                          |
    |-- GET /api/mobile/trips/current -----> |
    |                                        |
    |<-- Trip state: PENDING/ACCEPTED/STARTED |
    |                                        |
    | When ACCEPTED:                         |
    |-- GET /api/mobile/trips/{id}/track --> |
    |                                        |
    |<-- Driver real-time location ---------- |
    |                                        |
```

---

**Backend Status**: ✅ Ready | **Test Drivers**: ✅ 5 Available | **Endpoints**: ✅ Tested
