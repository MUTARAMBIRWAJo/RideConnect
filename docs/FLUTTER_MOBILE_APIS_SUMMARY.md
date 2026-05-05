# 🚀 RideConnect Flutter Mobile App - All APIs Summary

**Complete API Endpoints for Mobile Development**  
**Generated:** May 2026  
**Total Endpoints:** 58+

---

## 📊 API Endpoints by Category

### 🔐 AUTHENTICATION (10 APIs)

| Use Case | Endpoint | Method | Purpose |
|----------|----------|--------|---------|
| Passenger Signup | `/auth/register` | POST | Create new passenger account |
| Driver Signup | `/auth/register/driver` | POST | Create new driver account |
| User Login | `/auth/login` | POST | Authenticate user |
| Mobile Login | `/auth/mobile/login` | POST | Mobile app authentication |
| Get Profile | `/auth/profile` | GET | Retrieve user profile |
| Update Profile | `/auth/profile` | PUT | Edit profile details |
| Validate Token | `/auth/token/validate` | GET | Check token validity |
| Logout | `/auth/logout` | POST | End user session |
| Register Device | `/devices/push-token` | POST | Register for push notifications |
| Unregister Device | `/devices/push-token/{token}` | DELETE | Remove device notification token |

---

### 👥 PASSENGER - TRIPS & RIDES (9 APIs)

| Use Case | Endpoint | Method | Purpose |
|----------|----------|--------|---------|
| Browse Rides | `/mobile/rides` | GET | See available rides to book |
| Request Trip | `/mobile/trips/request` | POST | Request private transport |
| Active Trip | `/mobile/trips/current` | GET | Get current active trip |
| Track Driver | `/mobile/trips/{id}/track` | GET | Get driver's real-time location |
| Complete Trip | `/mobile/trips/{id}/complete` | PUT | Finish trip and rate driver |
| Cancel Trip | `/mobile/trips/{id}/cancel` | PUT | Cancel active/pending trip |
| Trip History | `/passenger/rides/history` | GET | View past rides |
| Available Rides | `/passenger/rides/available` | GET | Browse all available rides |
| Passenger Trips | `/passenger/trips` | GET | Get all passenger trips |

---

### 💳 PASSENGER - BOOKINGS (6 APIs)

| Use Case | Endpoint | Method | Purpose |
|----------|----------|--------|---------|
| Book Ride | `/mobile/bookings` | POST | Reserve seat on scheduled route |
| All Bookings | `/passenger/bookings` | GET | View all bookings |
| My Bookings | `/passenger/bookings/my` | GET | View my bookings |
| Booking Details | `/passenger/bookings/{id}` | GET | Get specific booking info |
| Update Booking | `/passenger/bookings/{id}` | PUT | Modify booking details |
| Cancel Booking | `/passenger/bookings/{id}/cancel` | PUT | Cancel booking |

---

### 💰 PASSENGER - PAYMENTS (4 APIs)

| Use Case | Endpoint | Method | Purpose |
|----------|----------|--------|---------|
| Make Payment | `/passenger/payments` | POST | Pay for trip/booking |
| Payment History | `/passenger/payments/history` | GET | View all transactions |
| Finance Summary | `/finance/summary` | GET | Financial overview |
| Transactions | `/finance/transactions` | GET | Transaction details |

---

### 📊 PASSENGER - STATS & INFO (4 APIs)

| Use Case | Endpoint | Method | Purpose |
|----------|----------|--------|---------|
| Profile Stats | `/passenger/stats` | GET | Passenger statistics |
| Online Drivers | `/passenger/drivers/online` | GET | Find nearby available drivers |
| Corridors | `/passenger/public-transport/corridors` | GET | Get transport corridors |
| Routes | `/passenger/public-transport/routes` | GET | Get specific routes |

---

### 📍 DRIVER - LOCATION & STATUS (6 APIs)

| Use Case | Endpoint | Method | Purpose |
|----------|----------|--------|---------|
| Online/Offline | `/mobile/drivers/status` | POST | Toggle driver availability |
| Trip Location | `/mobile/drivers/location` | POST | Send location for active trip |
| Live Location | `/mobile/drivers/live-location` | POST | Send real-time GPS data |
| Update Location | `/driver/location` | POST | General location update |
| Get Location | `/driver/{id}/location` | GET | Get driver's location |
| Nearby Drivers | `/drivers/nearby` | GET | Find nearby drivers |

---

### 📋 DRIVER - TRIP MANAGEMENT (7 APIs)

| Use Case | Endpoint | Method | Purpose |
|----------|----------|--------|---------|
| Available Trips | `/mobile/drivers/trips` | GET | Get trip offers to accept |
| Accept Trip | `/mobile/drivers/trips/{id}/accept` | POST | Accept a trip |
| Start Trip | `/mobile/drivers/trips/{id}/start` | PUT | Mark trip started |
| Complete Trip | `/mobile/drivers/trips/{id}/complete` | PUT | Finish trip, calculate earnings |
| Cancel Trip | `/mobile/drivers/trips/{id}/cancel` | PUT | Cancel accepted trip |
| Driver Trips | `/driver/trips` | GET | Get all driver trips |
| Trip Requests | `/driver/trip-requests` | GET | Get trip requests |

---

### 👤 DRIVER - PROFILE & EARNINGS (7 APIs)

| Use Case | Endpoint | Method | Purpose |
|----------|----------|--------|---------|
| Driver Profile | `/driver/profile` | GET | View driver profile |
| Edit Profile | `/driver/profile` | PUT | Update profile info |
| Daily Earnings | `/driver/earnings` | GET | View earnings by period |
| Monthly Earnings | `/driver/earnings/monthly` | GET | Get earnings breakdown |
| Driver Stats | `/driver/stats` | GET | Performance statistics |
| Driver Bookings | `/driver/bookings` | GET | Get driver's bookings |
| Bookings Confirm | `/driver/bookings/{id}/confirm` | PUT | Confirm booking |

---

### 📄 DRIVER - DOCUMENTS & VERIFICATION (2 APIs)

| Use Case | Endpoint | Method | Purpose |
|----------|----------|--------|---------|
| Upload Documents | `/driver/documents` | POST | Upload license, ID, etc |
| View Documents | `/driver/documents` | GET | See uploaded documents |

---

### 🌐 REAL-TIME TRACKING (3 APIs + WebSocket)

| Use Case | Endpoint | Method | Purpose |
|----------|----------|--------|---------|
| Driver Location | `/mobile/tracking/driver/{driverId}` | GET | Get single driver location |
| Trip Location | `/mobile/tracking/trip/{tripId}` | GET | Get driver for specific trip |
| Nearby Drivers | `/mobile/tracking/nearby` | GET | Find nearby online drivers |
| Live Updates | `driver:{driverId}` | WS | WebSocket location stream |

---

## 🔄 Quick Flow Examples

### **🛣️ PASSENGER BOOKING A PRIVATE RIDE**

```
1. GET /mobile/rides → Browse available rides
2. POST /mobile/trips/request → Request a trip
   ✓ Returns: tripId, estimatedFare, waitTime
3. GET /mobile/trips/current → Check trip status
4. GET /mobile/trips/{id}/track (poll or WebSocket)
   ✓ Returns: driver location, ETA, vehicle info
5. PUT /mobile/trips/{id}/complete → Finish & rate
6. POST /passenger/payments → Pay for trip
```

### **🚗 DRIVER ACCEPTING A TRIP**

```
1. POST /mobile/drivers/status → Go online
2. Timer: POST /mobile/drivers/live-location (every 5-10 sec)
3. Timer: GET /mobile/drivers/trips (every 5 sec)
4. POST /mobile/drivers/trips/{id}/accept → Accept trip
5. PUT /mobile/drivers/trips/{id}/start → Pick up passenger
6. Timer: POST /mobile/drivers/location (send trip location)
7. PUT /mobile/drivers/trips/{id}/complete → Finish trip
```

### **📊 CHECK EARNINGS**

```
1. GET /driver/earnings → Daily/weekly/monthly earnings
2. GET /driver/earnings/monthly → Detailed breakdown
3. GET /driver/stats → Performance metrics
```

---

## 📱 API Usage by App Feature

### **🗺️ MAP & LOCATION**
- `/passenger/drivers/online` - Show drivers on map
- `/mobile/tracking/nearby` - Get nearby drivers
- `/mobile/trips/{id}/track` - Driver ETA on map
- WebSocket `driver:{id}` - Live position updates

### **📞 NOTIFICATIONS**
- `/devices/push-token` - Register device token
- WebSocket subscriptions - Real-time alerts

### **💳 PAYMENT**
- `/passenger/payments` - Process payment
- `/passenger/payments/history` - Show transactions

### **⭐ RATINGS & REVIEWS**
- `/mobile/trips/{id}/complete` - Submit rating (includes review parameter)

### **🔍 SEARCH & FILTER**
- `/mobile/rides` - Filter available rides
- `/passenger/public-transport/corridors` - Search corridors
- `/passenger/public-transport/routes` - Search routes

### **📈 ANALYTICS**
- `/driver/stats` - Driver performance
- `/passenger/stats` - Passenger profile stats
- `/driver/earnings/monthly` - Revenue analytics

---

## 🔑 Authentication Pattern

**All endpoints EXCEPT auth/register/login require:**

```
Header: Authorization: Bearer {access_token}
```

**Token obtained from:**
- `POST /auth/register` 
- `POST /auth/login`
- `POST /auth/mobile/login`

---

## ✅ Common Request Patterns

### **Pattern 1: Get Location Data**
```
Query Parameters:
- latitude: float (required)
- longitude: float (required)  
- radius_km: float (optional, default 5)
```

### **Pattern 2: Paginated Results**
```
Query Parameters:
- limit: int (default 10)
- offset: int (default 0)
- page: int (alternative to offset)
```

### **Pattern 3: Filter by Status**
```
Query Parameters:
- status: string (completed, pending, cancelled, etc)
```

---

## 🎯 Use Case Reference

| Feature | Primary Endpoint |
|---------|-----------------|
| **Find Ride** | GET `/mobile/rides` |
| **Request Pickup** | POST `/mobile/trips/request` |
| **Track Driver** | GET `/mobile/trips/{id}/track` |
| **Rate Driver** | PUT `/mobile/trips/{id}/complete` |
| **Go Online** | POST `/mobile/drivers/status` |
| **Get Trip Offers** | GET `/mobile/drivers/trips` |
| **Accept Trip** | POST `/mobile/drivers/trips/{id}/accept` |
| **Check Earnings** | GET `/driver/earnings` |
| **View History** | GET `/passenger/rides/history` |
| **Update Location** | POST `/mobile/drivers/live-location` |
| **Payment** | POST `/passenger/payments` |
| **Documents** | POST `/driver/documents` |

---

## 📋 Data Required for Common Actions

### **Request a Trip**
```json
{
  "pickup_location": "string",
  "pickup_lat": "number",
  "pickup_lng": "number",
  "dropoff_location": "string",
  "dropoff_lat": "number",
  "dropoff_lng": "number",
  "number_of_passengers": "int"
}
```

### **Update Driver Location**
```json
{
  "lat": "number",
  "lng": "number",
  "speed_kmh": "number",
  "heading": "number",
  "accuracy": "number"
}
```

### **Complete Trip**
```json
{
  "rating": "int (1-5)",
  "review": "string",
  "payment_method": "string",
  "final_fare": "number"
}
```

---

## ⚠️ Error Responses

### **Common Status Codes**
- **200** - Success
- **201** - Created
- **400** - Bad Request (invalid data)
- **401** - Unauthorized (no/invalid token)
- **403** - Forbidden (no permission)
- **404** - Not Found
- **422** - Validation Error
- **429** - Too Many Requests
- **500** - Server Error

### **Error Response Format**
```json
{
  "status": "error",
  "message": "Human readable message",
  "code": 400,
  "errors": {
    "field_name": ["Error detail"]
  }
}
```

---

## 🚀 Getting Started

### **1. Register**
```
POST /auth/register
→ Get: access_token
```

### **2. Set Token in Headers**
```
All requests add:
Authorization: Bearer {access_token}
```

### **3. Start Using APIs**
```
Passenger: GET /mobile/rides
Driver: POST /mobile/drivers/status
```

---

## 📚 Documentation Files

| File | Purpose |
|------|---------|
| `FLUTTER_MOBILE_APP_COMPLETE_API_GUIDE.md` | Full detailed documentation with examples |
| `FLUTTER_MOBILE_APP_API_QUICK_REFERENCE.md` | Quick lookup reference tables |
| `REALTIME_DRIVER_LOCATION_TRACKING.md` | Real-time tracking implementation |
| `MOBILE_AUTH_API.md` | Authentication details |
| `MOBILE_DRIVER_PASSENGER_APIS.md` | Driver & passenger specific APIs |

---

## 🔗 API Base URL

```
Production: https://api.rideconnect.rw/api/v1
Staging: https://staging-api.rideconnect.rw/api/v1
Development: http://localhost:8000/api/v1
```

---

**Total APIs:** 58 endpoints  
**Rate Limit:** 1,000 requests/hour  
**Response Format:** JSON  
**Auth Method:** JWT Bearer Token  
**Real-Time:** Supabase WebSocket  

**For detailed examples, see:** `FLUTTER_MOBILE_APP_COMPLETE_API_GUIDE.md`  
**For quick lookup, see:** `FLUTTER_MOBILE_APP_API_QUICK_REFERENCE.md`
