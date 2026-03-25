# RideConnect Flutter API Documentation

## Base URL
```
http://localhost:8080/api/v1
```

## Authentication
- **Method**: Bearer Token (Laravel Sanctum)
- **Header**: `Authorization: Bearer {token}`
- **All protected endpoints require valid token**

---

## 🚗 PASSENGER APP APIs

### 🔐 Authentication

#### Register Passenger
```http
POST /auth/register/passenger
Content-Type: application/json

{
  "name": "John Doe",
  "email": "john@example.com",
  "password": "password123",
  "phone": "+250788123456"
}
```

#### Login (Email/Phone)
```http
POST /auth/mobile/login
Content-Type: application/json

{
  "email": "john@example.com", // or "phone": "+250788123456"
  "password": "password123"
}
```

#### Get Profile
```http
GET /auth/profile
Authorization: Bearer {token}
```

#### Update Profile
```http
PUT /auth/profile
Authorization: Bearer {token}
Content-Type: application/json

{
  "name": "John Updated",
  "phone": "+250788123456"
}
```

#### Logout
```http
POST /auth/logout
Authorization: Bearer {token}
```

---

### 🚖 Ride Discovery & Booking

#### Get Available Rides
```http
GET /passenger/rides/available?status=ACTIVE&available_only=true&search=Kigali
Authorization: Bearer {token}
```

#### Get Ride Details
```http
GET /passenger/rides/{id}
Authorization: Bearer {token}
```

#### Book a Seat on Existing Ride
```http
POST /passenger/rides
Authorization: Bearer {token}
Content-Type: application/json

{
  "ride_id": 123,
  "seats": 1,
  "pickup_address": "Kimihurura Roundabout",
  "dropoff_address": "Kigali City Tower"
}
```

#### Request Custom Ride (Direct to Driver)
```http
POST /passenger/ride-requests
Authorization: Bearer {token}
Content-Type: application/json

{
  "driver_id": 456,
  "pickup_location": "Kimihurura Roundabout",
  "pickup_lat": -1.9536,
  "pickup_lng": 30.0606,
  "dropoff_location": "Kigali City Tower",
  "dropoff_lat": -1.9441,
  "dropoff_lng": 30.0619,
  "fare": 2500
}
```

#### Cancel Ride
```http
PUT /passenger/rides/{id}/cancel
Authorization: Bearer {token}
```

---

### 📋 Booking Management

#### Get My Bookings
```http
GET /passenger/bookings/my?status=CONFIRMED
Authorization: Bearer {token}
```

#### Get Booking Details
```http
GET /passenger/bookings/{id}
Authorization: Bearer {token}
```

#### Cancel Booking
```http
PUT /passenger/bookings/{id}/cancel
Authorization: Bearer {token}
```

---

### 🚶 Trip Management

#### Get My Trips
```http
GET /passenger/trips?status=COMPLETED
Authorization: Bearer {token}
```

#### Get Trip Details
```http
GET /passenger/trips/{id}
Authorization: Bearer {token}
```

#### Cancel Trip
```http
PUT /passenger/trips/{id}/cancel
Authorization: Bearer {token}
```

---

### 💳 Payment

#### Create Payment
```http
POST /passenger/payments
Authorization: Bearer {token}
Content-Type: application/json

{
  "booking_id": 789,
  "amount": 2500,
  "payment_method": "mobile_money" // or "card"
}
```

#### Get Payment History
```http
GET /passenger/payments/history
Authorization: Bearer {token}
```

---

### 📊 Passenger Stats

#### Get Passenger Statistics
```http
GET /passenger/stats
Authorization: Bearer {token}
```

---

### 🔔 Notifications

#### Get Notifications
```http
GET /notifications
Authorization: Bearer {token}
```

#### Get Unread Count
```http
GET /notifications/unread-count
Authorization: Bearer {token}
```

#### Mark as Read
```http
PUT /notifications/{id}/read
Authorization: Bearer {token}
```

---

### 📍 Location & Drivers

#### Get Online Drivers
```http
GET /passenger/drivers/online
Authorization: Bearer {token}
```

---

## 🚗 DRIVER APP APIs

### 🔐 Authentication

#### Register Driver
```http
POST /auth/register/driver
Content-Type: application/json

{
  "name": "Driver Name",
  "email": "driver@example.com",
  "password": "password123",
  "phone": "+250788123456"
}
```

#### Login
```http
POST /auth/mobile/login
Content-Type: application/json

{
  "email": "driver@example.com",
  "password": "password123"
}
```

#### Get Profile
```http
GET /driver/profile
Authorization: Bearer {token}
```

#### Update Profile
```http
PUT /driver/profile
Authorization: Bearer {token}
Content-Type: application/json

{
  "name": "Driver Updated",
  "phone": "+250788123456"
}
```

---

### 📊 Driver Stats & Earnings

#### Get Driver Statistics
```http
GET /driver/stats
Authorization: Bearer {token}
```

#### Get Earnings
```http
GET /driver/earnings
Authorization: Bearer {token}
```

#### Get Monthly Earnings
```http
GET /driver/earnings/monthly?month=2024-03
Authorization: Bearer {token}
```

---

### 🚖 Ride Management

#### Create New Ride
```http
POST /driver/rides
Authorization: Bearer {token}
Content-Type: application/json

{
  "origin_address": "Kimihurura Roundabout",
  "origin_lat": -1.9536,
  "origin_lng": 30.0606,
  "destination_address": "Kigali City Tower",
  "destination_lat": -1.9441,
  "destination_lng": 30.0619,
  "departure_time": "2024-03-25T14:30:00Z",
  "arrival_time_estimated": "2024-03-25T15:00:00Z",
  "available_seats": 4,
  "price_per_seat": 2500,
  "currency": "RWF",
  "vehicle_id": 123,
  "description": "Comfortable ride, AC available"
}
```

#### Get My Rides
```http
GET /driver/rides?status=ACTIVE
Authorization: Bearer {token}
```

#### Update Ride
```http
PUT /driver/rides/{id}
Authorization: Bearer {token}
Content-Type: application/json

{
  "available_seats": 3,
  "price_per_seat": 2600
}
```

#### Delete Ride
```http
DELETE /driver/rides/{id}
Authorization: Bearer {token}
```

---

### 📋 Booking Management

#### Get Bookings for My Rides
```http
GET /driver/bookings?status=PENDING
Authorization: Bearer {token}
```

#### Confirm Booking
```http
PUT /driver/bookings/{id}/confirm
Authorization: Bearer {token}
```

#### Cancel Booking
```http
PUT /driver/bookings/{id}/cancel
Authorization: Bearer {token}
```

---

### 🚶 Trip Requests & Management

#### Get Trip Requests
```http
GET /driver/trip-requests?status=PENDING
Authorization: Bearer {token}
```

#### Accept Trip Request
```http
PUT /driver/trip-requests/{id}/accept
Authorization: Bearer {token}
```

#### Reject Trip Request
```http
PUT /driver/trip-requests/{id}/reject
Authorization: Bearer {token}
```

#### Complete Trip Request
```http
PUT /driver/trip-requests/{id}/complete
Authorization: Bearer {token}
```

#### Get My Trips
```http
GET /driver/trips?status=ACTIVE
Authorization: Bearer {token}
```

#### Start Trip
```http
PUT /driver/trips/{id}/start
Authorization: Bearer {token}
```

#### Cancel Trip
```http
PUT /driver/trips/{id}/cancel
Authorization: Bearer {token}
```

---

### 📱 Driver Status & Location

#### Update Availability Status
```http
PUT /driver/status
Authorization: Bearer {token}
Content-Type: application/json

{
  "status": "online", // "online", "offline", "busy"
  "latitude": -1.9536,
  "longitude": 30.0606
}
```

#### Update Location
```http
POST /driver/location
Authorization: Bearer {token}
Content-Type: application/json

{
  "latitude": -1.9536,
  "longitude": 30.0606
}
```

---

### 📄 Document Management

#### Upload Document
```http
POST /driver/documents
Authorization: Bearer {token}
Content-Type: multipart/form-data

{
  "document_type": "license", // "license", "insurance", "registration"
  "document_file": [file],
  "expiry_date": "2025-03-25"
}
```

#### Get Documents
```http
GET /driver/documents
Authorization: Bearer {token}
```

---

## 🔔 SHARED APIS (Both Apps)

### Device Token Registration

#### Register Push Token
```http
POST /devices/push-token
Authorization: Bearer {token}
Content-Type: application/json

{
  "token": "fcm_token_here",
  "platform": "android" // or "ios"
}
```

#### Remove Push Token
```http
DELETE /devices/push-token/{token}
Authorization: Bearer {token}
```

---

### Notifications

#### Get Notifications
```http
GET /notifications
Authorization: Bearer {token}
```

#### Mark All as Read
```http
PUT /notifications/read-all
Authorization: Bearer {token}
```

#### Clear Actioned Notifications
```http
DELETE /notifications/clear-actioned
Authorization: Bearer {token}
```

---

## 🤖 AI INTEGRATION APIS

### Driver Matching
```http
POST /ai/match-driver
Authorization: Bearer {token}
Content-Type: application/json

{
  "pickup_lat": -1.9536,
  "pickup_lng": 30.0606,
  "dropoff_lat": -1.9441,
  "dropoff_lng": 30.0619,
  "passenger_count": 1
}
```

### ETA Prediction
```http
POST /ai/predict-eta
Authorization: Bearer {token}
Content-Type: application/json

{
  "origin_lat": -1.9536,
  "origin_lng": 30.0606,
  "destination_lat": -1.9441,
  "destination_lng": 30.0619
}
```

### Demand Prediction
```http
POST /ai/predict-demand
Authorization: Bearer {token}
Content-Type: application/json

{
  "area_lat": -1.9536,
  "area_lng": 30.0606,
  "time_window": "2h"
}
```

---

## 📱 RESPONSE FORMATS

### Success Response
```json
{
  "success": true,
  "data": {
    // Response data here
  },
  "message": "Operation successful"
}
```

### Error Response
```json
{
  "success": false,
  "message": "Error description",
  "error": {
    "code": "ERROR_CODE",
    "description": "Detailed error description"
  }
}
```

### Validation Error Response
```json
{
  "success": false,
  "message": "Validation failed",
  "errors": {
    "field_name": ["Error message for this field"]
  }
}
```

---

## 🔒 HTTP STATUS CODES

- `200` - Success
- `201` - Created (for registration/creation)
- `400` - Bad Request (validation errors)
- `401` - Unauthorized (invalid credentials/token)
- `403` - Forbidden (account not approved, wrong role)
- `404` - Not Found
- `422` - Unprocessable Entity (validation failed)
- `500` - Internal Server Error

---

## 🚀 RIDE REQUEST FLOW

### Passenger Flow:
1. **Login** → Get token
2. **Discover Rides** → `GET /passenger/rides/available`
3. **Book Ride** → `POST /passenger/rides` OR **Request Custom** → `POST /passenger/ride-requests`
4. **Track Booking** → `GET /passenger/bookings/my`
5. **Make Payment** → `POST /passenger/payments`
6. **Track Trip** → `GET /passenger/trips/{id}`

### Driver Flow:
1. **Login** → Get token
2. **Go Online** → `PUT /driver/status` (status: "online")
3. **Create Ride** → `POST /driver/rides`
4. **Get Bookings** → `GET /driver/bookings`
5. **Confirm Bookings** → `PUT /driver/bookings/{id}/confirm`
6. **Handle Trip Requests** → `GET /driver/trip-requests`
7. **Update Location** → `POST /driver/location`
8. **Complete Trip** → `PUT /driver/trip-requests/{id}/complete`

---

## 📋 IMPORTANT NOTES

1. **Authentication Required**: All endpoints except `/auth/register/*` and `/auth/mobile/login` require Bearer token
2. **Account Approval**: New registrations require admin approval before login
3. **Role-Based Access**: Passengers can only access passenger endpoints, drivers only driver endpoints
4. **Real-Time Updates**: Use WebSocket or push notifications for real-time ride status updates
5. **Location Services**: Drivers should update location every 30 seconds when online
6. **Error Handling**: Always check the `success` field in responses
7. **Pagination**: List endpoints support `?page=1&limit=20` parameters
