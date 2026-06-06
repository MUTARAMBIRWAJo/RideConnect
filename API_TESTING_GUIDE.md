# RideConnect Flutter Mobile App - API Testing Guide

## 📋 Quick Summary

**Total APIs Found**: 150+ endpoints  
**Base URL**: `/api/v1/` (v2 limited set available)  
**Authentication**: Laravel Sanctum JWT Bearer Token  
**Primary Users**: Passengers, Drivers, Admins, Officers, Accountants  

---

## 📁 Generated Reference Files

### 1. **POSTMAN_API_REFERENCE.md** ⭐ START HERE
   - **Purpose**: Detailed documentation with all endpoints, parameters, and example payloads
   - **Format**: Markdown with organized sections
   - **Use**: Read through this for comprehensive understanding of each API
   - **Includes**: 
     - Complete endpoint list with HTTP methods
     - Request/response examples
     - Parameter descriptions
     - Quick testing workflows

### 2. **API_ENDPOINTS_REFERENCE.csv**
   - **Purpose**: Machine-readable format for spreadsheets
   - **Format**: CSV (importable to Excel, Google Sheets, etc.)
   - **Use**: Sort, filter, and organize endpoints by category
   - **Columns**: METHOD, ENDPOINT, CATEGORY, USER_TYPE, PARAMETERS, DESCRIPTION, AUTH_REQUIRED

### 3. **RIDECONNECT_POSTMAN_COLLECTION.json** ⭐ USE IN POSTMAN
   - **Purpose**: Ready-to-import Postman collection
   - **Format**: JSON collection file
   - **Use**: 
     1. Open Postman
     2. Click "Import"
     3. Upload this file or paste contents
     4. Set `base_url` and `token` variables
     5. Start testing immediately
   - **Includes**: 
     - All major endpoints organized by category
     - Pre-configured headers (Authorization, Content-Type)
     - Example request bodies
     - Variable placeholders for dynamic values

---

## 🚀 Quick Setup in Postman

### Step 1: Import Collection
```
Postman → Import → Select RIDECONNECT_POSTMAN_COLLECTION.json
```

### Step 2: Set Environment Variables
```json
{
  "base_url": "http://localhost:8000/api",
  "token": ""
}
```

### Step 3: Register Test Users
```
POST {{base_url}}/v1/auth/register/passenger
{
  "name": "Test Passenger",
  "email": "test.passenger@example.com",
  "password": "SecurePass123",
  "phone": "+256701234567"
}
```

### Step 4: Login & Get Token
```
POST {{base_url}}/v1/auth/mobile/login
{
  "email_or_phone": "test.passenger@example.com",
  "password": "SecurePass123"
}
```
Copy `token` from response → Paste into `{{token}}` variable

### Step 5: Start Testing
- Use provided request templates
- Modify parameters as needed
- Run collection tests

---

## 🎯 API Categories (150+ Total)

### Authentication (Public - No Auth)
- **Endpoints**: 8
- **Key**: register, login, forgot-password, reset-password, validate token

### Passenger APIs (Auth Required)
- **Profile**: 3 endpoints
- **Rides**: 6 endpoints  
- **Trips**: 7 endpoints
- **Bookings**: 6 endpoints
- **Payments**: 2 endpoints
- **Public Transport**: 8 endpoints
- **Total**: 32+ endpoints

### Driver APIs (Auth Required)
- **Profile**: 3 endpoints
- **Rides**: 4 endpoints
- **Trips**: 7 endpoints
- **Bookings**: 3 endpoints
- **Location/Earnings**: 6 endpoints
- **Public Bus Operations**: 9 endpoints
- **Total**: 32+ endpoints

### Mobile Optimized (Auth Required)
- **Passenger Mobile**: 8 endpoints
- **Driver Mobile**: 8 endpoints
- **Real-Time Tracking**: 3 endpoints
- **Total**: 19+ endpoints

### Admin & Management (Auth Required - Admin Role)
- **Dashboard**: 8 endpoints
- **User Management**: 6 endpoints
- **Finance**: 5 endpoints
- **Analytics**: 4 endpoints
- **Officer Operations**: 14 endpoints
- **Total**: 37+ endpoints

### Shared Features (Auth Required)
- **Notifications**: 7 endpoints
- **Device Tokens**: 2 endpoints
- **AI/ML**: 16 endpoints
- **Webhooks**: 2 endpoints
- **Health Check**: 4 endpoints
- **Total**: 31+ endpoints

---

## 📊 Testing Scenarios

### Scenario 1: New Passenger Journey
```
1. Register → POST /v1/auth/register/passenger
2. Login → POST /v1/auth/mobile/login
3. Get Profile → GET /v1/passenger/profile
4. View Available Rides → GET /v1/passenger/rides/available
5. Request Trip → POST /v1/passenger/trip-requests
6. Get Current Trip → GET /v1/mobile/trips/current
7. Payment → POST /v1/passenger/payments
```

### Scenario 2: Driver Accepting & Completing Ride
```
1. Register → POST /v1/auth/register/driver
2. Login → POST /v1/auth/mobile/login
3. Go Online → POST /v1/mobile/drivers/status {status: ONLINE}
4. Get Available Trips → GET /v1/mobile/drivers/trips
5. Accept Trip → POST /v1/mobile/drivers/trips/{id}/accept
6. Update Location → POST /v1/driver/location
7. Start Trip → PUT /v1/mobile/drivers/trips/{id}/start
8. Complete Trip → PUT /v1/mobile/drivers/trips/{id}/complete
```

### Scenario 3: Public Transport (Bus Booking)
```
1. Get Corridors → GET /v1/passenger/public-bus/corridors
2. Get Stops → GET /v1/passenger/public-bus/corridors/{id}/stops
3. Get Active Buses → GET /v1/passenger/public-bus/corridors/{id}/active-buses
4. Book Seat → POST /v1/passenger/public-bus/book-seat
5. Get Ticket → GET /v1/passenger/public-bus/tickets/{ticket}
```

---

## 🔐 Authentication Details

### Token Usage
```
Authorization: Bearer {token}
Content-Type: application/json
```

### Example Headers
```
Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...
Content-Type: application/json
X-Requested-With: XMLHttpRequest
```

### User Roles
- **PASSENGER**: Book rides, request trips, pay
- **DRIVER**: Accept trips, manage earnings
- **ADMIN**: Dashboard, user management, monitoring
- **OFFICER**: Public transport, ticket management
- **ACCOUNTANT**: Finance, payouts, analytics
- **SUPER_ADMIN**: Full system access

---

## 📍 Key Endpoints by Use Case

### Passenger - Find & Book Ride
```
GET    /v1/passenger/rides/available          → See rides
POST   /v1/passenger/trip-requests            → Request trip
GET    /v1/passenger/drivers/match            → Find drivers
POST   /v1/passenger/bookings                 → Book ride
POST   /v1/passenger/payments                 → Pay for ride
```

### Driver - Accept & Complete Trip
```
POST   /v1/mobile/drivers/status              → Go online
GET    /v1/mobile/drivers/trips               → Get available
POST   /v1/mobile/drivers/trips/{id}/accept   → Accept
POST   /v1/driver/location                    → Update location
PUT    /v1/mobile/drivers/trips/{id}/start    → Start trip
PUT    /v1/mobile/drivers/trips/{id}/complete → Complete
```

### Real-Time Tracking (Passenger Tracks Driver)
```
GET    /v1/mobile/tracking/driver/{driverId}  → Driver location
GET    /v1/mobile/tracking/trip/{tripId}      → Trip driver loc
GET    /v1/mobile/tracking/nearby             → Nearby drivers
```

### Notifications & Communication
```
GET    /v1/notifications                      → Get notifications
POST   /v1/devices/push-token                 → Register FCM token
PUT    /v1/notifications/{id}/read            → Mark as read
```

---

## 🛠️ Common Testing Tasks

### Test Pagination
```
GET /v1/passenger/bookings?limit=10&offset=0
GET /v1/passenger/bookings?limit=20&offset=20
```

### Test Filters
```
GET /v1/driver/trips?status=COMPLETED&limit=10
GET /v1/admin/rides?search=Kampala&status=ACTIVE
```

### Test Date Ranges
```
GET /v1/driver/earnings?date_from=2026-06-01&date_to=2026-06-30
GET /v1/finance/transactions?date_from=2026-01-01&date_to=2026-06-06
```

### Test Error Cases
```
POST /v1/passenger/rides (missing required field)
GET  /v1/passenger/trips/99999 (non-existent ID)
POST /v1/auth/login (wrong credentials)
```

---

## 📦 Request Body Examples

### Standard Ride Request
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

### Payment with Idempotency
```json
{
  "trip_id": 1,
  "amount": 5000,
  "payment_method": "CARD",
  "idempotency_key": "unique-payment-key-123"
}
```

### Driver Location Update
```json
{
  "latitude": 0.3149,
  "longitude": 32.5825,
  "accuracy": 10,
  "heading": 180,
  "speed": 50
}
```

---

## ✅ Response Format

### Success Response
```json
{
  "success": true,
  "message": "Operation completed successfully",
  "data": {
    "id": 1,
    "name": "Test",
    ...
  }
}
```

### Error Response
```json
{
  "success": false,
  "message": "Validation failed",
  "errors": {
    "email": ["Email already exists"],
    "phone": ["Phone format invalid"]
  }
}
```

### HTTP Status Codes
- **200**: OK - Successful request
- **201**: Created - Resource created
- **400**: Bad Request - Validation error
- **401**: Unauthorized - Missing/invalid token
- **403**: Forbidden - Insufficient permissions
- **404**: Not Found - Resource doesn't exist
- **422**: Unprocessable Entity - Validation failed
- **500**: Server Error - Internal error

---

## 🔗 Additional Resources

### Documentation Files
- [API_INTERNAL_DATA_CONTRACT.md](API_INTERNAL_DATA_CONTRACT.md)
- [FLUTTER_API_DOCUMENTATION.md](FLUTTER_API_DOCUMENTATION.md)
- [ML_SERVICE_QUICKSTART.md](ML_SERVICE_QUICKSTART.md)

### Implementation Guides
- [FLUTTER_SETUP_GUIDE.md](FLUTTER_SETUP_GUIDE.md)
- [FLUTTER_API_INTEGRATION_FIXES.md](FLUTTER_API_INTEGRATION_FIXES.md)

### Configuration
- [ML_SERVICE_CONFIG_EXAMPLE.php](ML_SERVICE_CONFIG_EXAMPLE.php)

---

## 🎓 How to Use This Guide

1. **Start with POSTMAN_API_REFERENCE.md** for detailed endpoint documentation
2. **Use RIDECONNECT_POSTMAN_COLLECTION.json** to import requests into Postman
3. **Reference API_ENDPOINTS_REFERENCE.csv** for quick lookups
4. **Follow testing scenarios** provided above
5. **Check response formats** for expected results

---

## 💡 Pro Tips

- Use Postman **Collections** for organized testing
- Use **Environments** to manage base_url and tokens across requests
- Use **Pre-request Scripts** to automatically refresh tokens
- Use **Test Scripts** to validate responses
- Use **Variables** for dynamic data ({{base_url}}, {{token}})
- Export/Import collections for team sharing
- Generate API documentation from Postman collection

---

## 🐛 Troubleshooting

### 401 Unauthorized
- **Issue**: Invalid or missing token
- **Fix**: Login first, copy new token, update {{token}} variable

### 422 Unprocessable Entity
- **Issue**: Missing required fields or invalid format
- **Fix**: Check request body against examples in POSTMAN_API_REFERENCE.md

### 404 Not Found
- **Issue**: Wrong endpoint URL or resource doesn't exist
- **Fix**: Verify endpoint URL matches exactly (case-sensitive)

### 500 Server Error
- **Issue**: Backend error
- **Fix**: Check application logs, verify data format

---

**Last Generated**: June 6, 2026  
**Project**: RideConnect Flutter Mobile App  
**API Version**: v1 (primary), v2 (limited)  

---

### 📞 Next Steps
1. Import RIDECONNECT_POSTMAN_COLLECTION.json into Postman
2. Set base_url = your server URL
3. Register a test passenger
4. Login and get token
5. Start testing using provided scenarios
6. Review responses and validate functionality
