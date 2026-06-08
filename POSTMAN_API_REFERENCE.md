# RideConnect Flutter Mobile App - Complete API Reference for Postman Testing

**Project**: RideConnect  
**API Version**: v1 (primary), v2 (limited)  
**Base URL**: `http://localhost:8000/api` or your server  
**Authentication**: Bearer Token (Sanctum JWT)  
**Last Updated**: 2026-06-06

---

## TABLE OF CONTENTS
1. [Authentication](#authentication)
2. [Public APIs](#public-apis-no-auth-required)
3. [Passenger APIs](#passenger-apis-auth-required)
4. [Driver APIs](#driver-apis-auth-required)
5. [Mobile Optimized APIs](#mobile-optimized-apis)
6. [Shared Endpoints](#shared-endpoints)
7. [Admin & Finance APIs](#admin--finance-apis)
8. [Testing Workflow](#quick-testing-workflow)

---

## AUTHENTICATION

### Register Passenger
**POST** `/v1/auth/register/passenger`
```json
{
  "name": "John Passenger",
  "email": "passenger@example.com",
  "password": "SecurePass123",
  "phone": "+256701234567"
}
```

### Register Driver
**POST** `/v1/auth/register/driver`
```json
{
  "name": "Jane Driver",
  "email": "driver@example.com",
  "password": "SecurePass123",
  "phone": "+256702234567"
}
```

### Login (Mobile Optimized)
**POST** `/v1/auth/mobile/login`
```json
{
  "email_or_phone": "passenger@example.com",
  "password": "SecurePass123"
}
```
**Response** → Copy `token` value for Authorization header

### Forgot Password
**POST** `/v1/auth/forgot-password`
```json
{
  "email": "passenger@example.com"
}
```

### Reset Password
**POST** `/v1/auth/reset-password`
```json
{
  "email": "passenger@example.com",
  "token": "reset_token_from_email",
  "password": "NewSecurePass123",
  "password_confirmation": "NewSecurePass123"
}
```

### Logout
**POST** `/v1/auth/logout`  
*Headers: Authorization: Bearer {token}*

### Validate Token
**GET** `/v1/auth/token/validate`  
*Headers: Authorization: Bearer {token}*

---

## PUBLIC APIs (No Auth Required)

### Calculate Pricing
**POST** `/v1/pricing/calculate`
```json
{
  "pickup_lat": 0.3149,
  "pickup_lng": 32.5825,
  "dropoff_lat": 0.3200,
  "dropoff_lng": 32.5900,
  "ride_type": "ON_DEMAND"
}
```

### Search Locations
**GET** `/v1/locations/search?query=Kampala&limit=10`

### Reverse Geocode
**GET** `/v1/locations/reverse-geocode?latitude=0.3149&longitude=32.5825`

### Geocode Address
**GET** `/v1/locations/geocode?address=Kampala%20Uganda`

---

## PASSENGER APIs (Auth Required)

### Profile Management
| Method | Endpoint | Purpose |
|--------|----------|---------|
| GET | `/v1/passenger/profile` | Get passenger profile |
| PUT | `/v1/passenger/profile` | Update passenger profile |
| GET | `/v1/passenger/stats` | Get passenger statistics |

**PUT** `/v1/passenger/profile`
```json
{
  "name": "John Passenger",
  "phone": "+256701234567",
  "profile_image": "base64_image_or_url",
  "preferred_contact": "phone"
}
```

### Available Rides (On-Demand)
| Method | Endpoint | Purpose |
|--------|----------|---------|
| GET | `/v1/passenger/rides/available` | List available rides |
| POST | `/v1/passenger/rides` | Book a ride |
| GET | `/v1/passenger/rides` | Get ride history |
| GET | `/v1/passenger/rides/{id}` | Get ride details |
| PUT | `/v1/passenger/rides/{id}/cancel` | Cancel ride |

**POST** `/v1/passenger/rides`
```json
{
  "ride_id": 123,
  "seats_count": 2,
  "special_instructions": "Please wait at the gate"
}
```

### Trip Requests
| Method | Endpoint | Purpose |
|--------|----------|---------|
| POST | `/v1/passenger/public-bus/request` | Request a public bus trip |
| GET | `/v1/passenger/trips` | Get all trips |
| GET | `/v1/passenger/trips/{id}` | Get trip details |
| POST | `/v1/passenger/trips` | Create trip |
| PUT | `/v1/passenger/trips/{id}/cancel` | Cancel trip |
| GET | `/v1/passenger/trips/{id}/status` | Check trip status |

**POST** `/v1/passenger/public-bus/request`
```json
{
  "pickup_lat": 0.3149,
  "pickup_lng": 32.5825,
  "dropoff_lat": 0.3200,
  "dropoff_lng": 32.5900,
  "trip_type": "ON_DEMAND",
  "passenger_count": 2
}
```

### Bookings (Scheduled Rides)
| Method | Endpoint | Purpose |
|--------|----------|---------|
| GET | `/v1/passenger/bookings` | List bookings |
| POST | `/v1/passenger/bookings` | Create booking |
| GET | `/v1/passenger/bookings/{id}` | Get booking details |
| PUT | `/v1/passenger/bookings/{id}` | Update booking |
| PUT | `/v1/passenger/bookings/{id}/cancel` | Cancel booking |

**POST** `/v1/passenger/bookings`
```json
{
  "ride_id": 123,
  "seats_count": 2,
  "special_instructions": "Extra luggage"
}
```

### Payments
| Method | Endpoint | Purpose |
|--------|----------|---------|
| POST | `/v1/passenger/payments` | Create payment |
| GET | `/v1/passenger/payments/history` | Payment history |

**POST** `/v1/passenger/payments`
```json
{
  "trip_id": 456,
  "amount": 5000,
  "payment_method": "CARD",
  "idempotency_key": "unique_key_123"
}
```

### Driver Matching
| Method | Endpoint | Purpose |
|--------|----------|---------|
| GET | `/v1/passenger/drivers/online` | Get online drivers |
| GET | `/v1/passenger/drivers/match` | Get matched drivers |

**GET** `/v1/passenger/drivers/match?pickup_lat=0.3149&pickup_lng=32.5825&dropoff_lat=0.3200&dropoff_lng=32.5900&ride_type=ON_DEMAND`

### Public Transport / Bus
| Method | Endpoint | Purpose |
|--------|----------|---------|
| GET | `/v1/passenger/public-transport/corridors` | List corridors |
| GET | `/v1/passenger/public-transport/routes` | List routes |
| GET | `/v1/passenger/public-transport/available` | Available buses |
| GET | `/v1/passenger/public-bus/corridors` | List bus corridors |
| GET | `/v1/passenger/public-bus/corridors/{id}/stops` | Get stops in corridor |
| GET | `/v1/passenger/public-bus/corridors/{id}/active-buses` | Active buses |
| POST | `/v1/passenger/public-bus/book-seat` | Book bus seat |
| GET | `/v1/passenger/public-bus/trips/current` | Current bus trip |
| GET | `/v1/passenger/public-bus/tickets/{ticket}` | Get ticket |

**POST** `/v1/passenger/public-bus/book-seat`
```json
{
  "bus_id": 789,
  "seat_number": "A1",
  "pickup_stop_id": 1,
  "dropoff_stop_id": 5
}
```

---

## DRIVER APIs (Auth Required)

### Profile Management
| Method | Endpoint | Purpose |
|--------|----------|---------|
| GET | `/v1/driver/profile` | Get driver profile |
| PUT | `/v1/driver/profile` | Update driver profile |
| GET | `/v1/driver/stats` | Get driver statistics |

**PUT** `/v1/driver/profile`
```json
{
  "name": "Jane Driver",
  "phone": "+256702234567",
  "vehicle_type": "CAR",
  "license_plate": "UG456XYZ",
  "bio": "Friendly and reliable driver"
}
```

### Rides Management
| Method | Endpoint | Purpose |
|--------|----------|---------|
| GET | `/v1/driver/rides` | Get driver's rides |
| POST | `/v1/driver/rides` | Create new ride |
| PUT | `/v1/driver/rides/{id}` | Update ride |
| DELETE | `/v1/driver/rides/{id}` | Delete ride |

**POST** `/v1/driver/rides`
```json
{
  "from_location": "Kampala",
  "to_location": "Jinja",
  "vehicle_type": "SEDAN",
  "capacity": 4,
  "fare_per_km": 1500,
  "depart_time": "2026-06-06 14:00:00"
}
```

### Trip Management
| Method | Endpoint | Purpose |
|--------|----------|---------|
| GET | `/v1/driver/trips` | Get driver's trips |
| PUT | `/v1/driver/trips/{id}/accept` | Accept trip |
| PUT | `/v1/driver/trips/{id}/start` | Start trip |
| PUT | `/v1/driver/trips/{id}/complete` | Complete trip |
| PUT | `/v1/driver/trips/{id}/cancel` | Cancel trip |
| PUT | `/v1/driver/trips/{id}/respond` | Respond to trip request |
| PUT | `/v1/driver/trips/{id}/status` | Update trip status |

**PUT** `/v1/driver/trips/{id}/start`
```json
{
  "current_lat": 0.3149,
  "current_lng": 32.5825
}
```

**PUT** `/v1/driver/trips/{id}/complete`
```json
{
  "final_fare": 5500,
  "notes": "Completed successfully"
}
```

### Trip Requests
| Method | Endpoint | Purpose |
|--------|----------|---------|
| GET | `/v1/driver/trip-requests` | Get trip requests |
| PUT | `/v1/driver/trip-requests/{id}/accept` | Accept request |
| PUT | `/v1/driver/trip-requests/{id}/reject` | Reject request |
| PUT | `/v1/driver/trip-requests/{id}/complete` | Complete request |

### Location Tracking
| Method | Endpoint | Purpose |
|--------|----------|---------|
| POST | `/v1/driver/location` | Update driver location |
| GET | `/v1/driver/location/{driver_id}` | Get driver location |

**POST** `/v1/driver/location`
```json
{
  "latitude": 0.3149,
  "longitude": 32.5825,
  "accuracy": 10,
  "heading": 180,
  "speed": 50
}
```

### Earnings & Documents
| Method | Endpoint | Purpose |
|--------|----------|---------|
| GET | `/v1/driver/earnings` | Get earnings |
| GET | `/v1/driver/earnings/monthly` | Get monthly earnings |
| POST | `/v1/driver/documents` | Upload document |
| GET | `/v1/driver/documents` | Get documents |

**POST** `/v1/driver/documents` (multipart/form-data)
```
document_type: LICENSE
file: <binary file>
```

### Public Bus Operations (Driver)
| Method | Endpoint | Purpose |
|--------|----------|---------|
| POST | `/v1/driver/public-bus/location` | Update bus location |
| POST | `/v1/driver/public-bus/arrived-stop` | Arrived at stop |
| POST | `/v1/driver/public-bus/passenger-boarded` | Passenger boarded |
| POST | `/v1/driver/public-bus/passenger-completed` | Passenger completed |
| POST | `/v1/driver/status` | Update driver status |
| GET | `/v1/driver/assignment/current` | Get current assignment |
| POST | `/v1/driver/assignments/{id}/accept` | Accept assignment |
| POST | `/v1/driver/assignments/{id}/reject` | Reject assignment |

---

## MOBILE OPTIMIZED APIs

### Mobile Passenger
| Method | Endpoint | Purpose |
|--------|----------|---------|
| GET | `/v1/mobile/rides` | Get rides (optimized) |
| POST | `/v1/mobile/bookings` | Create booking (optimized) |
| POST | `/v1/mobile/trips/request` | Request trip (optimized) |
| GET | `/v1/mobile/trips/current` | Get current trip |
| GET | `/v1/mobile/trips/{id}/track` | Track trip live |
| PUT | `/v1/mobile/trips/{id}/cancel` | Cancel trip |
| PUT | `/v1/mobile/trips/{id}/complete` | Complete trip |

### Mobile Driver
| Method | Endpoint | Purpose |
|--------|----------|---------|
| POST | `/v1/mobile/drivers/status` | Set driver status |
| GET | `/v1/mobile/drivers/trips` | Get available trips |
| POST | `/v1/mobile/drivers/trips/{id}/accept` | Accept trip |
| POST | `/v1/mobile/drivers/trips/{id}/reject` | Reject trip |
| POST | `/v1/mobile/drivers/location` | Update location |
| POST | `/v1/mobile/drivers/live-location` | Update live location |
| PUT | `/v1/mobile/drivers/trips/{id}/start` | Start trip |
| PUT | `/v1/mobile/drivers/trips/{id}/complete` | Complete trip |

### Real-Time Tracking
| Method | Endpoint | Purpose |
|--------|----------|---------|
| GET | `/v1/mobile/tracking/driver/{id}` | Get driver location |
| GET | `/v1/mobile/tracking/trip/{id}` | Get trip driver location |
| GET | `/v1/mobile/tracking/nearby` | Get nearby drivers |

---

## SHARED ENDPOINTS

### Notifications
| Method | Endpoint | Purpose |
|--------|----------|---------|
| GET | `/v1/notifications` | Get notifications |
| GET | `/v1/notifications/unread-count` | Count unread |
| PUT | `/v1/notifications/{id}/read` | Mark as read |
| PUT | `/v1/notifications/read-all` | Mark all as read |
| DELETE | `/v1/notifications/{id}` | Delete notification |

### Device Tokens (Push Notifications)
| Method | Endpoint | Purpose |
|--------|----------|---------|
| POST | `/v1/devices/push-token` | Register token |
| DELETE | `/v1/devices/push-token/{token}` | Unregister token |

**POST** `/v1/devices/push-token`
```json
{
  "token": "fcm_token_here",
  "device_type": "FCM",
  "device_id": "unique_device_id"
}
```

---

## ADMIN & FINANCE APIs

### Admin Dashboard
| Method | Endpoint | Purpose |
|--------|----------|---------|
| GET | `/v1/admin/dashboard` | Dashboard overview |
| GET | `/v1/admin/logs` | System logs |
| GET | `/v1/admin/rides` | Admin ride list |
| GET | `/v1/admin/users` | User management |
| PUT | `/v1/admin/riders/{id}/approve` | Approve driver/rider |

### Finance
| Method | Endpoint | Purpose |
|--------|----------|---------|
| GET | `/v1/finance/summary` | Finance summary |
| GET | `/v1/finance/transactions` | Transactions list |
| GET | `/v1/finance/export` | Export data |

### Analytics
| Method | Endpoint | Purpose |
|--------|----------|---------|
| GET | `/v1/analytics/revenue` | Revenue analytics |
| GET | `/v1/analytics/driver-performance` | Driver performance |
| GET | `/v1/analytics/commission-trend` | Commission trends |

---

## QUICK TESTING WORKFLOW

### 1. Setup Postman Environment Variables
```
{
  "base_url": "http://localhost:8000/api",
  "token": "",
  "passenger_email": "passenger@example.com",
  "driver_email": "driver@example.com",
  "password": "SecurePass123"
}
```

### 2. Test New Passenger Registration
```
POST {{base_url}}/v1/auth/register/passenger
{
  "name": "Test Passenger",
  "email": "test.passenger@example.com",
  "password": "SecurePass123",
  "phone": "+256701234567"
}
```

### 3. Login & Get Token
```
POST {{base_url}}/v1/auth/mobile/login
{
  "email_or_phone": "test.passenger@example.com",
  "password": "SecurePass123"
}
```
Copy `token` → Set as `Authorization: Bearer {token}`

### 4. Test Passenger Workflow
```
1. GET {{base_url}}/v1/passenger/profile
2. GET {{base_url}}/v1/passenger/rides/available
3. POST {{base_url}}/v1/passenger/trip-requests (with location coords)
4. GET {{base_url}}/v1/passenger/trips/current
5. PUT {{base_url}}/v1/passenger/trips/{id}/cancel
```

### 5. Test Driver Workflow
```
1. Register driver → Login → Get token
2. POST {{base_url}}/v1/driver/location (update location)
3. POST {{base_url}}/v1/mobile/drivers/status (set ONLINE)
4. GET {{base_url}}/v1/mobile/drivers/trips (get available)
5. POST {{base_url}}/v1/mobile/drivers/trips/{id}/accept
6. PUT {{base_url}}/v1/mobile/drivers/trips/{id}/start
7. PUT {{base_url}}/v1/mobile/drivers/trips/{id}/complete
```

### 6. Import into Postman
Use the **POSTMAN_API_COLLECTION.json** file (if available) or manually create requests from this reference.

---

## ERROR RESPONSES

Standard error format:
```json
{
  "success": false,
  "message": "Error description",
  "errors": {
    "field_name": ["validation error message"]
  }
}
```

### Common Status Codes
- **200** - OK
- **201** - Created
- **400** - Bad Request (validation errors)
- **401** - Unauthorized (missing/invalid token)
- **403** - Forbidden (insufficient permissions)
- **404** - Not Found
- **422** - Unprocessable Entity (validation failed)
- **500** - Internal Server Error

---

## NOTES FOR TESTING

1. **Authentication**: Save token from login response as Bearer token
2. **Idempotency**: Use `idempotency_key` for payment endpoints to prevent duplicates
3. **Real-Time**: Use polling for location tracking or implement WebSocket connection
4. **File Uploads**: Use multipart/form-data for document uploads
5. **Timestamps**: Use ISO 8601 format: `2026-06-06T14:00:00Z`
6. **Pagination**: Use `limit` and `offset` query parameters
7. **Coordinates**: Use decimal degrees (e.g., 0.3149, 32.5825)

---

**For detailed implementation, see**: [app/Http/Controllers/Api/](app/Http/Controllers/Api/)

Generated: 2026-06-06
