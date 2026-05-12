# Internal Data APIs - Contract Reference

## Endpoints Overview

All internal endpoints require `Authorization: Bearer {token}` header and are scoped to `/api/v1/internal/`.

### Summary Table

| Endpoint | Method | Purpose | Auth |
|----------|--------|---------|------|
| `/driver/{id}/behavior` | GET | Get driver's latest behavior snapshot | ✅ |
| `/passenger/{id}/behavior` | GET | Get passenger's latest behavior snapshot | ✅ |
| `/route-state` | GET | Get route conditions (by trip or coordinates) | ✅ |
| `/weather` | GET | Get weather conditions (by trip or coordinates) | ✅ |

---

## 1. GET /api/v1/internal/driver/{id}/behavior

### Purpose
Retrieve the latest driver behavior snapshot including scores and metrics.

### Parameters
- `{id}` (path, required, integer): Driver ID

### Response

**Success (200):**
```json
{
  "success": true,
  "data": {
    "id": 42,
    "driver_id": 15,
    "trip_id": 1023,
    "rating": 4.5,
    "acceptance_rate": 0.92,
    "cancellation_rate": 0.08,
    "on_time_rate": 0.87,
    "behavior_score": 0.8234,
    "notes": "Snapshot created at trip start",
    "reviewed_at": "2025-05-06T10:30:00Z",
    "created_at": "2025-05-06T10:30:00Z",
    "updated_at": "2025-05-06T10:30:00Z"
  }
}
```

**Not Found (404):**
```json
{
  "success": false,
  "message": "Driver behavior not found"
}
```

### Field Explanations
- `driver_id`: The driver this snapshot belongs to
- `trip_id`: The trip where this snapshot was captured
- `rating`: Star rating 1-5 (from drivers table)
- `acceptance_rate`: % of offered trips accepted (0-1)
- `cancellation_rate`: % of trips cancelled (0-1)
- `on_time_rate`: % of pickups on-time (0-1)
- `behavior_score`: Composite 0-1 score (higher is better)
- `reviewed_at`: When this snapshot was reviewed

### Example Usage

```bash
curl -X GET \
  "http://api.local/v1/internal/driver/15/behavior" \
  -H "Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGc..."
```

```php
$response = Http::withToken($token)->get('/api/v1/internal/driver/15/behavior');
$behavior = $response->json('data');
echo $behavior['behavior_score']; // 0.8234
```

---

## 2. GET /api/v1/internal/passenger/{id}/behavior

### Purpose
Retrieve the latest passenger behavior snapshot including reliability metrics.

### Parameters
- `{id}` (path, required, integer): Passenger ID (from mobile_users table)

### Response

**Success (200):**
```json
{
  "success": true,
  "data": {
    "id": 28,
    "passenger_id": 67,
    "trip_id": 1023,
    "reliability_score": 0.85,
    "cancellation_rate": 0.12,
    "no_show_rate": 0.02,
    "total_trips": 45,
    "notes": "Passenger reliability snapshot captured at trip start",
    "created_at": "2025-05-06T10:30:00Z",
    "updated_at": "2025-05-06T10:30:00Z"
  }
}
```

**Not Found (404):**
```json
{
  "success": false,
  "message": "Passenger behavior not found"
}
```

### Field Explanations
- `passenger_id`: The passenger this snapshot belongs to
- `trip_id`: The trip where this snapshot was captured
- `reliability_score`: Composite 0-1 score (higher is better)
- `cancellation_rate`: % of bookings cancelled (0-1)
- `no_show_rate`: % of trips where passenger didn't show (0-1)
- `total_trips`: Total lifetime trips (context)

### Example Usage

```bash
curl -X GET \
  "http://api.local/v1/internal/passenger/67/behavior" \
  -H "Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGc..."
```

```php
$response = Http::withToken($token)->get('/api/v1/internal/passenger/67/behavior');
$reliability = $response->json('data.reliability_score'); // 0.85
```

---

## 3. GET /api/v1/internal/route-state

### Purpose
Get route conditions and state. Can query by existing trip or by coordinates.

### Query Parameters
**Option A - By Trip ID:**
- `trip_id` (integer, optional): Get route state for existing trip

**Option B - By Coordinates:**
- `pickup_lat` (float, required if no trip_id): Pickup latitude (-90 to 90)
- `pickup_lng` (float, required if no trip_id): Pickup longitude (-180 to 180)
- `dropoff_lat` (float, required if no trip_id): Dropoff latitude (-90 to 90)
- `dropoff_lng` (float, required if no trip_id): Dropoff longitude (-180 to 180)

### Response

**Success (200):**
```json
{
  "success": true,
  "data": {
    "id": 88,
    "trip_id": 1023,
    "pickup_lat": -1.9450,
    "pickup_lng": 30.0610,
    "dropoff_lat": -1.9500,
    "dropoff_lng": 30.0700,
    "route_name": "CAR_SNAPSHOT",
    "distance_km": 0.678,
    "estimated_duration_min": 8,
    "traffic_level": 2,
    "congestion_index": 0.25,
    "route_geometry": null,
    "created_at": "2025-05-06T10:30:00Z",
    "updated_at": "2025-05-06T10:30:00Z"
  }
}
```

**Not Found (404):**
```json
{
  "success": false,
  "message": "Route state not found"
}
```

**Validation Error (422):**
```json
{
  "success": false,
  "message": "A trip_id or full pickup/dropoff coordinates are required to resolve route state.",
  "errors": {
    "trip_id": ["A trip_id or full pickup/dropoff coordinates are required..."]
  }
}
```

### Field Explanations
- `distance_km`: Calculated Haversine distance (rounded to 3 decimals)
- `estimated_duration_min`: ETA in minutes (based on distance + traffic)
- `traffic_level`: 1=clear, 2=light, 3=moderate, 4=heavy, 5=severe congestion
- `congestion_index`: Normalized 0-1 (0=clear, 1=totally congested)
- `route_name`: Descriptor (e.g., "CAR_SNAPSHOT", "MOTORCYCLE_SNAPSHOT")
- `route_geometry`: GeoJSON geometry (currently null)

### Example Usage

```bash
# By trip ID
curl -X GET \
  "http://api.local/v1/internal/route-state?trip_id=1023" \
  -H "Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGc..."

# By coordinates
curl -X GET \
  "http://api.local/v1/internal/route-state?pickup_lat=-1.945&pickup_lng=30.061&dropoff_lat=-1.950&dropoff_lng=30.070" \
  -H "Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGc..."
```

```php
// By trip
$response = Http::withToken($token)->get('/api/v1/internal/route-state', [
    'trip_id' => 1023
]);

// By coordinates
$response = Http::withToken($token)->get('/api/v1/internal/route-state', [
    'pickup_lat' => -1.945,
    'pickup_lng' => 30.061,
    'dropoff_lat' => -1.950,
    'dropoff_lng' => 30.070
]);

$distance = $response->json('data.distance_km'); // 0.678
```

---

## 4. GET /api/v1/internal/weather

### Purpose
Get weather conditions. Can query by existing trip or by coordinates.

### Query Parameters
**Option A - By Trip ID:**
- `trip_id` (integer, optional): Get weather for existing trip location

**Option B - By Coordinates:**
- `lat` (float, required if no trip_id): Latitude (-90 to 90)
- `lng` (float, required if no trip_id): Longitude (-180 to 180)

### Response

**Success (200):**
```json
{
  "success": true,
  "data": {
    "id": 15,
    "trip_id": 1023,
    "location_lat": -1.9450,
    "location_lng": 30.0610,
    "condition": "clear",
    "temperature_celsius": 24.5,
    "wind_speed_kmh": 8.5,
    "precipitation_mm": 0.0,
    "weather_factor": 1.0,
    "description": "Clear conditions, optimal travel",
    "recorded_at": "2025-05-06T10:30:00Z",
    "created_at": "2025-05-06T10:30:00Z",
    "updated_at": "2025-05-06T10:30:00Z"
  }
}
```

**Not Found (404):**
```json
{
  "success": false,
  "message": "Weather condition not found"
}
```

**Validation Error (422):**
```json
{
  "success": false,
  "message": "A trip_id or lat/lng coordinates are required to resolve weather.",
  "errors": {
    "lat": ["A trip_id or lat/lng coordinates are required..."]
  }
}
```

### Field Explanations
- `condition`: Weather type (clear, rain, fog, cloudy, etc.)
- `temperature_celsius`: Temperature in Celsius (nullable for mock data)
- `wind_speed_kmh`: Wind speed in km/h
- `precipitation_mm`: Rainfall in millimeters
- `weather_factor`: Multiplier for matching (1.0=clear, <1.0=adverse)
- `description`: Human-readable weather description
- `recorded_at`: Timestamp of weather recording

### Example Usage

```bash
# By trip ID
curl -X GET \
  "http://api.local/v1/internal/weather?trip_id=1023" \
  -H "Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGc..."

# By coordinates
curl -X GET \
  "http://api.local/v1/internal/weather?lat=-1.945&lng=30.061" \
  -H "Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGc..."
```

```php
// By trip
$response = Http::withToken($token)->get('/api/v1/internal/weather', [
    'trip_id' => 1023
]);

// By coordinates
$response = Http::withToken($token)->get('/api/v1/internal/weather', [
    'lat' => -1.945,
    'lng' => 30.061
]);

$factor = $response->json('data.weather_factor'); // 1.0
```

---

## Error Handling

### Standard Error Response
```json
{
  "success": false,
  "message": "Error description",
  "errors": {
    "field_name": ["Validation error details"]
  }
}
```

### Common HTTP Status Codes
- `200`: Success
- `401`: Unauthorized (missing/invalid token)
- `404`: Resource not found
- `422`: Validation error (bad parameters)
- `500`: Server error

---

## Data Format Notes

### Coordinate Format
- Latitude: -90 to 90 (negative = South)
- Longitude: -180 to 180 (negative = West)
- Rwanda: ~-1.0 to -2.9 latitude, ~28.8 to 30.9 longitude

### Decimal Precision
- Coordinates: 7 decimals (±1.1cm accuracy)
- Distances: 3 decimals (±1m accuracy)
- Scores: 4 decimals (0.0001 precision)

### Time Format
- ISO 8601: `2025-05-06T10:30:00Z`
- Always UTC

---

## Rate Limiting & Caching

Currently **no rate limiting** on internal APIs.

**Recommended for production:**
- Cache driver/passenger behavior snapshots (TTL: 1 hour)
- Cache weather data (TTL: 15 minutes)
- Cache route state (TTL: 5 minutes)

---

## Integration Guide

### Step 1: Authenticate
```php
$token = auth()->user()->createToken('api')->plainTextToken;
```

### Step 2: Get Driver Behavior
```php
$response = Http::withToken($token)
    ->get("/api/v1/internal/driver/{$driverId}/behavior");

if ($response->successful()) {
    $behavior = $response->json('data');
    $driverScore = $behavior['behavior_score'] ?? 0.6;
}
```

### Step 3: Get Weather & Route
```php
$weather = Http::withToken($token)
    ->get("/api/v1/internal/weather?trip_id={$tripId}")
    ->json('data');

$route = Http::withToken($token)
    ->get("/api/v1/internal/route-state?trip_id={$tripId}")
    ->json('data');
```

### Step 4: Use in Matching
```php
$engine = app(MatchingEngine::class);
$scores = $engine->calculateMatchingScore($trip, $availableDrivers);
$bestDriver = array_key_first($scores);
```

---

## Fallback Behavior

If data is not available, the system returns sensible defaults:

| Data | Fallback |
|------|----------|
| No driver behavior | `behavior_score = 0.6` |
| No passenger behavior | `reliability_score = 0.7` |
| No route state | Calculated from coordinates |
| No weather data | `weather_factor = 1.0` (clear) |

This ensures the system continues to function even with incomplete data during early deployment.

