# RideConnect - Smart Public Bus Trip Request & Matching Flow
## Complete Implementation Guide

**Implementation Date:** June 6, 2026  
**Status:** Ready for Testing  
**Compatibility:** Laravel 12, PostgreSQL (Supabase), Sanctum Auth, Render Deployment

---

## 🎯 Business Flow Overview

The smart public bus matching system allows passengers to request bus trips with **location names only** (no manual coordinates). The backend automatically:

1. **Geocodes** pickup/dropoff location names → coordinates
2. **Finds active buses** on the selected corridor
3. **Calculates distance** to nearest bus (Haversine formula)
4. **Estimates ETA** based on bus speed and distance
5. **Gets route details** using Google Directions API
6. **Calculates fare** based on distance and corridor
7. **Creates trip request** and returns comprehensive match data

---

## 📋 What Was Implemented

### 1. Database Migration
**File:** `database/migrations/2026_06_06_000002_create_trip_requests_table.php`

Creates `trip_requests` table with:
- Passenger & corridor references
- Pickup/dropoff locations with coordinates
- Matched driver & vehicle references
- Distance, ETA, route details, fare
- Status tracking (PENDING_MATCH, BUS_ASSIGNED, IN_TRANSIT, COMPLETED, CANCELLED)

**Indexes:** passenger_id, corridor_id, status, created_at, compound (corridor_id, status)

### 2. TripRequest Model
**File:** `app/Models/TripRequest.php`

```php
class TripRequest extends Model {
    // Relationships
    public function passenger(): BelongsTo { }  // User
    public function corridor(): BelongsTo { }   // TransportCorridor
    public function driver(): BelongsTo { }     // Driver
    public function vehicle(): BelongsTo { }    // Vehicle
    
    // Helpers
    public function isPendingMatch(): bool { }
    public function isAssigned(): bool { }
    public function isCompleted(): bool { }
    public function isCancelled(): bool { }
}
```

### 3. PublicBusMatchingService (Core Business Logic)
**File:** `app/Services/PublicBusMatchingService.php`

**Key Methods:**

#### `requestTrip(User $passenger, array $data): array`
Main entry point for smart matching flow.

```php
// Input
$data = [
    'corridor_id' => 4,
    'pickup_location' => 'Kimironko Market',
    'dropoff_location' => 'Nyabugogo Bus Park'
];

// Output
[
    'trip_request_id' => 123,
    'corridor' => [...],
    'pickup' => ['name', 'latitude', 'longitude'],
    'dropoff' => ['name', 'latitude', 'longitude'],
    'matched_bus' => ['vehicle_id', 'plate_number', 'capacity', 'available_seats'],
    'driver' => ['id', 'name'],
    'distance_to_bus_km' => 0.8,
    'bus_eta_minutes' => 4,
    'trip_distance_km' => 8.5,
    'trip_duration_minutes' => 22,
    'estimated_fare' => 650,
    'currency' => 'RWF',
    'status' => 'PENDING_MATCH'
]
```

**Implementation Steps:**
1. Load corridor with validation
2. Geocode pickup location → {lat, lng} using Google Geocoding API
3. Geocode dropoff location → {lat, lng} using Google Geocoding API
4. Query active buses on corridor
5. Calculate distance to each bus (Haversine formula)
6. Select nearest bus
7. Calculate ETA = distance / speed × 60
8. Get route details (distance, duration) from Google Directions API
9. Calculate fare using FareCalculatorService
10. Create TripRequest record with PENDING_MATCH status
11. Return formatted response

#### `getRequest(TripRequest $tripRequest): array`
Retrieve trip request with current status.

#### Private Helper Methods

**`calculateDistance(lat1, lng1, lat2, lng2): float`**
- Haversine formula for geographic distance
- Returns distance in kilometers
- Used for: bus proximity calculation

**`findNearestBus(activeBuses, pickupLat, pickupLng): array`**
- Iterates through active buses
- Calculates distance to each
- Returns nearest with distance data

**`calculateEta(distanceKm, speedKmh): int`**
- ETA = (distance / speed) × 60
- Default bus speed: 40 km/h
- Returns minutes (integer)

**`getRouteDetails(pickupLat, pickupLng, dropoffLat, dropoffLng): array`**
- Calls Google Directions API
- Fallback to Haversine if API fails
- Returns: {distance_km, duration_minutes}

**`getAvailableSeats(vehicleId): int`**
- Queries BusRouteAssignment
- Counts current passenger boardings
- Returns: totalSeats - bookedSeats

### 4. Form Request Validation
**File:** `app/Http/Requests/Passenger/CreatePublicBusTripRequest.php`

Rules:
- `corridor_id`: required, exists in transport_corridors
- `pickup_location`: required, string, min:3, max:255
- `dropoff_location`: required, string, min:3, max:255

Only passengers (role=PASSENGER) can authorize.

### 5. API Resource
**File:** `app/Http/Resources/Passenger/TripRequestResource.php`

Formats TripRequest for API responses with:
- Corridor data
- Pickup/dropoff with coordinates
- Matched bus details
- Driver info
- Distance, ETA, fare, status

### 6. Updated PassengerPublicBusController
**File:** `app/Http/Controllers/Api/PassengerPublicBusController.php`

**New Methods:**

#### `requestTrip(Request $request): JsonResponse`
```
POST /api/v1/passenger/public-bus/request
```

- Validates passenger authorization & approval
- Calls PublicBusMatchingService::requestTrip()
- Returns 201 Created with match data
- Returns 422 Unprocessable Entity on matching failure

#### `showRequest(Request $request, string $id): JsonResponse`
```
GET /api/v1/passenger/public-bus/requests/{id}
```

- Loads trip request (owner-verified)
- Returns current status and all details
- Returns 404 if not found or unauthorized

### 7. Route Registration
**File:** `routes/api.php`

```php
Route::prefix('passenger/public-bus')->middleware(['auth:sanctum'])->group(function () {
    // Corridor listing
    Route::get('/corridors', 'corridors');
    Route::get('/corridors/{corridor}/stops', 'stops');
    Route::get('/corridors/{corridor}/active-buses', 'activeBuses');

    // Smart matching
    Route::post('/request', 'requestTrip')->name('passenger.public-bus.request');
    Route::get('/requests/{id}', 'showRequest')->name('passenger.public-bus.show-request');

    // Existing booking
    Route::post('/book-seat', 'bookSeat');
    Route::get('/trips/current', 'currentTrip');
    Route::get('/tickets/{ticket}', 'ticket');
});
```

### 8. Feature Tests
**File:** `tests/Feature/PublicBus/PassengerPublicBusMatchingTest.php`

Tests include:
- ✅ Passenger can request trip with location names
- ✅ Automatic geocoding and bus matching
- ✅ Trip request created with PENDING_MATCH status
- ✅ Validation: invalid corridor ID
- ✅ Authorization: unapproved passengers cannot request
- ✅ Authorization: only passengers can request
- ✅ Passenger can view their trip request
- ✅ Passenger cannot view others' requests
- ✅ Response includes correct status
- ✅ Response includes estimated fare

### 9. Postman Collection
**File:** `POSTMAN_PUBLIC_BUS_MATCHING.json`

Ready-to-import examples for:
1. Get Available Corridors
2. Get Corridor Stops
3. Request Public Bus Trip (with success/error examples)
4. Get Trip Request Status

---

## 🔧 Configuration

### Environment Variables
Add to `.env`:

```env
GOOGLE_MAPS_API_KEY=your_api_key_here
```

The service will:
- Use API key if set (for Geocoding & Directions APIs)
- Fall back to Haversine calculation if key missing or API fails
- Log warnings for missing configuration

### Fare Configuration
Fare calculation uses (in order):
1. **Rwanda Tariffs Table** - if `rura_tariffs` exists with base_fare & price_per_km
2. **Fallback Hardcoded** - Bus: base=300 RWF, per_km=80 RWF

---

## 📊 Data Flow Diagram

```
Passenger Mobile App
    ↓
GET /corridors → List available routes
    ↓
SELECT corridor_id
    ↓
ENTER pickup_location (name only, e.g., "Kimironko Market")
ENTER dropoff_location (name only, e.g., "Nyabugogo Bus Park")
    ↓
POST /request {corridor_id, pickup_location, dropoff_location}
    ↓
[Backend: PublicBusMatchingService]
    ├─ Geocode pickup → {lat: -1.9499, lng: 30.1265}
    ├─ Geocode dropoff → {lat: -1.9398, lng: 30.0891}
    ├─ Find active buses on corridor
    ├─ Calculate distance to nearest bus (Haversine)
    ├─ Calculate ETA (distance / speed × 60)
    ├─ Get route details (Google Directions API)
    ├─ Calculate fare (FareCalculatorService)
    ├─ Create TripRequest record
    └─ Return match data
    ↓
Response 201 Created
{
  success: true,
  data: {
    trip_request_id: 123,
    corridor: {...},
    matched_bus: {vehicle_id, plate_number, available_seats},
    driver: {id, name},
    distance_to_bus_km: 0.8,
    bus_eta_minutes: 4,
    trip_distance_km: 8.5,
    estimated_fare: 650,
    status: "PENDING_MATCH"
  }
}
    ↓
Display match result to passenger
    ↓
GET /requests/{id} → Check status updates (polls or websockets)
```

---

## 🧪 Testing Guide

### Unit Tests
```bash
php artisan test tests/Feature/PublicBus/PassengerPublicBusMatchingTest.php
```

### Manual Testing with Postman
1. Import `POSTMAN_PUBLIC_BUS_MATCHING.json`
2. Set `base_url` variable: `http://localhost:8000`
3. Set `passenger_token` variable (get from login)
4. Execute requests in order:
   - 1. Get Corridors
   - 2. Get Stops (pick corridor ID from step 1)
   - 3. Request Trip (use corridor ID & location names)
   - 4. Check Status (use trip_request_id from step 3)

### cURL Examples

**Get Corridors:**
```bash
curl -X GET \
  http://localhost:8000/api/v1/passenger/public-bus/corridors \
  -H "Authorization: Bearer {token}"
```

**Request Trip:**
```bash
curl -X POST \
  http://localhost:8000/api/v1/passenger/public-bus/request \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json" \
  -d '{
    "corridor_id": 4,
    "pickup_location": "Kimironko Market",
    "dropoff_location": "Nyabugogo Bus Park"
  }'
```

**Check Status:**
```bash
curl -X GET \
  http://localhost:8000/api/v1/passenger/public-bus/requests/123 \
  -H "Authorization: Bearer {token}"
```

---

## ✅ Success Response Structure

```json
{
  "success": true,
  "message": "Public bus match found",
  "data": {
    "trip_request_id": 123,
    "corridor": {
      "id": 4,
      "code": "105",
      "name": "REMERA BUS PARK -> NYABUGOGO BUS PARK (105)"
    },
    "pickup": {
      "name": "Kimironko Market",
      "latitude": -1.94995,
      "longitude": 30.12647
    },
    "dropoff": {
      "name": "Nyabugogo Bus Park",
      "latitude": -1.93982,
      "longitude": 30.08912
    },
    "matched_bus": {
      "vehicle_id": 10,
      "plate_number": "RAD123A",
      "capacity": 65,
      "available_seats": 18
    },
    "driver": {
      "id": 9,
      "name": "Jean Claude"
    },
    "distance_to_bus_km": 0.8,
    "bus_eta_minutes": 4,
    "trip_distance_km": 8.5,
    "trip_duration_minutes": 22,
    "estimated_fare": 650,
    "currency": "RWF",
    "status": "PENDING_MATCH"
  }
}
```

---

## ⚠️ Error Responses

### 403 Unauthorized (Non-Passenger)
```json
{
  "success": false,
  "message": "Only passengers can request public bus trips"
}
```

### 403 Forbidden (Unapproved Account)
```json
{
  "success": false,
  "message": "Your account must be approved to request a bus trip"
}
```

### 422 Unprocessable Entity (Validation Error)
```json
{
  "errors": {
    "corridor_id": ["The selected corridor does not exist"]
  }
}
```

### 422 Matching Failed (No Buses)
```json
{
  "success": false,
  "message": "No active buses found on this corridor",
  "error_code": "MATCHING_FAILED"
}
```

### 404 Not Found (Request Doesn't Exist)
```json
{
  "success": false,
  "message": "Not found"
}
```

---

## 🔐 Security Considerations

1. **Authentication:** Sanctum token required
2. **Authorization:** 
   - Only passengers (role=PASSENGER) can request trips
   - Only approved passengers can make requests
   - Passengers can only view their own trip requests
3. **Validation:**
   - Corridor must exist
   - Location names validated (min 3, max 255 chars)
4. **Rate Limiting:** Can be added via middleware
5. **API Key Security:** Google Maps API key in `.env` (not in code)

---

## 📈 Performance Considerations

1. **Geocoding:** Cached at service level (10-15 requests/second limit)
2. **Distance Calculation:** O(n) for number of active buses (typically 5-20)
3. **Database Queries:**
   - Load corridor: 1 query
   - Find active buses: 1 query + eager loading
   - Get available seats: N+1 optimization (batch query)
4. **Google APIs:** 
   - Timeout: 10 seconds
   - Fallback to Haversine if timeout
5. **Index Strategy:** trip_requests indexed on (passenger_id, corridor_id, status)

---

## 🚀 Deployment Checklist

- [ ] Run migration: `php artisan migrate`
- [ ] Set GOOGLE_MAPS_API_KEY in `.env`
- [ ] Run tests: `php artisan test`
- [ ] Clear cache: `php artisan cache:clear`
- [ ] Deploy to Render: Push to Git
- [ ] Monitor logs for geocoding errors
- [ ] Test with sample data before production

---

## 📚 Related Files

| File | Purpose |
|------|---------|
| `database/migrations/2026_06_06_000002_create_trip_requests_table.php` | Schema |
| `app/Models/TripRequest.php` | Model & relationships |
| `app/Services/PublicBusMatchingService.php` | Business logic |
| `app/Http/Controllers/Api/PassengerPublicBusController.php` | Endpoints |
| `app/Http/Requests/Passenger/CreatePublicBusTripRequest.php` | Validation |
| `app/Http/Resources/Passenger/TripRequestResource.php` | Response format |
| `routes/api.php` | Route definitions |
| `tests/Feature/PublicBus/PassengerPublicBusMatchingTest.php` | Tests |
| `POSTMAN_PUBLIC_BUS_MATCHING.json` | API examples |

---

## 🤝 Integration with Existing Systems

### Reuses Existing Services
- **GeocodingService** - Location → coordinates
- **FareCalculatorService** - Distance-based fare calculation
- **PublicBusTransportService** - Bus & corridor queries
- **MatchingSessionService** - Session management pattern

### Database Dependencies
- `transport_corridors` - Corridor definitions
- `corridor_stops` - Bus stops
- `vehicles` - Bus details (seats, plate)
- `drivers` - Driver info
- `bus_route_assignments` - Active buses on routes
- `passenger_route_boardings` - Passenger bookings

### Flutter App Integration
Client-side Flutter code:
```dart
// 1. Get corridors
GET /api/v1/passenger/public-bus/corridors

// 2. Show stop list to user
GET /api/v1/passenger/public-bus/corridors/{id}/stops

// 3. User enters location names & requests
POST /api/v1/passenger/public-bus/request
{
  "corridor_id": 4,
  "pickup_location": "Kimironko Market",
  "dropoff_location": "Nyabugogo Bus Park"
}

// 4. Display match result
Response: trip_request_id, matched_bus, driver, eta, fare

// 5. Poll status
GET /api/v1/passenger/public-bus/requests/{trip_request_id}
```

---

## 🐛 Troubleshooting

| Issue | Solution |
|-------|----------|
| No buses found | Check `bus_route_assignments` status=active |
| Geocoding fails | Verify GOOGLE_MAPS_API_KEY in .env |
| Wrong ETA | Check bus speed assumption (default 40 km/h) |
| Fare mismatch | Check rura_tariffs table or fallback rates |
| Migration fails | Ensure tables exist: corridors, vehicles, drivers |

---

## 📞 Support & Contact

For issues or questions:
1. Check test cases: `PassengerPublicBusMatchingTest.php`
2. Review logs for geocoding/API errors
3. Verify database schema: `php artisan migrate:status`
4. Check Postman collection for request format

---

**Implementation Complete** ✅  
Ready for integration testing with Flutter mobile app.
