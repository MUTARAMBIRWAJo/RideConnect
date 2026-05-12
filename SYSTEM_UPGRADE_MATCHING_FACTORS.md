# RideConnect Real-Time Matching & Behavior System

## Overview

This document describes the new real-time matching factors and condition-tracking system added to RideConnect.

### Core Objective

**Audit and upgrade the RideConnect system to ensure that the following critical matching factors are properly recorded and usable:**
- Driver behavior 
- Passenger behavior
- Route state
- Weather conditions

## Database Schema

### New Tables

#### `driver_behaviors`
Captures driver behavioral metrics and scores at trip start:
- `behavior_score` (0-1): Composite score based on rating, acceptance_rate, cancellation_rate, on_time_rate
- `acceptance_rate`: Percentage of offered trips accepted (higher is better)
- `cancellation_rate`: Percentage of accepted trips cancelled (lower is better)
- `on_time_rate`: Estimated from historical performance

#### `passenger_behaviors`
Captures passenger reliability metrics:
- `reliability_score` (0-1): Composite score based on cancellation/no-show history
- `cancellation_rate`: Percentage of booked trips cancelled
- `no_show_rate`: Percentage of bookings where passenger didn't show
- `total_trips`: Lifetime trip count for context

#### `route_states`
Snapshot of route conditions at trip start:
- `distance_km`: Calculated haversine distance
- `estimated_duration_min`: Based on distance + traffic level
- `traffic_level` (1-5): Current traffic conditions (1=clear, 5=severe congestion)
- `congestion_index` (0-1): Normalized traffic severity for matching

#### `weather_conditions`
Snapshot of weather at trip start location:
- `weather_factor` (0-1): Multiplier for matching (1.0 = clear, <1.0 = adverse)
- `condition`: Description (rain, fog, clear, etc.)
- `temperature_celsius`, `wind_speed_kmh`, `precipitation_mm`: Raw data

### Modified Tables

#### `trips` (new columns)
- `driver_behavior_id` → FK to driver_behaviors
- `passenger_behavior_id` → FK to passenger_behaviors
- `route_state_id` → FK to route_states
- `weather_condition_id` → FK to weather_conditions

## Matching Engine

### Weighted Scoring Formula

$$\text{score} = w_1 \cdot d + w_2 \cdot r + w_3 \cdot b + w_4 \cdot p + w_5 \cdot t + w_6 \cdot w$$

Where:
- $d$: distance penalty (0-1, lower distance = higher score)
- $r$: driver rating normalized to 0-1
- $b$: driver behavior score (0-1)
- $p$: passenger reliability (0-1)
- $t$: traffic factor (0-1, clear traffic = 1.0)
- $w$: weather factor (0-1, clear weather = 1.0)

### Weights (sum = 1.0)
- **w₁ = 0.20**: Distance penalty
- **w₂ = 0.15**: Driver rating
- **w₃ = 0.20**: Driver behavior score
- **w₄ = 0.15**: Passenger reliability
- **w₅ = 0.15**: Traffic factor
- **w₆ = 0.15**: Weather factor

### Usage

```php
// Inject MatchingEngine service
$matchingEngine = app(MatchingEngine::class);

// Calculate scores for multiple drivers
$driverIds = [1, 2, 3, 4, 5]; // Available drivers
$scores = $matchingEngine->calculateMatchingScore($trip, $driverIds);

// Result: [2 => 0.8234, 1 => 0.7891, ...]  (sorted highest to lowest)
$bestDriverId = array_key_first($scores);
```

## Real-Time Trip Snapshot Capture

### When Snapshots Are Captured

**At trip start** (when driver calls `PUT /api/mobile/drivers/trips/{id}/start`):

```php
// In MobileDriverController::startTrip()
$trip = $this->conditionService->captureSnapshot($trip);
```

This captures:
1. **Driver behavior** from historical records
2. **Passenger reliability** from historical records
3. **Route state** from current traffic/weather APIs or traffic_events table
4. **Weather conditions** from current conditions

All snapshots are stored in their respective tables and linked to the trip via FK.

### Data Flow

```
Trip Start Request
    ↓
MobileDriverController::startTrip()
    ↓
TripConditionService::captureSnapshot()
    ├→ createDriverBehavior()     [queries driver_behaviors, ride_events, ride_cancellations]
    ├→ createPassengerBehavior()  [queries passenger_behaviors, ride_events, ride_cancellations]
    ├→ createRouteState()         [queries traffic_events, calculates distance/duration]
    └→ createWeatherCondition()   [queries traffic_events, extracts weather snapshot]
    ↓
Trip updated with FK to all 4 snapshot records
    ↓
TripStarted event fired
```

## Internal APIs (`/api/v1/internal/*`)

### Driver Behavior
```
GET /api/v1/internal/driver/{driver_id}/behavior
```
Returns latest driver behavior record with scores and metrics.

**Response:**
```json
{
  "success": true,
  "data": {
    "id": 42,
    "driver_id": 15,
    "trip_id": 1023,
    "behavior_score": 0.8234,
    "rating": 4.5,
    "acceptance_rate": 0.92,
    "cancellation_rate": 0.08,
    "on_time_rate": 0.87,
    "notes": "Snapshot created at trip start",
    "reviewed_at": "2025-05-06T10:30:00Z"
  }
}
```

### Passenger Behavior
```
GET /api/v1/internal/passenger/{passenger_id}/behavior
```
Returns latest passenger behavior record with reliability metrics.

**Response:**
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
    "notes": "Passenger reliability snapshot..."
  }
}
```

### Route State
```
GET /api/v1/internal/route-state?trip_id=1023
GET /api/v1/internal/route-state?pickup_lat=...&pickup_lng=...&dropoff_lat=...&dropoff_lng=...
```

**Response:**
```json
{
  "success": true,
  "data": {
    "id": 88,
    "trip_id": 1023,
    "pickup_lat": -1.945,
    "pickup_lng": 30.061,
    "dropoff_lat": -1.950,
    "dropoff_lng": 30.070,
    "distance_km": 0.678,
    "estimated_duration_min": 8,
    "traffic_level": 2,
    "congestion_index": 0.25,
    "route_name": "CAR_SNAPSHOT"
  }
}
```

### Weather
```
GET /api/v1/internal/weather?trip_id=1023
GET /api/v1/internal/weather?lat=-1.945&lng=30.061
```

**Response:**
```json
{
  "success": true,
  "data": {
    "id": 15,
    "trip_id": 1023,
    "location_lat": -1.945,
    "location_lng": 30.061,
    "condition": "clear",
    "temperature_celsius": 24.0,
    "wind_speed_kmh": 8.5,
    "precipitation_mm": 0.0,
    "weather_factor": 1.0,
    "description": "Clear conditions, optimal travel",
    "recorded_at": "2025-05-06T10:30:00Z"
  }
}
```

## Model Relationships

All new models follow Laravel convention:

```php
// Trip model
$trip->driverBehavior()       // belongsTo
$trip->passengerBehavior()    // belongsTo
$trip->routeState()           // belongsTo
$trip->weatherCondition()     // belongsTo

// DriverBehavior model
$behavior->trip()             // belongsTo
$behavior->driver()           // belongsTo

// RouteState model
$state->trip()                // belongsTo

// WeatherCondition model
$condition->trip()            // belongsTo

// PassengerBehavior model
$behavior->trip()             // belongsTo
$behavior->passenger()        // belongsTo
```

## Migration Files

### Migration: `2026_05_06_000001_create_behavior_and_condition_tables.php`

Creates:
1. `driver_behaviors` table
2. `passenger_behaviors` table
3. `route_states` table
4. `weather_conditions` table
5. Adds FK columns to `trips` table

Safely handles existing tables and columns.

## Service Classes

### TripConditionService
- **Location:** `app/Services/TripConditionService.php`
- **Methods:**
  - `captureSnapshot(Trip $trip): Trip` - Capture all 4 snapshots
  - `getCurrentRouteState($lat, $lng, ...): array` - Get current route conditions
  - `getCurrentWeatherState($lat, $lng): array` - Get current weather

### MatchingEngine
- **Location:** `app/Services/MatchingEngine.php`
- **Methods:**
  - `calculateMatchingScore(Trip $trip, array $driverIds): array` - Compute weighted scores
  - Private helpers for each factor normalization

## Deployment Checklist

- [ ] Run migration: `php artisan migrate`
- [ ] Verify all new tables created
- [ ] Add `TripConditionService` to service container (auto-discovery)
- [ ] Update `MobileDriverController` to capture snapshots on trip start
- [ ] Update `TripController` to capture snapshots on trip start
- [ ] Register internal API routes in `routes/api.php`
- [ ] Update API documentation
- [ ] Test trip start flow end-to-end
- [ ] Verify snapshot data persists correctly
- [ ] Test internal API endpoints
- [ ] Monitor first 100 trips for data quality

## Testing Recommendations

### Unit Tests
- Behavior score calculations
- Distance penalty formula
- Matching score formula

### Integration Tests
- Trip start flow with snapshots
- Internal API endpoints
- Snapshot data consistency

### Manual Tests
1. Create a trip and start it
2. Verify all 4 snapshot records created
3. Query internal APIs to confirm data
4. Check matching engine score computation
5. Verify historical data aggregation accuracy

## Future Enhancements

- [ ] Real-time traffic API integration (remove mock)
- [ ] Weather forecast API integration (remove mock)
- [ ] Driver behavior continuous updates during trip
- [ ] Passenger satisfaction ratings post-trip
- [ ] ML model predictions based on snapshot features
- [ ] A/B testing matching algorithms
- [ ] Dashboard visualization of matching factors

