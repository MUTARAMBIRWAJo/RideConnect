# Flutter Mobile App - API Quick Reference & Endpoint Summary

**RideConnect Platform - Mobile APIs Cheat Sheet**  
**Version:** 1.0  
**Last Updated:** May 2026

---

## 📋 Quick Navigation

- [Authentication APIs](#-authentication-apis)
- [Passenger APIs](#-passenger-apis)
- [Driver APIs](#-driver-apis)
- [Real-Time Tracking](#-real-time-tracking)
- [API Status Codes](#-api-status-codes)

---

## 🔐 Authentication APIs

| # | Method | Endpoint | Purpose | Auth |
|---|--------|----------|---------|------|
| 1 | POST | `/auth/register` | Register new passenger | ❌ |
| 2 | POST | `/auth/register/driver` | Register new driver | ❌ |
| 3 | POST | `/auth/login` | User login | ❌ |
| 4 | POST | `/auth/mobile/login` | Mobile app login | ❌ |
| 5 | GET | `/auth/profile` | Get user profile | ✅ |
| 6 | PUT | `/auth/profile` | Update user profile | ✅ |
| 7 | GET | `/auth/token/validate` | Validate access token | ✅ |
| 8 | POST | `/auth/logout` | Logout user | ✅ |
| 9 | POST | `/devices/push-token` | Register device for notifications | ✅ |
| 10 | DELETE | `/devices/push-token/{token}` | Unregister device | ✅ |

---

## 👥 Passenger APIs

### 🚗 Trip/Ride Management

| # | Method | Endpoint | Purpose | Auth |
|---|--------|----------|---------|------|
| 11 | GET | `/mobile/rides` | Get available rides | ✅ |
| 12 | POST | `/mobile/trips/request` | Request a new trip | ✅ |
| 13 | GET | `/mobile/trips/current` | Get current active trip | ✅ |
| 14 | GET | `/mobile/trips/{id}/track` | Track driver location | ✅ |
| 15 | PUT | `/mobile/trips/{id}/complete` | Complete trip | ✅ |
| 16 | PUT | `/mobile/trips/{id}/cancel` | Cancel trip | ✅ |
| 17 | GET | `/passenger/rides/history` | Get ride history | ✅ |
| 18 | GET | `/passenger/rides/available` | Available rides for booking | ✅ |
| 19 | GET | `/passenger/trips` | Get passenger's trips | ✅ |

### 💳 Booking Management

| # | Method | Endpoint | Purpose | Auth |
|---|--------|----------|---------|------|
| 20 | POST | `/mobile/bookings` | Create booking | ✅ |
| 21 | GET | `/passenger/bookings` | Get all bookings | ✅ |
| 22 | GET | `/passenger/bookings/my` | Get my bookings | ✅ |
| 23 | GET | `/passenger/bookings/{id}` | Get booking details | ✅ |
| 24 | PUT | `/passenger/bookings/{id}` | Update booking | ✅ |
| 25 | PUT | `/passenger/bookings/{id}/cancel` | Cancel booking | ✅ |

### 💰 Payment & Finance

| # | Method | Endpoint | Purpose | Auth |
|---|--------|----------|---------|------|
| 26 | POST | `/passenger/payments` | Create payment | ✅ |
| 27 | GET | `/passenger/payments/history` | Payment history | ✅ |
| 28 | GET | `/finance/summary` | Financial summary | ✅ |
| 29 | GET | `/finance/transactions` | Transaction history | ✅ |

### 📊 Statistics & History

| # | Method | Endpoint | Purpose | Auth |
|---|--------|----------|---------|------|
| 30 | GET | `/passenger/stats` | Get passenger statistics | ✅ |
| 31 | GET | `/passenger/drivers/online` | Get online drivers nearby | ✅ |
| 32 | GET | `/passenger/public-transport/corridors` | Get corridors | ✅ |
| 33 | GET | `/passenger/public-transport/routes` | Get routes | ✅ |

---

## 🚗 Driver APIs

### 📍 Location & Status

| # | Method | Endpoint | Purpose | Auth |
|---|--------|----------|---------|------|
| 34 | POST | `/mobile/drivers/status` | Update online/offline status | ✅ |
| 35 | POST | `/mobile/drivers/location` | Send trip location | ✅ |
| 36 | POST | `/mobile/drivers/live-location` | Send live location (realtime) | ✅ |
| 37 | POST | `/driver/location` | Update driver location | ✅ |
| 38 | GET | `/driver/{id}/location` | Get driver location | ✅ |
| 39 | GET | `/drivers/nearby` | Get nearby drivers | ✅ |

### 📋 Trip Management

| # | Method | Endpoint | Purpose | Auth |
|---|--------|----------|---------|------|
| 40 | GET | `/mobile/drivers/trips` | Get available trips | ✅ |
| 41 | POST | `/mobile/drivers/trips/{id}/accept` | Accept trip | ✅ |
| 42 | PUT | `/mobile/drivers/trips/{id}/start` | Start trip | ✅ |
| 43 | PUT | `/mobile/drivers/trips/{id}/complete` | Complete trip | ✅ |
| 44 | PUT | `/mobile/drivers/trips/{id}/cancel` | Cancel trip | ✅ |
| 45 | GET | `/driver/trips` | Get driver's trips | ✅ |
| 46 | GET | `/driver/trip-requests` | Get trip requests | ✅ |

### 👤 Profile & Earnings

| # | Method | Endpoint | Purpose | Auth |
|---|--------|----------|---------|------|
| 47 | GET | `/driver/profile` | Get driver profile | ✅ |
| 48 | PUT | `/driver/profile` | Update driver profile | ✅ |
| 49 | GET | `/driver/earnings` | Get earnings | ✅ |
| 50 | GET | `/driver/earnings/monthly` | Get monthly earnings | ✅ |
| 51 | GET | `/driver/stats` | Get driver statistics | ✅ |
| 52 | GET | `/driver/bookings` | Get driver's bookings | ✅ |

### 📄 Documents & Verification

| # | Method | Endpoint | Purpose | Auth |
|---|--------|----------|---------|------|
| 53 | POST | `/driver/documents` | Upload documents | ✅ |
| 54 | GET | `/driver/documents` | Get documents | ✅ |

---

## 🌐 Real-Time Tracking

| # | Method | Endpoint | Purpose | Auth |
|---|--------|----------|---------|------|
| 55 | GET | `/mobile/tracking/driver/{driverId}` | Get driver location | ✅ |
| 56 | GET | `/mobile/tracking/trip/{tripId}` | Get trip driver location | ✅ |
| 57 | GET | `/mobile/tracking/nearby` | Get nearby online drivers | ✅ |
| 58 | WS | `driver:{id}` | WebSocket location stream | ✅ |

---

## 📱 Endpoint Organization by Use Case

### **Passenger App Flow**

```
1. Register/Login
   ├── POST /auth/register
   ├── POST /auth/login
   └── POST /devices/push-token (register for notifications)

2. Browse & Request
   ├── GET /mobile/rides (available rides)
   ├── GET /passenger/drivers/online (nearby drivers)
   ├── POST /mobile/trips/request (request trip)
   └── POST /mobile/bookings (book scheduled ride)

3. Track & Complete
   ├── GET /mobile/trips/current (active trip)
   ├── GET /mobile/trips/{id}/track (real-time driver location)
   ├── WebSocket driver:{id}.driver.location.updated (live updates)
   ├── PUT /mobile/trips/{id}/complete (finish trip)
   └── POST /passenger/payments (pay for trip)

4. Review & History
   ├── GET /passenger/stats (profile stats)
   ├── GET /passenger/rides/history (past rides)
   └── GET /passenger/payments/history (past payments)
```

### **Driver App Flow**

```
1. Register/Login & Setup
   ├── POST /auth/register/driver (register)
   ├── POST /auth/mobile/login (login)
   ├── POST /driver/documents (upload documents)
   └── GET /driver/profile (view profile)

2. Go Online
   ├── POST /mobile/drivers/status (go online)
   └── Timer → POST /mobile/drivers/live-location every 5-10 sec

3. Accept & Manage Trips
   ├── Timer → GET /mobile/drivers/trips every 5 sec
   ├── POST /mobile/drivers/trips/{id}/accept (accept)
   ├── PUT /mobile/drivers/trips/{id}/start (start)
   ├── Timer → POST /mobile/drivers/location (send trip location)
   └── PUT /mobile/drivers/trips/{id}/complete (finish)

4. Earnings & Offline
   ├── GET /driver/earnings (today's earnings)
   ├── GET /driver/stats (performance stats)
   └── POST /mobile/drivers/status (go offline)
```

---

## ⚙️ Core Endpoint Parameters

### **Location-Based Endpoints**

Endpoints requiring location query parameters:

```
latitude      (float, required)   : GPS latitude -90 to 90
longitude     (float, required)   : GPS longitude -180 to 180
radius_km     (float, optional)   : Search radius in kilometers (default: 5, max: 50)
```

Example:
```
GET /passenger/drivers/online?latitude=-1.9536&longitude=30.0605&radius_km=10
```

### **Trip Request Payload**

```json
{
  "pickup_location": "string",
  "pickup_lat": "float",
  "pickup_lng": "float",
  "dropoff_location": "string",
  "dropoff_lat": "float",
  "dropoff_lng": "float",
  "number_of_passengers": "int",
  "ride_type": "private|public",
  "preferred_vehicle_type": "sedan|suv|premium"
}
```

### **Driver Live Location Payload**

```json
{
  "lat": "float (required)",
  "lng": "float (required)",
  "speed_kmh": "float (optional)",
  "heading": "float (optional, 0-360)",
  "accuracy": "float (optional, meters)",
  "is_online": "bool (optional)"
}
```

---

## 🔄 API Response Format

### **Success Response (2xx)**

```json
{
  "status": "success",
  "data": {
    // Response data
  },
  "message": "Operation completed successfully",
  "code": 200,
  "timestamp": "2026-05-05T10:30:00Z"
}
```

### **Error Response (4xx, 5xx)**

```json
{
  "status": "error",
  "message": "Descriptive error message",
  "code": 400,
  "errors": {
    "field_name": ["Error message 1", "Error message 2"]
  },
  "timestamp": "2026-05-05T10:30:00Z"
}
```

---

## 📊 API Status Codes

| Code | Meaning | Common Causes |
|------|---------|---------------|
| 200 | OK - Success | Request completed successfully |
| 201 | Created | Resource created successfully |
| 400 | Bad Request | Invalid parameters, missing fields |
| 401 | Unauthorized | Missing/invalid/expired token |
| 403 | Forbidden | User lacks permission |
| 404 | Not Found | Resource doesn't exist |
| 422 | Validation Failed | Invalid input data |
| 429 | Too Many Requests | Rate limit exceeded |
| 500 | Server Error | Internal server error |
| 503 | Service Unavailable | Server maintenance |

---

## 🔑 Authentication Pattern

### **Token-Based Authentication**

```dart
// 1. Login and get token
final loginResponse = await dio.post(
  '/auth/login',
  data: {
    'email': 'user@example.com',
    'password': 'password123'
  }
);

final token = loginResponse.data['data']['access_token'];

// 2. Include token in all subsequent requests
dio.options.headers['Authorization'] = 'Bearer $token';

// 3. For all requests
final response = await dio.get('/passenger/profile');
// Authorization header automatically included
```

### **Token Expiration Handling**

```dart
// Check if token is valid
final validateResponse = await dio.get('/auth/token/validate');
if (!validateResponse.data['data']['valid']) {
  // Token expired, redirect to login
  navigateToLogin();
}
```

---

## 🔄 Real-Time Updates via WebSocket

### **Subscribe to Driver Location Updates**

```dart
import 'package:supabase_flutter/supabase_flutter.dart';

final supabase = Supabase.instance.client;

// For specific trip
final tripSubscription = supabase
    .channel('driver:$driverId')
    .onBroadcast(
        event: 'driver.location.updated',
        callback: (payload) {
            // Handle location update
            final latitude = payload['latitude'];
            final longitude = payload['longitude'];
            updateMapMarker(latitude, longitude);
        },
    )
    .subscribe();

// Subscribe to online status changes
final statusSubscription = supabase
    .channel('driver:$driverId')
    .onBroadcast(
        event: 'driver.status.changed',
        callback: (payload) {
            final isOnline = payload['is_online'];
            updateDriverStatus(isOnline);
        },
    )
    .subscribe();

// Unsubscribe when done
await supabase.removeChannel(tripSubscription);
await supabase.removeChannel(statusSubscription);
```

---

## 📝 Common Implementation Patterns

### **Pattern 1: Polling vs WebSocket**

```dart
// Polling (HTTP)
Timer.periodic(Duration(seconds: 2), (timer) {
  dio.get('/mobile/trips/$tripId/track').then((response) {
    updateUI(response.data['data']);
  });
});

// WebSocket (Recommended)
supabase
    .channel('driver:$driverId')
    .onBroadcast(event: 'driver.location.updated', callback: (payload) {
        updateUI(payload);
    })
    .subscribe();
```

### **Pattern 2: Continuous Location Tracking**

```dart
// Start tracking when driver goes online
void startLocationTracking() {
  locationUpdateTimer = Timer.periodic(Duration(seconds: 10), (_) async {
    try {
      final position = await determinePosition();
      await dio.post(
        '/mobile/drivers/live-location',
        data: {
          'lat': position.latitude,
          'lng': position.longitude,
          'speed_kmh': position.speed,
          'heading': position.heading,
          'accuracy': position.accuracy,
          'is_online': true,
        },
      );
    } catch (e) {
      handleError(e);
    }
  });
}

// Stop tracking when driver goes offline
void stopLocationTracking() {
  locationUpdateTimer?.cancel();
}
```

### **Pattern 3: Error Handling with Retry**

```dart
Future<void> makeAPICallWithRetry(Function apiCall, {int maxRetries = 3}) async {
  int retryCount = 0;
  while (retryCount < maxRetries) {
    try {
      return await apiCall();
    } catch (e) {
      retryCount++;
      if (retryCount >= maxRetries) {
        rethrow;
      }
      await Future.delayed(Duration(seconds: 2 * retryCount));
    }
  }
}

// Usage
await makeAPICallWithRetry(() => dio.post('/mobile/drivers/status', data: {...}));
```

---

## 🎯 Best Practices

✅ **DO:**
- Use WebSocket for real-time location updates
- Implement token refresh logic
- Add retry logic for network failures
- Cache user data locally
- Validate all input before sending
- Handle all error codes appropriately

❌ **DON'T:**
- Poll API every second (use WebSocket instead)
- Store sensitive data in SharedPreferences
- Make API calls on main thread
- Ignore token expiration
- Send location updates too frequently

---

## 📞 Support & Documentation

- **Full Documentation:** See `FLUTTER_MOBILE_APP_COMPLETE_API_GUIDE.md`
- **Real-Time Tracking:** See `REALTIME_DRIVER_LOCATION_TRACKING.md`
- **Authentication:** See `MOBILE_AUTH_API.md`
- **API Support:** api-support@rideconnect.rw

---

**Total APIs:** 58 endpoints  
**Authentication Methods:** JWT Bearer Token  
**Response Format:** JSON  
**Real-Time:** Supabase WebSocket  
**Rate Limit:** 1000 requests/hour per IP  

**Last Updated:** May 2026
