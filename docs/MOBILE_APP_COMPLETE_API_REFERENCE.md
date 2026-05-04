# Mobile App Complete API Reference

**RideConnect Mobile Application**  
**Version:** 2.0  
**Last Updated:** May 2026

---

## Table of Contents

1. [API Overview](#api-overview)
2. [Authentication & Authorization](#authentication--authorization)
3. [Passenger APIs](#passenger-apis)
4. [Driver APIs](#driver-apis)
5. [Realtime Features](#realtime-features)
6. [Error Handling](#error-handling)
7. [Rate Limiting](#rate-limiting)
8. [Data Models](#data-models)

---

## API Overview

### Base URL

```
Production: https://api.rideconnect.rw/api/v2
Staging: https://staging-api.rideconnect.rw/api/v2
Development: http://localhost:8000/api/v2
```

### API Versioning

All endpoints use `/api/v2` prefix. Future breaking changes will increment the version.

### Mobile App Endpoints

Mobile app APIs are under the `/mobile` prefix:

- Passenger endpoints: `/api/v2/mobile/*` (rides, bookings, trips)
- Driver endpoints: `/api/v2/mobile/drivers/*`
- Shared endpoints: `/api/v2/user/*` (profile management)

### Response Format

All responses follow a standardized JSON format:

```json
{
  "status": "success|error",
  "data": {},
  "message": "Human readable message",
  "code": 200,
  "timestamp": "2026-05-04T14:30:00Z"
}
```

### Authentication

All endpoints (except `/auth/login`, `/auth/register`, `/auth/forgot-password`) require:

```
Authorization: Bearer {access_token}
```

---

## Authentication & Authorization

### Register as Passenger

```http
POST /auth/register
Content-Type: application/json

{
  "name": "John Doe",
  "email": "john@example.com",
  "phone": "+250788123456",
  "password": "SecurePassword123!",
  "password_confirmation": "SecurePassword123!",
  "role": "passenger"
}
```

**Response (201):**
```json
{
  "status": "success",
  "data": {
    "id": 1,
    "name": "John Doe",
    "email": "john@example.com",
    "phone": "+250788123456",
    "role": "passenger",
    "is_approved": false,
    "is_verified": false,
    "access_token": "eyJhbGciOiJIUzI1NiIs...",
    "token_type": "Bearer",
    "expires_in": 86400
  }
}
```

### Register as Driver

```http
POST /auth/register
Content-Type: application/json

{
  "name": "Jane Smith",
  "email": "jane@example.com",
  "phone": "+250788654321",
  "password": "SecurePassword123!",
  "password_confirmation": "SecurePassword123!",
  "role": "driver",
  "vehicle_type": "CAR",
  "license_number": "DR123456",
  "national_id": "ID123456"
}
```

**Response (201):**
```json
{
  "status": "success",
  "data": {
    "id": 2,
    "name": "Jane Smith",
    "email": "jane@example.com",
    "phone": "+250788654321",
    "role": "driver",
    "is_approved": false,
    "is_verified": false,
    "access_token": "eyJhbGciOiJIUzI1NiIs...",
    "token_type": "Bearer",
    "expires_in": 86400
  }
}
```

### Login

```http
POST /auth/login
Content-Type: application/json

{
  "email": "john@example.com",
  "password": "SecurePassword123!",
  "device_token": "device_notification_token_here"
}
```

**Response (200):**
```json
{
  "status": "success",
  "data": {
    "id": 1,
    "name": "John Doe",
    "email": "john@example.com",
    "phone": "+250788123456",
    "role": "passenger",
    "is_approved": true,
    "is_verified": true,
    "access_token": "eyJhbGciOiJIUzI1NiIs...",
    "token_type": "Bearer",
    "expires_in": 86400
  }
}
```

### Refresh Token

```http
POST /auth/refresh
Authorization: Bearer {access_token}
```

**Response (200):**
```json
{
  "status": "success",
  "data": {
    "access_token": "eyJhbGciOiJIUzI1NiIs...",
    "token_type": "Bearer",
    "expires_in": 86400
  }
}
```

### Logout

```http
POST /auth/logout
Authorization: Bearer {access_token}
```

**Response (200):**
```json
{
  "status": "success",
  "message": "Logged out successfully"
}
```

### Verify Email

```http
POST /auth/verify-email
Content-Type: application/json

{
  "code": "123456"
}
```

**Response (200):**
```json
{
  "status": "success",
  "message": "Email verified successfully"
}
```

### Multi-Factor Authentication

```http
POST /auth/mfa/enable
Authorization: Bearer {access_token}
```

**Response (200):**
```json
{
  "status": "success",
  "data": {
    "secret": "JBSWY3DPEBLW64TMMQXDOYLUL5UVEAA=",
    "qr_code_url": "https://..."
  }
}
```

---

## Passenger APIs

### Profile Management

#### Get Profile

```http
GET /user/profile
Authorization: Bearer {access_token}
```

**Response (200):**
```json
{
  "status": "success",
  "data": {
    "id": 1,
    "name": "John Doe",
    "email": "john@example.com",
    "phone": "+250788123456",
    "profile_photo": "https://cdn.rideconnect.rw/photos/user_1.jpg",
    "is_approved": true,
    "is_verified": true,
    "rating": 4.8,
    "total_rides": 42,
    "joined_at": "2025-11-15T10:30:00Z",
    "preferred_payment_method": "mobile_money",
    "emergency_contact": {
      "name": "Jane Doe",
      "phone": "+250788654321"
    },
    "address": {
      "street": "123 Main Street",
      "city": "Kigali",
      "country": "Rwanda",
      "postal_code": "00000"
    }
  }
}
```

#### Update Profile

```http
PUT /us
Authorization: Bearer {access_token}
Content-Type: application/json

{
  "name": "John Doe Updated",
  "phone": "+250788123456",
  "profile_photo": "base64_image_data",
  "emergency_contact": {
    "name": "Jane Doe",
    "phone": "+250788654321"
  },
  "address": {
    "street": "456 New Street",
    "city": "Kigali",
    "country": "Rwanda",
    "postal_code": "00000"
  }
}
```

**Response (200):**
```json
{
  "status": "success",
  "message": "Profile updated successfully",
  "data": { /* updated profile */ }
}
```

#### Upload Profile Photo

```http
POST /mobile/passenger/profile/photo
Authorization: Bearer {access_token}
Content-Type: multipart/form-data

{
  "photo": <binary_file>
}
```

**Response (200):**
```json
{
  "status": "success",
  "data": {
    "photo_url": "https://cdn.rideconnect.rw/photos/user_1_updated.jpg"
  }
}
```

### Ride Discovery & Booking

#### Get Available Rides

```http
GET /mobile/rides?
  transport_type=CAR&
  travel_mode=ON_DEMAND&
  origin_lat=-1.9536&
  origin_lng=30.0606&
  destination_lat=-1.9441&
  destination_lng=30.0619&
  search=Kigali&
  page=1&
  per_page=20
Authorization: Bearer {access_token}
```

**Response (200):**
```json
{
  "status": "success",
  "data": [
    {
      "id": 101,
      "driver": {
        "id": 2,
        "name": "Jane Smith",
        "rating": 4.9,
        "phone": "+250788654321",
        "vehicle": {
          "make": "Toyota",
          "model": "Prius",
          "color": "Silver",
          "license_plate": "RAJ123A"
        }
      },
      "transport_type": "CAR",
      "travel_mode": "ON_DEMAND",
      "origin": {
        "address": "Kimihurura Roundabout, Kigali",
        "lat": -1.9536,
        "lng": 30.0606
      },
      "destination": {
        "address": "Kigali City Tower, Kigali",
        "lat": -1.9441,
        "lng": 30.0619
      },
      "distance_km": 2.5,
      "estimated_time_minutes": 12,
      "base_fare": 1500,
      "price_per_km": 400,
      "estimated_total_fare": 2500,
      "currency": "RWF",
      "available_seats": 3,
      "departure_time": "2026-05-04T14:30:00Z",
      "arrival_time_estimated": "2026-05-04T14:42:00Z",
      "ride_rules": {
        "can_book": false,
        "can_request_trip": true,
        "luggage_allowed": true,
        "pets_allowed": false,
        "smoking_allowed": false,
        "requires_approval": false,
        "min_advance_booking_hours": 0
      },
      "status": "PUBLISHED"
    }
  ],
  "pagination": {
    "current_page": 1,
    "per_page": 20,
    "total": 45,
    "last_page": 3
  }
}
```

#### Get Ride Details

```http
GET /mobile/rides/{id}
Authorization: Bearer {access_token}
```

**Response (200):**
```json
{
  "status": "success",
  "data": {
    "id": 101,
    "driver": {
      "id": 2,
      "name": "Jane Smith",
      "email": "jane@example.com",
      "phone": "+250788654321",
      "rating": 4.9,
      "total_rides": 287,
      "response_time_seconds": 45,
      "profile_photo": "https://cdn.rideconnect.rw/photos/driver_2.jpg",
      "vehicle": {
        "id": 5,
        "make": "Toyota",
        "model": "Prius",
        "year": 2023,
        "color": "Silver",
        "license_plate": "RAJ123A",
        "seats": 4
      }
    },
    "transport_type": "CAR",
    "travel_mode": "ON_DEMAND",
    "origin": {
      "address": "Kimihurura Roundabout, Kigali",
      "lat": -1.9536,
      "lng": 30.0606
    },
    "destination": {
      "address": "Kigali City Tower, Kigali",
      "lat": -1.9441,
      "lng": 30.0619
    },
    "distance_km": 2.5,
    "estimated_time_minutes": 12,
    "pricing": {
      "base_fare": 1500,
      "distance_fare": 1000,
      "surge_multiplier": 1.0,
      "discount_applied": false,
      "discount_amount": 0,
      "estimated_total_fare": 2500,
      "currency": "RWF"
    },
    "available_seats": 3,
    "departure_time": "2026-05-04T14:30:00Z",
    "arrival_time_estimated": "2026-05-04T14:42:00Z",
    "reviews_count": 142,
    "average_rating": 4.9,
    "ride_rules": {
      "can_book": false,
      "can_request_trip": true,
      "luggage_allowed": true,
      "pets_allowed": false,
      "smoking_allowed": false,
      "requires_approval": false,
      "min_advance_booking_hours": 0
    },
    "status": "PUBLISHED",
    "created_at": "2026-05-04T12:00:00Z"
  }
}
```

### Booking Management (Scheduled Rides)

#### Create Booking

```http
POST /mobile/bookings
Authorization: Bearer {access_token}
Content-Type: application/json

{
  "ride_id": 101,
  "seats_booked": 2,
  "pickup_address": "Kimihurura Roundabout, Kigali",
  "pickup_lat": -1.9536,
  "pickup_lng": 30.0606,
  "dropoff_address": "Kigali City Tower, Kigali",
  "dropoff_lat": -1.9441,
  "dropoff_lng": 30.0619,
  "special_requests": "Please wait at main entrance"
}
```

**Response (201):**
```json
{
  "status": "success",
  "message": "Booking created successfully",
  "data": {
    "id": 501,
    "ride_id": 101,
    "seats_booked": 2,
    "total_price": 5000,
    "currency": "RWF",
    "status": "PENDING",
    "payment_status": "UNPAID",
    "pickup_address": "Kimihurura Roundabout, Kigali",
    "dropoff_address": "Kigali City Tower, Kigali",
    "special_requests": "Please wait at main entrance",
    "hours_to_departure": 2.5,
    "travel_type": "BOOKING",
    "ticket_status": "PENDING",
    "confirmation_code": "RIDE-101-5001",
    "created_at": "2026-05-04T12:30:00Z"
  }
}
```

#### Get My Bookings

```http
GET /mobile/bookings?
  status=PENDING&
  date_from=2026-05-01&
  date_to=2026-05-31&
  page=1&
  per_page=20
Authorization: Bearer {access_token}
```

**Response (200):**
```json
{
  "status": "success",
  "data": [
    {
      "id": 501,
      "ride": {
        "id": 101,
        "origin": "Kimihurura Roundabout, Kigali",
        "destination": "Kigali City Tower, Kigali",
        "departure_time": "2026-05-04T14:30:00Z",
        "driver": {
          "name": "Jane Smith",
          "rating": 4.9
        }
      },
      "seats_booked": 2,
      "total_price": 5000,
      "currency": "RWF",
      "status": "PENDING",
      "payment_status": "UNPAID",
      "hours_to_departure": 2.5,
      "travel_type": "BOOKING",
      "ticket_status": "PENDING",
      "confirmation_code": "RIDE-101-5001",
      "created_at": "2026-05-04T12:30:00Z"
    }
  ],
  "pagination": {
    "current_page": 1,
    "per_page": 20,
    "total": 5,
    "last_page": 1
  }
}
```

#### Get Booking Details

```http
GET /mobile/bookings/{id}
Authorization: Bearer {access_token}
```

**Response (200):**
```json
{
  "status": "success",
  "data": {
    "id": 501,
    "ride": {
      "id": 101,
      "origin": {
        "address": "Kimihurura Roundabout, Kigali",
        "lat": -1.9536,
        "lng": 30.0606
      },
      "destination": {
        "address": "Kigali City Tower, Kigali",
        "lat": -1.9441,
        "lng": 30.0619
      },
      "departure_time": "2026-05-04T14:30:00Z",
      "arrival_time_estimated": "2026-05-04T14:42:00Z",
      "driver": {
        "id": 2,
        "name": "Jane Smith",
        "email": "jane@example.com",
        "phone": "+250788654321",
        "rating": 4.9,
        "vehicle": {
          "make": "Toyota",
          "model": "Prius",
          "color": "Silver",
          "license_plate": "RAJ123A"
        }
      }
    },
    "user": {
      "id": 1,
      "name": "John Doe",
      "email": "john@example.com",
      "phone": "+250788123456"
    },
    "seats_booked": 2,
    "total_price": 5000,
    "currency": "RWF",
    "status": "PENDING",
    "payment_status": "UNPAID",
    "pickup_address": "Kimihurura Roundabout, Kigali",
    "pickup_lat": -1.9536,
    "pickup_lng": 30.0606,
    "dropoff_address": "Kigali City Tower, Kigali",
    "dropoff_lat": -1.9441,
    "dropoff_lng": 30.0619,
    "special_requests": "Please wait at main entrance",
    "hours_to_departure": 2.5,
    "travel_type": "BOOKING",
    "ticket_status": "PENDING",
    "confirmation_code": "RIDE-101-5001",
    "payment": {
      "id": 1001,
      "amount": 5000,
      "status": "PENDING",
      "payment_method": "MOBILE_MONEY"
    },
    "review": null,
    "created_at": "2026-05-04T12:30:00Z",
    "confirmed_at": null,
    "cancelled_at": null
  }
}
```

#### Confirm Booking

```http
PUT /passenger/bookings/{id}/confirm
Authorization: Bearer {access_token}
Content-Type: application/json

{
  "payment_method": "mobile_money"
}
```

**Response (200):**
```json
{
  "status": "success",
  "message": "Booking confirmed successfully",
  "data": {
    "id": 501,
    "status": "CONFIRMED",
    "payment_status": "PENDING_PAYMENT",
    "confirmed_at": "2026-05-04T12:35:00Z"
  }
}
```

#### Cancel Booking

```http
PUT /mobile/bookings/{id}/cancel
Authorization: Bearer {access_token}
Content-Type: application/json

{
  "reason": "Travel plans changed"
}
```

**Response (200):**
```json
{
  "status": "success",
  "message": "Booking cancelled successfully",
  "data": {
    "id": 501,
    "status": "CANCELLED",
    "cancelled_at": "2026-05-04T12:40:00Z",
    "refund_amount": 5000,
    "refund_status": "PROCESSING"
  }
}
```

### Trip Management (On-Demand)

#### Request Trip

```http
POST /mobile/trips/request
Authorization: Bearer {access_token}
Content-Type: application/json

{
  "ride_id": null,
  "transport_type": "CAR",
  "pickup_location": "Kimihurura Roundabout, Kigali",
  "pickup_lat": -1.9536,
  "pickup_lng": 30.0606,
  "dropoff_location": "Kigali City Tower, Kigali",
  "dropoff_lat": -1.9441,
  "dropoff_lng": 30.0619,
  "preferred_driver_id": null
}
```

**Response (201):**
```json
{
  "status": "success",
  "message": "Trip request created successfully",
  "data": {
    "id": 1001,
    "passenger_id": 1,
    "driver_id": null,
    "booking_id": null,
    "ride_id": null,
    "pickup_location": "Kimihurura Roundabout, Kigali",
    "pickup_lat": -1.9536,
    "pickup_lng": 30.0606,
    "dropoff_location": "Kigali City Tower, Kigali",
    "dropoff_lat": -1.9441,
    "dropoff_lng": 30.0619,
    "fare": 2500,
    "status": "PENDING",
    "requested_at": "2026-05-04T14:30:00Z",
    "accepted_at": null,
    "started_at": null,
    "completed_at": null,
    "confirmation_code": "TRIP-2500-1001"
  }
}
```

#### Get My Trips

```http
GET /mobile/trips?
  status=COMPLETED&
  date_from=2026-05-01&
  date_to=2026-05-31&
  page=1&
  per_page=20
Authorization: Bearer {access_token}
```

**Response (200):**
```json
{
  "status": "success",
  "data": [
    {
      "id": 1001,
      "driver": {
        "id": 2,
        "name": "Jane Smith",
        "rating": 4.9,
        "vehicle": {
          "make": "Toyota",
          "model": "Prius",
          "license_plate": "RAJ123A"
        }
      },
      "pickup_location": "Kimihurura Roundabout, Kigali",
      "dropoff_location": "Kigali City Tower, Kigali",
      "fare": 2500,
      "actual_fare": 2500,
      "status": "COMPLETED",
      "started_at": "2026-05-04T14:32:00Z",
      "completed_at": "2026-05-04T14:45:00Z",
      "duration_minutes": 13,
      "distance_km": 2.5,
      "rating_given": 5,
      "confirmation_code": "TRIP-2500-1001"
    }
  ],
  "pagination": {
    "current_page": 1,
    "per_page": 20,
    "total": 42,
    "last_page": 3
  }
}
```

#### Get Trip Details

```http
GET /mobile/trips/{trip_id}
Authorization: Bearer {access_token}
```

**Response (200):**
```json
{
  "status": "success",
  "data": {
    "id": 1001,
    "passenger": {
      "id": 1,
      "name": "John Doe",
      "email": "john@example.com",
      "phone": "+250788123456"
    },
    "driver": {
      "id": 2,
      "name": "Jane Smith",
      "email": "jane@example.com",
      "phone": "+250788654321",
      "rating": 4.9,
      "profile_photo": "https://cdn.rideconnect.rw/photos/driver_2.jpg",
      "vehicle": {
        "make": "Toyota",
        "model": "Prius",
        "year": 2023,
        "color": "Silver",
        "license_plate": "RAJ123A",
        "seats": 4
      }
    },
    "pickup_location": "Kimihurura Roundabout, Kigali",
    "pickup_lat": -1.9536,
    "pickup_lng": 30.0606,
    "dropoff_location": "Kigali City Tower, Kigali",
    "dropoff_lat": -1.9441,
    "dropoff_lng": 30.0619,
    "fare": 2500,
    "actual_fare": 2500,
    "status": "COMPLETED",
    "requested_at": "2026-05-04T14:30:00Z",
    "accepted_at": "2026-05-04T14:31:00Z",
    "started_at": "2026-05-04T14:32:00Z",
    "completed_at": "2026-05-04T14:45:00Z",
    "duration_minutes": 13,
    "distance_km": 2.5,
    "actual_pickup_lat": -1.9535,
    "actual_pickup_lng": 30.0607,
    "actual_dropoff_lat": -1.9440,
    "actual_dropoff_lng": 30.0620,
    "actual_distance": 2.5,
    "driver_current_location": {
      "lat": -1.9440,
      "lng": 30.0620,
      "heading": 45,
      "speed_kmh": 0
    },
    "review_given": {
      "id": 201,
      "rating": 5,
      "comment": "Excellent driver, very professional",
      "created_at": "2026-05-04T15:00:00Z"
    },
    "confirmation_code": "TRIP-2500-1001"
  }
}
```

#### Track Trip

```http
GET /mobile/trips/{trip_id}/track
Authorization: Bearer {access_token}
```

**Response (200):**
```json
{
  "status": "success",
  "data": {
    "driver_location": {
      "lat": -1.9440,
      "lng": 30.0620,
      "heading": 45,
      "speed_kmh": 42,
      "accuracy": 10,
      "timestamp": "2026-05-04T14:35:00Z"
    },
    "route_path": [
      {"lat": -1.9536, "lng": 30.0606},
      {"lat": -1.9500, "lng": 30.0610},
      {"lat": -1.9440, "lng": 30.0620}
    ],
    "eta": 8
  }
}
```

#### Accept Trip (if offered to multiple drivers)

```http
PUT /mobile/trips/{trip_id}/accept
Authorization: Bearer {access_token}
```

**Response (200):**
```json
{
  "status": "success",
  "message": "Trip accepted",
  "data": {
    "id": 1001,
    "status": "ACCEPTED",
    "accepted_at": "2026-05-04T14:31:00Z"
  }
}
```

#### Cancel Trip

```http
PUT /mobile/trips/{trip_id}/cancel
Authorization: Bearer {access_token}
Content-Type: application/json

{
  "reason": "Found another ride",
  "cancellation_feedback": "Driver took too long"
}
```

**Response (200):**
```json
{
  "status": "success",
  "message": "Trip cancelled successfully",
  "data": {
    "id": 1001,
    "status": "CANCELLED",
    "cancellation_fee": 0,
    "refund_amount": 2500
  }
}
```

#### Rate Trip

```http
POST /mobile/trips/{trip_id}/rate
Authorization: Bearer {access_token}
Content-Type: application/json

{
  "rating": 5,
  "comment": "Great driver, very professional",
  "categories": {
    "cleanliness": 5,
    "safety": 5,
    "communication": 5,
    "driving_skill": 5
  }
}
```

**Response (201):**
```json
{
  "status": "success",
  "message": "Rating submitted successfully",
  "data": {
    "id": 201,
    "trip_id": 1001,
    "rating": 5,
    "comment": "Great driver, very professional",
    "created_at": "2026-05-04T15:00:00Z"
  }
}
```

### Payment

#### Create Payment

```http
POST /passenger/payments
Authorization: Bearer {access_token}
Content-Type: application/json

{
  "booking_id": null,
  "trip_id": 1001,
  "amount": 2500,
  "currency": "RWF",
  "payment_method": "MOBILE_MONEY",
  "phone_number": "+250788123456"
}
```

**Response (201):**
```json
{
  "status": "success",
  "message": "Payment initiated",
  "data": {
    "id": 1001,
    "trip_id": 1001,
    "amount": 2500,
    "currency": "RWF",
    "status": "PENDING",
    "payment_method": "MOBILE_MONEY",
    "transaction_id": "MTN-20260504-001",
    "created_at": "2026-05-04T14:45:00Z",
    "expires_at": "2026-05-04T14:50:00Z"
  }
}
```

#### Verify Payment

```http
GET /passenger/payments/{id}/verify
Authorization: Bearer {access_token}
```

**Response (200):**
```json
{
  "status": "success",
  "data": {
    "id": 1001,
    "trip_id": 1001,
    "amount": 2500,
    "status": "COMPLETED",
    "verified_at": "2026-05-04T14:46:00Z",
    "receipt_url": "https://cdn.rideconnect.rw/receipts/payment_1001.pdf"
  }
}
```

#### Get Payment History

```http
GET /passenger/payments/history?
  status=COMPLETED&
  date_from=2026-05-01&
  page=1&
  per_page=20
Authorization: Bearer {access_token}
```

**Response (200):**
```json
{
  "status": "success",
  "data": [
    {
      "id": 1001,
      "trip_id": 1001,
      "booking_id": null,
      "amount": 2500,
      "currency": "RWF",
      "status": "COMPLETED",
      "payment_method": "MOBILE_MONEY",
      "transaction_id": "MTN-20260504-001",
      "created_at": "2026-05-04T14:45:00Z"
    }
  ],
  "pagination": {
    "current_page": 1,
    "per_page": 20,
    "total": 42,
    "last_page": 3
  },
  "summary": {
    "total_spent_month": 125000,
    "total_spent_all_time": 890000,
    "average_trip_cost": 2500
  }
}
```

### Wallet & Credits

#### Get Wallet Balance

```http
GET /passenger/wallet
Authorization: Bearer {access_token}
```

**Response (200):**
```json
{
  "status": "success",
  "data": {
    "balance": 15000,
    "currency": "RWF",
    "locked_amount": 2500,
    "available_balance": 12500,
    "last_transaction": {
      "id": 5001,
      "type": "DEBIT",
      "amount": 2500,
      "description": "Trip payment",
      "timestamp": "2026-05-04T14:45:00Z"
    }
  }
}
```

#### Add Funds to Wallet

```http
POST /passenger/wallet/add-funds
Authorization: Bearer {access_token}
Content-Type: application/json

{
  "amount": 10000,
  "payment_method": "MOBILE_MONEY",
  "phone_number": "+250788123456"
}
```

**Response (201):**
```json
{
  "status": "success",
  "message": "Payment initiated",
  "data": {
    "transaction_id": "WALLET-20260504-001",
    "amount": 10000,
    "status": "PENDING"
  }
}
```

### Support & Tickets

#### Create Support Ticket

```http
POST /passenger/support/tickets
Authorization: Bearer {access_token}
Content-Type: application/json

{
  "subject": "Driver was unprofessional",
  "description": "The driver was rude to me during the trip",
  "ticket_type": "complaint",
  "priority": "high",
  "trip_id": 1001,
  "attachments": ["image_base64_1", "image_base64_2"]
}
```

**Response (201):**
```json
{
  "status": "success",
  "message": "Support ticket created",
  "data": {
    "id": 5001,
    "ticket_number": "TKT-2026-05-001",
    "subject": "Driver was unprofessional",
    "status": "OPEN",
    "priority": "high",
    "created_at": "2026-05-04T14:50:00Z"
  }
}
```

#### Get Support Tickets

```http
GET /passenger/support/tickets?status=OPEN
Authorization: Bearer {access_token}
```

**Response (200):**
```json
{
  "status": "success",
  "data": [
    {
      "id": 5001,
      "ticket_number": "TKT-2026-05-001",
      "subject": "Driver was unprofessional",
      "description": "The driver was rude to me during the trip",
      "status": "IN_PROGRESS",
      "priority": "high",
      "assigned_to": {
        "id": 100,
        "name": "Support Agent",
        "role": "support_agent"
      },
      "created_at": "2026-05-04T14:50:00Z",
      "updated_at": "2026-05-04T15:30:00Z",
      "messages_count": 3
    }
  ]
}
```

#### Send Ticket Message

```http
POST /passenger/support/tickets/{id}/messages
Authorization: Bearer {access_token}
Content-Type: application/json

{
  "message": "Can you please provide more details about the incident?",
  "attachments": ["document_base64"]
}
```

**Response (201):**
```json
{
  "status": "success",
  "data": {
    "id": 50001,
    "ticket_id": 5001,
    "sender": "support_agent",
    "message": "Can you please provide more details about the incident?",
    "created_at": "2026-05-04T15:30:00Z"
  }
}
```

### Statistics & Analytics

#### Get Passenger Stats

```http
GET /passenger/stats
Authorization: Bearer {access_token}
```

**Response (200):**
```json
{
  "status": "success",
  "data": {
    "total_trips": 42,
    "total_bookings": 8,
    "completed_trips": 40,
    "cancelled_trips": 2,
    "total_spent": 890000,
    "average_rating": 4.8,
    "favorite_routes": [
      {
        "from": "Kimihurura",
        "to": "Kigali City Tower",
        "trips": 12
      }
    ],
    "favorite_drivers": [
      {
        "id": 2,
        "name": "Jane Smith",
        "trips": 15
      }
    ],
    "membership_level": "GOLD",
    "loyalty_points": 8900,
    "member_since": "2025-11-15",
    "statistics": {
      "month": {
        "trips": 5,
        "spent": 12500,
        "avg_rating": 4.9
      },
      "year": {
        "trips": 42,
        "spent": 890000,
        "avg_rating": 4.8
      }
    }
  }
}
```

---

## Driver APIs

### Profile Management

#### Get Profile

```http
GET /user/profile
Authorization: Bearer {access_token}
```

**Response (200):**
```json
{
  "status": "success",
  "data": {
    "id": 2,
    "name": "Jane Smith",
    "email": "jane@example.com",
    "phone": "+250788654321",
    "profile_photo": "https://cdn.rideconnect.rw/photos/driver_2.jpg",
    "rating": 4.9,
    "total_trips": 287,
    "response_time_seconds": 45,
    "acceptance_rate": 92.5,
    "cancellation_rate": 2.1,
    "documents": {
      "license": {
        "number": "DR123456",
        "expiry_date": "2027-05-15",
        "status": "VERIFIED"
      },
      "national_id": {
        "number": "ID123456",
        "expiry_date": "2028-03-10",
        "status": "VERIFIED"
      },
      "vehicle_registration": {
        "number": "VEH123456",
        "expiry_date": "2026-12-31",
        "status": "VERIFIED"
      },
      "insurance": {
        "provider": "Insurance Co.",
        "expiry_date": "2026-12-31",
        "status": "VERIFIED"
      }
    },
    "vehicle": {
      "id": 5,
      "make": "Toyota",
      "model": "Prius",
      "year": 2023,
      "color": "Silver",
      "license_plate": "RAJ123A",
      "seats": 4,
      "transmission": "Automatic",
      "fuel_type": "Hybrid"
    },
    "bank_account": {
      "bank_name": "Rwanda Development Bank",
      "account_number": "****1234",
      "holder_name": "Jane Smith",
      "status": "VERIFIED"
    },
    "status": "ONLINE",
    "is_approved": true,
    "is_verified": true,
    "joined_at": "2024-01-15T10:30:00Z"
  }
}
```

#### Update Profile

```http
PUT /user/profile
Authorization: Bearer {access_token}
Content-Type: application/json

{
  "name": "Jane Smith Updated",
  "phone": "+250788654321",
  "profile_photo": "base64_image_data",
  "bank_account": {
    "bank_name": "Rwanda Development Bank",
    "account_number": "1234567890",
    "holder_name": "Jane Smith"
  }
}
```

**Response (200):**
```json
{
  "status": "success",
  "message": "Profile updated successfully",
  "data": { /* updated profile */ }
}
```

### Availability & Status

#### Update Status

```http
POST /mobile/drivers/status
Authorization: Bearer {access_token}
Content-Type: application/json

{
  "status": "ONLINE",
  "location": {
    "lat": -1.9536,
    "lng": 30.0606
  }
}
```

**Response (200):**
```json
{
  "status": "success",
  "message": "Status updated",
  "data": {
    "driver_id": 2,
    "current_status": "ONLINE",
    "location": {
      "lat": -1.9536,
      "lng": 30.0606,
      "timestamp": "2026-05-04T14:30:00Z"
    }
  }
}
```

#### Get Current Status

```http
GET /driver/status
Authorization: Bearer {access_token}
```

**Response (200):**
```json
{
  "status": "success",
  "data": {
    "current_status": "ONLINE",
    "location": {
      "lat": -1.9536,
      "lng": 30.0606,
      "heading": 45,
      "speed_kmh": 42
    },
    "available_trips": 3,
    "total_earnings_today": 45000,
    "active_trip_id": null
  }
}
```

### Trip Management

#### Get Available Trip Requests

```http
GET /mobile/drivers/trips?
  status=PENDING&
  radius_km=10
Authorization: Bearer {access_token}
```

**Response (200):**
```json
{
  "status": "success",
  "data": [
    {
      "id": 1001,
      "passenger": {
        "id": 1,
        "name": "John Doe",
        "rating": 4.8,
        "total_trips": 42,
        "profile_photo": "https://cdn.rideconnect.rw/photos/user_1.jpg"
      },
      "pickup_location": "Kimihurura Roundabout, Kigali",
      "pickup_lat": -1.9536,
      "pickup_lng": 30.0606,
      "dropoff_location": "Kigali City Tower, Kigali",
      "dropoff_lat": -1.9441,
      "dropoff_lng": 30.0619,
      "estimated_distance_km": 2.5,
      "estimated_time_minutes": 12,
      "estimated_fare": 2500,
      "currency": "RWF",
      "status": "PENDING",
      "requested_at": "2026-05-04T14:30:00Z",
      "expires_at": "2026-05-04T14:35:00Z",
      "distance_from_driver_km": 0.8,
      "time_to_pickup_minutes": 3
    }
  ]
}
```

#### Accept Trip Request

```http
POST /mobile/drivers/trips/{trip_id}/accept
Authorization: Bearer {access_token}
Content-Type: application/json

{
  "current_location": {
    "lat": -1.9540,
    "lng": 30.0600
  }
}
```

**Response (200):**
```json
{
  "status": "success",
  "message": "Trip request accepted",
  "data": {
    "id": 1001,
    "trip_id": 1001,
    "status": "ACCEPTED",
    "passenger": {
      "id": 1,
      "name": "John Doe",
      "phone": "+250788123456",
      "rating": 4.8
    },
    "pickup_location": "Kimihurura Roundabout, Kigali",
    "dropoff_location": "Kigali City Tower, Kigali",
    "estimated_fare": 2500,
    "accepted_at": "2026-05-04T14:31:00Z"
  }
}
```

#### Reject Trip Request

```http
PUT /driver/trip-requests/{id}/reject
Authorization: Bearer {access_token}
Content-Type: application/json

{
  "reason": "Too far from current location"
}
```

**Response (200):**
```json
{
  "status": "success",
  "message": "Trip request rejected"
}
```

#### Get Active Trips

```http
GET /driver/trips/active
Authorization: Bearer {access_token}
```

**Response (200):**
```json
{
  "status": "success",
  "data": [
    {
      "id": 1001,
      "passenger": {
        "id": 1,
        "name": "John Doe",
        "phone": "+250788123456",
        "rating": 4.8
      },
      "pickup_location": "Kimihurura Roundabout, Kigali",
      "pickup_lat": -1.9536,
      "pickup_lng": 30.0606,
      "dropoff_location": "Kigali City Tower, Kigali",
      "dropoff_lat": -1.9441,
      "dropoff_lng": 30.0619,
      "status": "ACCEPTED",
      "passenger_current_location": {
        "lat": -1.9537,
        "lng": 30.0605
      },
      "estimated_pickup_time_minutes": 2,
      "estimated_fare": 2500,
      "accepted_at": "2026-05-04T14:31:00Z"
    }
  ]
}
```

#### Get Trip Details

```http
GET /driver/trips/{id}
Authorization: Bearer {access_token}
```

**Response (200):**
```json
{
  "status": "success",
  "data": {
    "id": 1001,
    "passenger": {
      "id": 1,
      "name": "John Doe",
      "email": "john@example.com",
      "phone": "+250788123456",
      "rating": 4.8,
      "total_trips": 42,
      "profile_photo": "https://cdn.rideconnect.rw/photos/user_1.jpg"
    },
    "pickup_location": "Kimihurura Roundabout, Kigali",
    "pickup_lat": -1.9536,
    "pickup_lng": 30.0606,
    "dropoff_location": "Kigali City Tower, Kigali",
    "dropoff_lat": -1.9441,
    "dropoff_lng": 30.0619,
    "estimated_distance_km": 2.5,
    "estimated_time_minutes": 12,
    "estimated_fare": 2500,
    "actual_distance_km": null,
    "actual_time_minutes": null,
    "actual_fare": null,
    "status": "ACCEPTED",
    "requested_at": "2026-05-04T14:30:00Z",
    "accepted_at": "2026-05-04T14:31:00Z",
    "started_at": null,
    "completed_at": null,
    "passenger_current_location": {
      "lat": -1.9537,
      "lng": 30.0605
    }
  }
}
```

#### Start Trip

```http
PUT /mobile/drivers/trips/{trip_id}/start
Authorization: Bearer {access_token}
Content-Type: application/json

{
  "location": {
    "lat": -1.9536,
    "lng": 30.0606
  }
}
```

**Response (200):**
```json
{
  "status": "success",
  "message": "Trip started",
  "data": {
    "id": 1001,
    "status": "STARTED",
    "started_at": "2026-05-04T14:32:00Z"
  }
}
```

#### Update Location (Live Tracking)

```http
POST /mobile/drivers/location
Authorization: Bearer {access_token}
Content-Type: application/json

{
  "lat": -1.9540,
  "lng": 30.0610,
  "accuracy": 15,
  "heading": 45,
  "speed_kmh": 42
}
```

**Response (200):**
```json
{
  "status": "success",
  "message": "Location updated"
}
```

#### Complete Trip

```http
PUT /mobile/drivers/trips/{trip_id}/complete
Authorization: Bearer {access_token}
Content-Type: application/json

{
  "location": {
    "lat": -1.9441,
    "lng": 30.0619
  },
  "actual_distance_km": 2.5,
  "actual_fare": 2500
}
```

**Response (200):**
```json
{
  "status": "success",
  "message": "Trip completed successfully",
  "data": {
    "id": 1001,
    "status": "COMPLETED",
    "completed_at": "2026-05-04T14:45:00Z",
    "actual_distance_km": 2.5,
    "actual_fare": 2500,
    "driver_earnings": 2000,
    "trip_rating": null
  }
}
```

#### Cancel Trip

```http
PUT /mobile/drivers/trips/{trip_id}/cancel
Authorization: Bearer {access_token}
Content-Type: application/json

{
  "reason": "Vehicle breakdown",
  "cancellation_type": "driver_initiated"
}
```

**Response (200):**
```json
{
  "status": "success",
  "message": "Trip cancelled",
  "data": {
    "id": 1001,
    "status": "CANCELLED",
    "cancelled_at": "2026-05-04T14:35:00Z",
    "cancellation_fee": 0,
    "reason": "Vehicle breakdown"
  }
}
```

#### Get Trip History

```http
GET /driver/trips/history?
  status=COMPLETED&
  date_from=2026-05-01&
  page=1&
  per_page=20
Authorization: Bearer {access_token}
```

**Response (200):**
```json
{
  "status": "success",
  "data": [
    {
      "id": 1000,
      "passenger": {
        "id": 3,
        "name": "Alice Johnson",
        "rating": 4.7
      },
      "pickup_location": "CBD, Kigali",
      "dropoff_location": "Nyarutarama",
      "actual_distance_km": 5.2,
      "actual_fare": 4500,
      "driver_earnings": 3600,
      "trip_rating": 5,
      "passenger_rating_comment": "Excellent service",
      "completed_at": "2026-05-04T12:00:00Z"
    },
    {
      "id": 999,
      "passenger": {
        "id": 4,
        "name": "Bob Wilson",
        "rating": 4.5
      },
      "pickup_location": "Kimihurura",
      "dropoff_location": "Kigali City Tower",
      "actual_distance_km": 2.5,
      "actual_fare": 2500,
      "driver_earnings": 2000,
      "trip_rating": 4,
      "completed_at": "2026-05-04T10:30:00Z"
    }
  ],
  "pagination": {
    "current_page": 1,
    "per_page": 20,
    "total": 287,
    "last_page": 15
  }
}
```

### Earnings

#### Get Earnings Summary

```http
GET /driver/earnings
Authorization: Bearer {access_token}
```

**Response (200):**
```json
{
  "status": "success",
  "data": {
    "today": {
      "trips_completed": 15,
      "total_earnings": 45000,
      "average_rating": 4.9,
      "hours_online": 6
    },
    "this_week": {
      "trips_completed": 82,
      "total_earnings": 215000,
      "average_rating": 4.85,
      "hours_online": 38
    },
    "this_month": {
      "trips_completed": 287,
      "total_earnings": 890000,
      "average_rating": 4.8,
      "hours_online": 140
    },
    "all_time": {
      "trips_completed": 287,
      "total_earnings": 890000,
      "average_rating": 4.8,
      "member_since": "2024-01-15"
    },
    "account_balance": {
      "total": 125000,
      "pending": 2500,
      "available": 122500,
      "withdrawable": 122500
    }
  }
}
```

#### Get Monthly Earnings

```http
GET /driver/earnings/monthly?month=2026-05
Authorization: Bearer {access_token}
```

**Response (200):**
```json
{
  "status": "success",
  "data": {
    "month": "2026-05",
    "summary": {
      "trips_completed": 287,
      "total_earnings": 890000,
      "average_trip_earnings": 3100,
      "total_fuel_cost_estimated": 45000,
      "net_earnings": 845000,
      "hours_online": 140,
      "average_rating": 4.8,
      "cancellation_rate": 2.1
    },
    "daily_breakdown": [
      {
        "date": "2026-05-01",
        "trips": 15,
        "earnings": 45000,
        "hours_online": 6,
        "rating": 4.9
      },
      {
        "date": "2026-05-02",
        "trips": 14,
        "earnings": 42000,
        "hours_online": 5.5,
        "rating": 4.85
      }
    ],
    "breakdown_by_type": {
      "scheduled_rides": {
        "trips": 120,
        "earnings": 380000
      },
      "on_demand_trips": {
        "trips": 167,
        "earnings": 510000
      }
    }
  }
}
```

#### Withdraw Earnings

```http
POST /driver/earnings/withdraw
Authorization: Bearer {access_token}
Content-Type: application/json

{
  "amount": 100000,
  "method": "bank_transfer"
}
```

**Response (201):**
```json
{
  "status": "success",
  "message": "Withdrawal initiated",
  "data": {
    "id": 5001,
    "amount": 100000,
    "method": "bank_transfer",
    "status": "PROCESSING",
    "estimated_arrival": "2026-05-06T10:00:00Z",
    "transaction_id": "WITHDRAW-20260504-001"
  }
}
```

### Documents

#### Upload Document

```http
POST /driver/documents
Authorization: Bearer {access_token}
Content-Type: multipart/form-data

{
  "document_type": "license",
  "file": <binary_file>,
  "expiry_date": "2027-05-15"
}
```

**Response (201):**
```json
{
  "status": "success",
  "message": "Document uploaded successfully",
  "data": {
    "id": 201,
    "document_type": "license",
    "status": "PENDING_VERIFICATION",
    "uploaded_at": "2026-05-04T14:50:00Z",
    "verification_status": "PENDING"
  }
}
```

#### Get Documents

```http
GET /driver/documents
Authorization: Bearer {access_token}
```

**Response (200):**
```json
{
  "status": "success",
  "data": [
    {
      "id": 201,
      "document_type": "license",
      "status": "VERIFIED",
      "expiry_date": "2027-05-15",
      "uploaded_at": "2025-11-15T10:30:00Z",
      "verification_status": "APPROVED"
    },
    {
      "id": 202,
      "document_type": "national_id",
      "status": "VERIFIED",
      "expiry_date": "2028-03-10",
      "uploaded_at": "2025-11-15T10:35:00Z",
      "verification_status": "APPROVED"
    }
  ]
}
```

---

## Realtime Features

### Supabase Realtime Channels

All realtime features use Supabase broadcast channels with naming scheme:

- `trip:{trip_id}` — Trip lifecycle and location updates
- `driver:{driver_id}` — Driver request notifications
- `passenger:{passenger_id}` — Passenger-specific updates

### Flutter Realtime Subscription Example

```dart
import 'package:supabase_flutter/supabase_flutter.dart';

class TripRealtimeService {
  final supabase = Supabase.instance.client;

  void subscribeToTrip(int tripId) {
    final channel = supabase.channel('trip:$tripId')
      .onBroadcast(
        event: 'driver.location.updated',
        callback: (payload) {
          final lat = payload['lat'];
          final lng = payload['lng'];
          final timestamp = payload['timestamp'];
          // Update UI with live driver position
        },
      )
      .onBroadcast(
        event: 'trip.started',
        callback: (payload) {
          // Trip has started
        },
      )
      .onBroadcast(
        event: 'trip.completed',
        callback: (payload) {
          // Trip is complete
        },
      )
      .subscribe();
  }

  void subscribeToDriverRequests(int driverId) {
    final channel = supabase.channel('driver:$driverId')
      .onBroadcast(
        event: 'trip.request',
        callback: (payload) {
          final tripId = payload['trip_id'];
          final pickup = payload['pickup'];
          // Show incoming trip request
        },
      )
      .subscribe();
  }

  void unsubscribe(String channelName) {
    supabase.removeChannel(supabase.channel(channelName));
  }
}
```

---

## Error Handling

### Standard Error Response

```json
{
  "status": "error",
  "code": 400,
  "message": "Validation failed",
  "error_code": "VALIDATION_ERROR",
  "errors": {
    "email": ["Email is required"],
    "password": ["Password must be at least 8 characters"]
  },
  "timestamp": "2026-05-04T14:30:00Z"
}
```

### Common Error Codes

| Code | HTTP | Message | Solution |
|------|------|---------|----------|
| INVALID_CREDENTIALS | 401 | Invalid email or password | Verify credentials |
| ACCOUNT_NOT_APPROVED | 403 | Your account must be approved | Wait for admin approval |
| ACCOUNT_NOT_VERIFIED | 403 | Your account must be verified | Verify email/phone |
| RIDE_NOT_FOUND | 404 | Ride not found | Check ride ID |
| TRIP_NOT_FOUND | 404 | Trip not found | Check trip ID |
| INSUFFICIENT_SEATS | 422 | Not enough seats available | Try fewer seats or different ride |
| RIDE_DEPARTED | 422 | Ride has already departed | Choose different ride |
| BOOKING_WINDOW_CLOSED | 422 | Booking window has closed | Try other rides |
| INVALID_LOCATION | 422 | Invalid pickup/dropoff location | Provide valid coordinates |
| PAYMENT_FAILED | 402 | Payment processing failed | Try again or different method |
| DRIVER_UNAVAILABLE | 503 | No drivers available | Try again in a few minutes |
| SERVER_ERROR | 500 | Internal server error | Retry or contact support |

---

## Rate Limiting

### Rate Limit Headers

All responses include:

```
X-RateLimit-Limit: 1000
X-RateLimit-Remaining: 999
X-RateLimit-Reset: 1620123456
```

### Limits by Endpoint Type

| Endpoint Type | Limit | Window |
|---------------|-------|--------|
| Authentication | 5 requests | 1 minute |
| Ride Discovery | 100 requests | 1 hour |
| Booking/Trip | 50 requests | 1 hour |
| Payment | 20 requests | 1 hour |
| Location Update | 600 requests | 1 hour |
| General API | 1000 requests | 1 hour |

---

## Data Models

### User

```json
{
  "id": 1,
  "name": "John Doe",
  "email": "john@example.com",
  "phone": "+250788123456",
  "role": "passenger|driver",
  "is_approved": true,
  "is_verified": true,
  "profile_photo": "https://cdn.rideconnect.rw/photos/user_1.jpg",
  "rating": 4.8,
  "created_at": "2025-11-15T10:30:00Z"
}
```

### Ride

```json
{
  "id": 101,
  "driver_id": 2,
  "vehicle_id": 5,
  "origin_address": "Kimihurura Roundabout, Kigali",
  "origin_lat": -1.9536,
  "origin_lng": 30.0606,
  "destination_address": "Kigali City Tower, Kigali",
  "destination_lat": -1.9441,
  "destination_lng": 30.0619,
  "departure_time": "2026-05-04T14:30:00Z",
  "arrival_time_estimated": "2026-05-04T14:42:00Z",
  "distance_km": 2.5,
  "base_fare": 1500,
  "price_per_km": 400,
  "price_per_seat": 2500,
  "available_seats": 3,
  "transport_type": "CAR|MOTORCYCLE|BUS",
  "travel_mode": "SCHEDULED|ON_DEMAND",
  "status": "DRAFT|PUBLISHED|IN_PROGRESS|COMPLETED|CANCELLED",
  "created_at": "2026-05-04T12:00:00Z"
}
```

### Booking

```json
{
  "id": 501,
  "user_id": 1,
  "ride_id": 101,
  "seats_booked": 2,
  "total_price": 5000,
  "currency": "RWF",
  "status": "PENDING|CONFIRMED|CANCELLED|COMPLETED|NO_SHOW",
  "pickup_address": "Kimihurura Roundabout, Kigali",
  "pickup_lat": -1.9536,
  "pickup_lng": 30.0606,
  "dropoff_address": "Kigali City Tower, Kigali",
  "dropoff_lat": -1.9441,
  "dropoff_lng": 30.0619,
  "special_requests": "Please wait at main entrance",
  "confirmation_code": "RIDE-101-5001",
  "created_at": "2026-05-04T12:30:00Z",
  "confirmed_at": null,
  "cancelled_at": null
}
```

### Trip

```json
{
  "id": 1001,
  "passenger_id": 1,
  "driver_id": 2,
  "booking_id": null,
  "ride_id": null,
  "pickup_location": "Kimihurura Roundabout, Kigali",
  "pickup_lat": -1.9536,
  "pickup_lng": 30.0606,
  "dropoff_location": "Kigali City Tower, Kigali",
  "dropoff_lat": -1.9441,
  "dropoff_lng": 30.0619,
  "fare": 2500,
  "actual_fare": 2500,
  "status": "PENDING|ACCEPTED|STARTED|COMPLETED|CANCELLED",
  "requested_at": "2026-05-04T14:30:00Z",
  "accepted_at": "2026-05-04T14:31:00Z",
  "started_at": "2026-05-04T14:32:00Z",
  "completed_at": "2026-05-04T14:45:00Z",
  "confirmation_code": "TRIP-2500-1001"
}
```

---

**API Version:** 2.0  
**Last Updated:** May 2026  
**Maintained By:** RideConnect Engineering Team
