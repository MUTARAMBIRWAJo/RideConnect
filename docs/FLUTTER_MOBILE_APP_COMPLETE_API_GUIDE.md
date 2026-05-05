# Flutter Mobile App - Complete API Reference & Guide

**RideConnect Platform**  
**Version:** 1.0  
**Last Updated:** May 2026  
**Supported Versions:** Flutter 3.0+, Dart 3.0+

---

## 📑 Table of Contents

1. [API Base URLs](#api-base-urls)
2. [Authentication APIs](#authentication-apis)
3. [Passenger APIs](#passenger-apis)
4. [Driver APIs](#driver-apis)
5. [Real-Time Tracking](#real-time-tracking)
6. [Common Use Cases](#common-use-cases)
7. [Error Handling](#error-handling)
8. [Request/Response Examples](#requestresponse-examples)

---

## 🌐 API Base URLs

```dart
// Production
const String API_BASE_URL = 'https://api.rideconnect.rw/api/v1';

// Staging
const String STAGING_URL = 'https://staging-api.rideconnect.rw/api/v1';

// Local Development
const String DEV_URL = 'http://localhost:8000/api/v1';
```

**All endpoints require:** `Authorization: Bearer {access_token}` (except auth endpoints)

---

## 🔐 Authentication APIs

### 1. **User Registration (Passenger)**
- **Endpoint:** `POST /auth/register`
- **Purpose:** Register a new passenger account
- **Public:** Yes (No auth required)

```json
Request:
{
  "name": "Jane Doe",
  "email": "jane@example.com",
  "phone": "+250788123456",
  "password": "SecurePass123!",
  "password_confirmation": "SecurePass123!",
  "role": "passenger"
}

Response: 
{
  "status": "success",
  "data": {
    "id": 123,
    "name": "Jane Doe",
    "email": "jane@example.com",
    "phone": "+250788123456",
    "role": "passenger",
    "access_token": "eyJhbGciOiJIUzI1NiIs...",
    "token_type": "Bearer",
    "expires_in": 604800
  }
}
```

### 2. **Driver Registration**
- **Endpoint:** `POST /auth/register/driver`
- **Purpose:** Register a new driver account with vehicle info
- **Public:** Yes

```json
Request:
{
  "name": "John Driver",
  "email": "john@example.com",
  "phone": "+250788123456",
  "password": "SecurePass123!",
  "vehicle_type": "sedan",
  "vehicle_registration": "RAJ-123-XYZ"
}

Response:
{
  "status": "success",
  "data": {
    "id": 456,
    "name": "John Driver",
    "role": "driver",
    "is_approved": false,
    "access_token": "eyJhbGciOiJIUzI1NiIs...",
    "expires_in": 604800
  }
}
```

### 3. **Login**
- **Endpoint:** `POST /auth/login` or `POST /auth/mobile/login`
- **Purpose:** Authenticate user and get access token
- **Public:** Yes

```json
Request:
{
  "email": "jane@example.com",
  "password": "SecurePass123!"
}

Response:
{
  "status": "success",
  "data": {
    "id": 123,
    "name": "Jane Doe",
    "email": "jane@example.com",
    "phone": "+250788123456",
    "role": "passenger",
    "access_token": "eyJhbGciOiJIUzI1NiIs...",
    "token_type": "Bearer",
    "expires_in": 604800
  }
}
```

### 4. **Validate Token**
- **Endpoint:** `GET /auth/token/validate`
- **Purpose:** Verify if access token is still valid
- **Auth Required:** Yes

```json
Response:
{
  "status": "success",
  "data": {
    "valid": true,
    "user_id": 123,
    "expires_at": "2026-05-13T10:30:00Z"
  }
}
```

### 5. **Get User Profile**
- **Endpoint:** `GET /auth/profile`
- **Purpose:** Fetch current user's profile details
- **Auth Required:** Yes

```json
Response:
{
  "status": "success",
  "data": {
    "id": 123,
    "name": "Jane Doe",
    "email": "jane@example.com",
    "phone": "+250788123456",
    "role": "passenger",
    "avatar_url": "https://...",
    "rating": 4.8,
    "total_trips": 45,
    "created_at": "2026-01-15T10:00:00Z"
  }
}
```

### 6. **Update User Profile**
- **Endpoint:** `PUT /auth/profile`
- **Purpose:** Update user profile information
- **Auth Required:** Yes

```json
Request:
{
  "name": "Jane Smith",
  "phone": "+250788999999",
  "avatar_url": "data:image/jpeg;base64,..."
}

Response:
{
  "status": "success",
  "data": { /* updated profile */ }
}
```

### 7. **Register Device Token (Push Notifications)**
- **Endpoint:** `POST /devices/push-token`
- **Purpose:** Register device for push notifications
- **Auth Required:** Yes

```json
Request:
{
  "token": "ExponentPushToken[xxxxxxxxxxxxxx]",
  "platform": "ios|android",
  "device_id": "unique-device-identifier"
}

Response:
{
  "status": "success",
  "message": "Device token registered"
}
```

---

## 👥 Passenger APIs

### 📱 Ride/Trip Management

#### 1. **Get Available Rides**
- **Endpoint:** `GET /mobile/rides`
- **Purpose:** Fetch list of available rides for booking
- **Query Params:** 
  - `latitude` (required)
  - `longitude` (required)
  - `radius_km` (optional, default: 10)

```json
Response:
{
  "status": "success",
  "data": [
    {
      "id": 1,
      "name": "Kigali City Center Loop",
      "type": "public_transport",
      "pickup_location": "Remera",
      "dropoff_location": "Kimironko",
      "base_fare": 1500,
      "distance_km": 5.2,
      "estimated_duration_minutes": 15,
      "available_seats": 4,
      "driver": {
        "id": 123,
        "name": "John Driver",
        "rating": 4.9,
        "avatar": "https://..."
      }
    }
  ]
}
```

#### 2. **Request a Trip**
- **Endpoint:** `POST /mobile/trips/request`
- **Purpose:** Request a new trip (for private transport)
- **Auth Required:** Yes

```json
Request:
{
  "pickup_location": "Remera Station",
  "dropoff_location": "Kimironko Market",
  "pickup_lat": -1.9536,
  "pickup_lng": 30.0605,
  "dropoff_lat": -1.9365,
  "dropoff_lng": 30.1200,
  "ride_type": "private",
  "number_of_passengers": 2,
  "preferred_vehicle_type": "sedan|suv|premium"
}

Response:
{
  "status": "success",
  "data": {
    "id": 789,
    "status": "matching",
    "estimated_fare": 3500,
    "estimated_wait_time_seconds": 120,
    "trip_reference": "TC-20260505-001"
  }
}
```

#### 3. **Get Current Trip**
- **Endpoint:** `GET /mobile/trips/current`
- **Purpose:** Get details of currently active trip
- **Auth Required:** Yes

```json
Response:
{
  "status": "success",
  "data": {
    "id": 789,
    "status": "started",
    "driver": {
      "id": 123,
      "name": "John Driver",
      "phone": "+250788123456",
      "vehicle": "Toyota Prius RAJ-123",
      "rating": 4.9,
      "latitude": -1.9536,
      "longitude": 30.0605
    },
    "pickup_location": "Remera Station",
    "dropoff_location": "Kimironko Market",
    "pickup_lat": -1.9536,
    "pickup_lng": 30.0605,
    "dropoff_lat": -1.9365,
    "dropoff_lng": 30.1200,
    "estimated_arrival_seconds": 480,
    "current_fare": 2100,
    "started_at": "2026-05-05T10:15:00Z"
  }
}
```

#### 4. **Track Trip Driver in Real-Time**
- **Endpoint:** `GET /mobile/trips/{tripId}/track`
- **Purpose:** Get real-time driver location during active trip
- **Auth Required:** Yes

```json
Response:
{
  "status": "success",
  "data": {
    "trip_id": 789,
    "driver_id": 123,
    "latitude": -1.9536,
    "longitude": 30.0605,
    "speed_kmh": 45.5,
    "heading": 180.0,
    "accuracy": 5.2,
    "is_online": true,
    "updated_at": "2026-05-05T10:15:30Z"
  }
}
```

#### 5. **Complete Trip**
- **Endpoint:** `PUT /mobile/trips/{tripId}/complete`
- **Purpose:** Mark trip as complete (passenger action)
- **Auth Required:** Yes

```json
Request:
{
  "rating": 5,
  "review": "Great driver, clean car!",
  "payment_method": "card|cash|mobile_money"
}

Response:
{
  "status": "success",
  "data": {
    "trip_id": 789,
    "total_fare": 3200,
    "rating_submitted": true,
    "completed_at": "2026-05-05T10:45:00Z"
  }
}
```

#### 6. **Cancel Trip**
- **Endpoint:** `PUT /mobile/trips/{tripId}/cancel`
- **Purpose:** Cancel an active or pending trip
- **Auth Required:** Yes

```json
Request:
{
  "reason": "Driver is taking too long",
  "cancellation_type": "passenger_requested"
}

Response:
{
  "status": "success",
  "message": "Trip cancelled successfully",
  "data": {
    "cancellation_fee": 500,
    "refund_amount": 0
  }
}
```

### 💳 Booking Management

#### 7. **Create Booking**
- **Endpoint:** `POST /mobile/bookings`
- **Purpose:** Book a ride on a scheduled public transport route
- **Auth Required:** Yes

```json
Request:
{
  "ride_id": 1,
  "number_of_seats": 2,
  "payment_method": "card|mobile_money",
  "passenger_names": ["Jane Doe", "John Doe"]
}

Response:
{
  "status": "success",
  "data": {
    "booking_id": 456,
    "ride_id": 1,
    "status": "confirmed",
    "seats": [12, 13],
    "total_fare": 3000,
    "booking_reference": "BK-20260505-001",
    "booking_date": "2026-05-05",
    "departure_time": "2026-05-05T14:00:00Z"
  }
}
```

### 💰 Payment & Finance

#### 8. **Create Payment**
- **Endpoint:** `POST /passenger/payments`
- **Purpose:** Initiate payment for trip or booking
- **Auth Required:** Yes

```json
Request:
{
  "amount": 3500,
  "currency": "RWF",
  "payment_method": "card|mobile_money|wallet",
  "trip_id": 789,
  "description": "Payment for trip TC-20260505-001"
}

Response:
{
  "status": "success",
  "data": {
    "payment_id": 101,
    "transaction_id": "TXN-20260505-0001",
    "status": "pending",
    "amount": 3500,
    "payment_url": "https://payment.gateway.com/...",
    "expires_at": "2026-05-05T11:00:00Z"
  }
}
```

#### 9. **Payment History**
- **Endpoint:** `GET /passenger/payments/history`
- **Purpose:** View payment transaction history
- **Auth Required:** Yes

```json
Response:
{
  "status": "success",
  "data": [
    {
      "id": 101,
      "transaction_id": "TXN-20260505-0001",
      "amount": 3500,
      "currency": "RWF",
      "status": "completed",
      "payment_method": "card",
      "trip_reference": "TC-20260505-001",
      "created_at": "2026-05-05T10:45:00Z"
    }
  ]
}
```

### 📊 Passenger Stats & History

#### 10. **Get Passenger Stats**
- **Endpoint:** `GET /mobile/rides` or `GET /passenger/stats`
- **Purpose:** Get passenger profile statistics
- **Auth Required:** Yes

```json
Response:
{
  "status": "success",
  "data": {
    "total_trips": 45,
    "average_rating": 4.8,
    "total_spent": 125000,
    "favorite_destinations": ["Kimironko", "Nyarugenge"],
    "member_since": "2026-01-15",
    "saved_addresses": 3,
    "preferred_payment_method": "card"
  }
}
```

#### 11. **Get Ride History**
- **Endpoint:** `GET /passenger/rides/history` or `GET /mobile/rides`
- **Purpose:** View past ride records
- **Auth Required:** Yes
- **Query Params:**
  - `limit` (default: 10)
  - `offset` (default: 0)
  - `status` (completed, cancelled, etc.)

```json
Response:
{
  "status": "success",
  "data": [
    {
      "id": 789,
      "type": "private_transport",
      "driver_name": "John Driver",
      "pickup": "Remera Station",
      "dropoff": "Kimironko Market",
      "distance_km": 8.5,
      "duration_minutes": 25,
      "fare": 3200,
      "rating": 5,
      "completed_at": "2026-05-05T10:45:00Z"
    }
  ]
}
```

### 🚗 Nearby Drivers

#### 12. **Get Online Drivers**
- **Endpoint:** `GET /passenger/drivers/online`
- **Purpose:** Find available online drivers near location
- **Auth Required:** Yes
- **Query Params:**
  - `latitude` (required)
  - `longitude` (required)
  - `radius_km` (optional, default: 5)

```json
Response:
{
  "status": "success",
  "data": [
    {
      "id": 123,
      "name": "John Driver",
      "rating": 4.9,
      "vehicle": "Toyota Prius",
      "vehicle_plate": "RAJ-123",
      "distance_km": 2.3,
      "latitude": -1.9536,
      "longitude": 30.0605,
      "availability": "available"
    }
  ]
}
```

### 🗺️ Location Data

#### 13. **Public Transport Corridors**
- **Endpoint:** `GET /passenger/public-transport/corridors`
- **Purpose:** Get list of available public transport corridors
- **Auth Required:** Yes

```json
Response:
{
  "status": "success",
  "data": [
    {
      "id": 1,
      "name": "Kigali City Center to Nyarugenge",
      "from": "City Center",
      "to": "Nyarugenge",
      "distance_km": 12.5,
      "available_routes": 5,
      "typical_duration_minutes": 30
    }
  ]
}
```

#### 14. **Public Transport Routes**
- **Endpoint:** `GET /passenger/public-transport/routes`
- **Purpose:** Get specific routes within a corridor
- **Auth Required:** Yes
- **Query Params:**
  - `corridor_id` (optional)

```json
Response:
{
  "status": "success",
  "data": [
    {
      "id": 1,
      "name": "Kigali City Center Loop",
      "corridor": "Central Kigali",
      "pickup_points": ["Remera", "Kacyiru"],
      "dropoff_points": ["Kimironko", "Kanombe"],
      "distance_km": 15.3,
      "fare": 1500,
      "frequency_minutes": 20
    }
  ]
}
```

---

## 🚗 Driver APIs

### 📍 Location & Status

#### 1. **Update Driver Status**
- **Endpoint:** `POST /mobile/drivers/status`
- **Purpose:** Toggle driver online/offline status
- **Auth Required:** Yes

```json
Request:
{
  "status": "online|offline",
  "latitude": -1.9536,
  "longitude": 30.0605,
  "availability_status": "available|on_trip|break"
}

Response:
{
  "status": "success",
  "data": {
    "driver_id": 123,
    "is_online": true,
    "status": "available",
    "updated_at": "2026-05-05T10:30:00Z"
  }
}
```

#### 2. **Update Driver Location (Trip Location)**
- **Endpoint:** `POST /mobile/drivers/location`
- **Purpose:** Send location update during active trip
- **Auth Required:** Yes
- **Used For:** Trip location tracking for trip-specific updates

```json
Request:
{
  "trip_id": 789,
  "lat": -1.9536,
  "lng": 30.0605
}

Response:
{
  "status": "success",
  "data": {
    "trip_id": 789,
    "location_updated": true
  }
}
```

#### 3. **Update Driver Live Location (Realtime)**
- **Endpoint:** `POST /mobile/drivers/live-location`
- **Purpose:** Send real-time location with GPS metrics
- **Auth Required:** Yes
- **Used For:** Continuous location tracking while online

```json
Request:
{
  "lat": -1.9536,
  "lng": 30.0605,
  "speed_kmh": 45.5,
  "heading": 180.0,
  "accuracy": 5.2,
  "is_online": true
}

Response:
{
  "status": "success",
  "data": {
    "driver_id": 123,
    "latitude": -1.9536,
    "longitude": 30.0605,
    "speed_kmh": 45.5,
    "heading": 180.0,
    "accuracy": 5.2,
    "is_online": true,
    "updated_at": "2026-05-05T10:30:00Z"
  }
}
```

### 📋 Trip Management

#### 4. **Get Available Trips**
- **Endpoint:** `GET /mobile/drivers/trips`
- **Purpose:** Fetch available trips for driver to accept
- **Auth Required:** Yes
- **Query Params:**
  - `limit` (default: 10)
  - `status` (pending, available)

```json
Response:
{
  "status": "success",
  "data": [
    {
      "id": 789,
      "passenger_name": "Jane Doe",
      "pickup_location": "Remera Station",
      "pickup_lat": -1.9536,
      "pickup_lng": 30.0605,
      "dropoff_location": "Kimironko Market",
      "dropoff_lat": -1.9365,
      "dropoff_lng": 30.1200,
      "estimated_distance_km": 8.5,
      "estimated_fare": 3500,
      "passenger_rating": 4.8,
      "posted_at": "2026-05-05T10:15:00Z",
      "expires_at": "2026-05-05T10:22:00Z"
    }
  ]
}
```

#### 5. **Accept Trip**
- **Endpoint:** `POST /mobile/drivers/trips/{tripId}/accept`
- **Purpose:** Accept a trip offer
- **Auth Required:** Yes

```json
Request:
{
  "estimated_arrival_minutes": 5
}

Response:
{
  "status": "success",
  "data": {
    "trip_id": 789,
    "status": "accepted",
    "passenger": {
      "name": "Jane Doe",
      "phone": "+250788123456",
      "rating": 4.8,
      "avatar": "https://..."
    },
    "accepted_at": "2026-05-05T10:17:00Z"
  }
}
```

#### 6. **Start Trip**
- **Endpoint:** `PUT /mobile/drivers/trips/{tripId}/start`
- **Purpose:** Mark trip as started (driver picked up passenger)
- **Auth Required:** Yes

```json
Request:
{
  "latitude": -1.9536,
  "longitude": 30.0605
}

Response:
{
  "status": "success",
  "data": {
    "trip_id": 789,
    "status": "started",
    "started_at": "2026-05-05T10:20:00Z"
  }
}
```

#### 7. **Complete Trip**
- **Endpoint:** `PUT /mobile/drivers/trips/{tripId}/complete`
- **Purpose:** Mark trip as completed with final details
- **Auth Required:** Yes

```json
Request:
{
  "final_distance_km": 8.7,
  "final_fare": 3250,
  "payment_received": 3250,
  "payment_method": "cash|card",
  "notes": "Completed successfully"
}

Response:
{
  "status": "success",
  "data": {
    "trip_id": 789,
    "status": "completed",
    "earnings": 2925,
    "commission_paid": 325,
    "completed_at": "2026-05-05T10:45:00Z"
  }
}
```

#### 8. **Cancel Trip**
- **Endpoint:** `PUT /mobile/drivers/trips/{tripId}/cancel`
- **Purpose:** Cancel trip (before starting)
- **Auth Required:** Yes

```json
Request:
{
  "reason": "Passenger not responding",
  "cancellation_type": "driver_requested"
}

Response:
{
  "status": "success",
  "message": "Trip cancelled",
  "data": {
    "cancellation_fee": 1000,
    "refund_to_passenger": 3500
  }
}
```

### 👤 Driver Profile & Earnings

#### 9. **Get Driver Profile**
- **Endpoint:** `GET /driver/profile`
- **Purpose:** Fetch driver's profile information
- **Auth Required:** Yes

```json
Response:
{
  "status": "success",
  "data": {
    "id": 123,
    "name": "John Driver",
    "email": "john@example.com",
    "phone": "+250788123456",
    "rating": 4.9,
    "total_trips": 325,
    "total_earnings": 985000,
    "is_verified": true,
    "is_online": true,
    "vehicle": {
      "type": "sedan",
      "make": "Toyota",
      "model": "Prius",
      "year": 2022,
      "plate": "RAJ-123",
      "color": "White",
      "seats": 4
    },
    "documents": {
      "national_id": "verified",
      "driving_license": "verified",
      "vehicle_registration": "verified"
    }
  }
}
```

#### 10. **Update Driver Profile**
- **Endpoint:** `PUT /driver/profile`
- **Purpose:** Update driver profile details
- **Auth Required:** Yes

```json
Request:
{
  "name": "John Smith",
  "phone": "+250788999999",
  "avatar_url": "data:image/jpeg;base64,..."
}

Response:
{
  "status": "success",
  "data": { /* updated profile */ }
}
```

#### 11. **Get Driver Earnings**
- **Endpoint:** `GET /driver/earnings`
- **Purpose:** View total and daily earnings
- **Auth Required:** Yes
- **Query Params:**
  - `period` (day, week, month, all)

```json
Response:
{
  "status": "success",
  "data": {
    "total_earnings": 985000,
    "today_earnings": 42500,
    "this_week_earnings": 145000,
    "this_month_earnings": 285000,
    "pending_payout": 45000,
    "completed_trips_today": 8,
    "completed_trips_all_time": 325
  }
}
```

#### 12. **Get Monthly Earnings**
- **Endpoint:** `GET /driver/earnings/monthly`
- **Purpose:** View earnings by month
- **Auth Required:** Yes

```json
Response:
{
  "status": "success",
  "data": [
    {
      "month": "May 2026",
      "earnings": 285000,
      "trips": 78,
      "average_per_trip": 3654
    }
  ]
}
```

### 📋 Documents & Verification

#### 13. **Upload Documents**
- **Endpoint:** `POST /driver/documents`
- **Purpose:** Upload required driver documents
- **Auth Required:** Yes
- **Multipart:** Yes

```json
Request:
{
  "document_type": "national_id|driving_license|vehicle_registration",
  "file": <binary file>,
  "expiry_date": "2028-12-31"
}

Response:
{
  "status": "success",
  "data": {
    "document_id": 101,
    "document_type": "national_id",
    "status": "pending_verification",
    "uploaded_at": "2026-05-05T10:30:00Z"
  }
}
```

#### 14. **Get Documents**
- **Endpoint:** `GET /driver/documents`
- **Purpose:** View all uploaded documents
- **Auth Required:** Yes

```json
Response:
{
  "status": "success",
  "data": [
    {
      "id": 101,
      "document_type": "national_id",
      "status": "verified",
      "expiry_date": "2028-12-31",
      "uploaded_at": "2026-04-01T10:00:00Z",
      "verified_at": "2026-04-02T15:30:00Z"
    }
  ]
}
```

### 📊 Driver Statistics

#### 15. **Get Driver Stats**
- **Endpoint:** `GET /driver/stats`
- **Purpose:** Get driver performance statistics
- **Auth Required:** Yes

```json
Response:
{
  "status": "success",
  "data": {
    "total_trips": 325,
    "completed_trips": 320,
    "cancelled_trips": 5,
    "average_rating": 4.9,
    "five_star_trips": 310,
    "total_distance_km": 8450,
    "total_earnings": 985000,
    "average_earning_per_trip": 3078,
    "member_since": "2026-01-15",
    "this_month_trips": 78
  }
}
```

---

## 🌐 Real-Time Tracking

### 1. **Get Driver Location**
- **Endpoint:** `GET /mobile/tracking/driver/{driverId}`
- **Purpose:** Get current location of specific driver
- **Auth Required:** Yes

```json
Response:
{
  "success": true,
  "data": {
    "driver_id": 123,
    "latitude": -1.9536,
    "longitude": 30.0605,
    "speed_kmh": 45.5,
    "heading": 180.0,
    "accuracy": 5.2,
    "is_online": true,
    "last_updated": "2026-05-05T10:30:00Z",
    "last_activity": "2026-05-05T10:30:00Z"
  }
}
```

### 2. **Get Trip Driver Location**
- **Endpoint:** `GET /mobile/tracking/trip/{tripId}`
- **Purpose:** Get driver location for specific trip
- **Auth Required:** Yes

```json
Response:
{
  "success": true,
  "data": {
    "trip_id": 789,
    "driver_id": 123,
    "driver_name": "John Driver",
    "latitude": -1.9536,
    "longitude": 30.0605,
    "speed_kmh": 45.5,
    "heading": 180.0,
    "accuracy": 5.2,
    "is_online": true,
    "last_updated": "2026-05-05T10:30:00Z",
    "trip_status": "started"
  }
}
```

### 3. **Get Nearby Online Drivers**
- **Endpoint:** `GET /mobile/tracking/nearby`
- **Purpose:** Find nearby online drivers
- **Auth Required:** Yes
- **Query Params:**
  - `latitude` (required)
  - `longitude` (required)
  - `radius_km` (optional, default: 5)

```json
Response:
{
  "success": true,
  "data": [
    {
      "driver_id": 123,
      "latitude": -1.9536,
      "longitude": 30.0605,
      "speed_kmh": 45.5,
      "heading": 180.0,
      "accuracy": 5.2,
      "is_online": true,
      "distance_km": 2.3,
      "last_updated": "2026-05-05T10:30:00Z",
      "last_activity": "2026-05-05T10:30:00Z"
    }
  ]
}
```

### WebSocket Real-Time Updates

Use Supabase Realtime for live updates:

```dart
import 'package:supabase_flutter/supabase_flutter.dart';

final supabase = Supabase.instance.client;

// Subscribe to driver location updates
final subscription = supabase
    .channel('driver:123')
    .onBroadcast(
        event: 'driver.location.updated',
        callback: (payload) {
            print('Driver updated: ${payload}');
            // Update map marker
        },
    )
    .subscribe();

// Unsubscribe
await supabase.removeChannel(subscription);
```

---

## 🎯 Common Use Cases

### Use Case 1: **Passenger Booking a Private Ride**

```dart
// 1. Request Trip
var tripResponse = await dio.post(
  '/mobile/trips/request',
  data: {
    'pickup_location': 'Remera Station',
    'pickup_lat': -1.9536,
    'pickup_lng': 30.0605,
    'dropoff_location': 'Kimironko Market',
    'dropoff_lat': -1.9365,
    'dropoff_lng': 30.1200,
    'number_of_passengers': 1,
  },
);
int tripId = tripResponse.data['data']['id'];

// 2. Subscribe to driver location updates
final subscription = supabase
    .channel('driver:\${tripId}')
    .onBroadcast(event: 'driver.location.updated', callback: (payload) {
        updateMapMarker(payload);
    })
    .subscribe();

// 3. Track trip
var trackResponse = await dio.get('/mobile/trips/\${tripId}/track');

// 4. Complete trip
await dio.put(
  '/mobile/trips/\${tripId}/complete',
  data: {
    'rating': 5,
    'review': 'Great driver!',
    'payment_method': 'card',
  },
);

// 5. Unsubscribe
await supabase.removeChannel(subscription);
```

### Use Case 2: **Driver Going Online**

```dart
// 1. Update status to online
await dio.post(
  '/mobile/drivers/status',
  data: {
    'status': 'online',
    'latitude': -1.9536,
    'longitude': 30.0605,
    'availability_status': 'available',
  },
);

// 2. Start sending live location every 10 seconds
Timer.periodic(Duration(seconds: 10), (timer) {
    final position = await Geolocator.getCurrentPosition();
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
});

// 3. Listen for new trips
Timer.periodic(Duration(seconds: 5), (timer) {
    var tripsResponse = await dio.get('/mobile/drivers/trips');
    List trips = tripsResponse.data['data'];
    // Show trip notifications
});
```

### Use Case 3: **Driver Accepting and Completing a Trip**

```dart
// 1. Get available trips
var tripsResponse = await dio.get('/mobile/drivers/trips');

// 2. Accept a trip
var acceptResponse = await dio.post(
  '/mobile/drivers/trips/\${tripId}/accept',
  data: {
    'estimated_arrival_minutes': 5,
  },
);

// 3. Start trip (when arrived at pickup)
await dio.put(
  '/mobile/drivers/trips/\${tripId}/start',
  data: {
    'latitude': -1.9536,
    'longitude': 30.0605,
  },
);

// 4. Send location updates during trip
await dio.post(
  '/mobile/drivers/location',
  data: {
    'trip_id': tripId,
    'lat': -1.9400,
    'lng': 30.0800,
  },
);

// 5. Complete trip
await dio.put(
  '/mobile/drivers/trips/\${tripId}/complete',
  data: {
    'final_distance_km': 8.7,
    'final_fare': 3250,
    'payment_received': 3250,
    'payment_method': 'cash',
  },
);
```

---

## ⚠️ Error Handling

### Standard Error Response

```json
{
  "status": "error",
  "message": "Invalid request parameters",
  "code": 422,
  "errors": {
    "email": ["Email field is required"],
    "password": ["Password must be at least 8 characters"]
  }
}
```

### Common HTTP Status Codes

| Code | Meaning |
|------|---------|
| 200 | Success |
| 201 | Created |
| 400 | Bad Request |
| 401 | Unauthorized (invalid/expired token) |
| 403 | Forbidden (no permission) |
| 404 | Not Found |
| 422 | Validation Error |
| 429 | Too Many Requests (Rate Limited) |
| 500 | Server Error |

### Error Handling in Flutter

```dart
try {
  var response = await dio.get('/passenger/profile');
  print(response.data);
} on DioError catch (e) {
  if (e.response?.statusCode == 401) {
    // Token expired, refresh or redirect to login
    refreshToken();
  } else if (e.response?.statusCode == 429) {
    // Rate limited, show message
    showMessage('Too many requests, please try again later');
  } else {
    showError(e.response?.data['message'] ?? 'An error occurred');
  }
}
```

---

## 📊 Request/Response Examples

### Example: Complete Passenger Journey

**Step 1: Register**
```dart
final response = await dio.post(
  '/auth/register',
  data: {
    "name": "Jane Doe",
    "email": "jane@example.com",
    "phone": "+250788123456",
    "password": "SecurePass123!",
    "password_confirmation": "SecurePass123!",
    "role": "passenger"
  },
);

final token = response.data['data']['access_token'];
dio.options.headers['Authorization'] = 'Bearer $token';
```

**Step 2: Request Trip**
```dart
final tripResponse = await dio.post(
  '/mobile/trips/request',
  data: {
    "pickup_location": "Remera",
    "pickup_lat": -1.9536,
    "pickup_lng": 30.0605,
    "dropoff_location": "Kimironko",
    "dropoff_lat": -1.9365,
    "dropoff_lng": 30.1200,
    "number_of_passengers": 1,
  },
);

final tripId = tripResponse.data['data']['id'];
```

**Step 3: Track Driver**
```dart
// Poll location every 2 seconds
Timer.periodic(Duration(seconds: 2), (timer) {
  dio.get('/mobile/trips/$tripId/track').then((response) {
    final driver = response.data['data'];
    updateMapMarker(driver['latitude'], driver['longitude']);
  });
});
```

**Step 4: Complete Trip**
```dart
await dio.put(
  '/mobile/trips/$tripId/complete',
  data: {
    "rating": 5,
    "review": "Excellent service!",
    "payment_method": "card",
  },
);
```

---

## 🔗 Additional Resources

- **Base URL:** See [API Base URLs](#-api-base-urls) section
- **Authentication:** All endpoints except `/auth/*` require `Authorization: Bearer {token}`
- **Real-Time:** Use Supabase Realtime for live updates
- **Rate Limiting:** 1000 requests/hour per IP
- **Support:** api-support@rideconnect.rw

---

**Last Updated:** May 2026  
**API Version:** v1
