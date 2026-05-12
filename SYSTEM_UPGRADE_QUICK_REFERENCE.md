# RideConnect Matching System - Quick Reference

## TL;DR

The RideConnect system now captures driver behavior, passenger reliability, route conditions, and weather at trip start. These factors feed into a weighted matching algorithm that scores drivers from 0-1 to find the best match.

## Migration

```bash
php artisan migrate
```

Creates: `driver_behaviors`, `passenger_behaviors`, `route_states`, `weather_conditions` tables.

## Models

```php
// Trip now has these relationships:
$trip->driverBehavior()      // DriverBehavior
$trip->passengerBehavior()   // PassengerBehavior
$trip->routeState()          // RouteState
$trip->weatherCondition()    // WeatherCondition
```

## Snapshot Capture (Automatic)

When trip starts:
```php
// In MobileDriverController or TripController
$trip = $this->conditionService->captureSnapshot($trip);
```

This creates all 4 snapshot records automatically.

## Matching Engine

```php
// Rank drivers by matching score
$engine = app(MatchingEngine::class);
$scores = $engine->calculateMatchingScore($trip, [1, 2, 3, 4, 5]);

// Result: [2 => 0.8234, 1 => 0.7891, ...]
$bestDriverId = array_key_first($scores);
```

**Formula:** `0.20*distance + 0.15*rating + 0.20*behavior + 0.15*reliability + 0.15*traffic + 0.15*weather`

## Internal APIs

```bash
# Get driver behavior
GET /api/v1/internal/driver/{id}/behavior

# Get passenger behavior
GET /api/v1/internal/passenger/{id}/behavior

# Get route state
GET /api/v1/internal/route-state?trip_id=123
GET /api/v1/internal/route-state?pickup_lat=...&pickup_lng=...&dropoff_lat=...&dropoff_lng=...

# Get weather
GET /api/v1/internal/weather?trip_id=123
GET /api/v1/internal/weather?lat=...&lng=...
```

## Fields & Scores

### Driver Behavior
- `behavior_score` (0-1): Composite score
- `acceptance_rate` (0-1): % of offered trips accepted
- `cancellation_rate` (0-1): % of trips cancelled
- `on_time_rate` (0-1): % on-time pickups
- `rating` (1-5): Star rating

### Passenger Behavior
- `reliability_score` (0-1): Composite reliability
- `cancellation_rate` (0-1): % cancellations
- `no_show_rate` (0-1): % no-shows
- `total_trips`: Lifetime trips

### Route State
- `distance_km`: Route distance
- `estimated_duration_min`: ETA minutes
- `traffic_level` (1-5): 1=clear, 5=severe
- `congestion_index` (0-1): Normalized traffic

### Weather Condition
- `condition`: Description (rain, clear, fog, etc.)
- `weather_factor` (0-1): 1.0=clear, <1.0=adverse
- `temperature_celsius`: Temp
- `wind_speed_kmh`: Wind
- `precipitation_mm`: Rain

## Key Files

| File | Purpose |
|------|---------|
| `TripConditionService.php` | Snapshot capture |
| `MatchingEngine.php` | Weighted scoring |
| `InternalDataController.php` | API endpoints |
| `Trip.php` | Model relationships |
| Migration file | Database tables |

## Testing Trip Start

```bash
# 1. Create trip
POST /api/v1/mobile/trips/request
{
  "pickup_location": "Kigali City Center",
  "pickup_lat": -1.945,
  "pickup_lng": 30.061,
  "dropoff_location": "AUPA",
  "dropoff_lat": -1.950,
  "dropoff_lng": 30.070
}

# 2. Accept (driver)
POST /api/v1/mobile/drivers/trips/{id}/accept

# 3. Start (driver) - THIS TRIGGERS SNAPSHOT CAPTURE
PUT /api/v1/mobile/drivers/trips/{id}/start

# 4. Query snapshots
GET /api/v1/internal/driver/15/behavior
GET /api/v1/internal/passenger/67/behavior
GET /api/v1/internal/route-state?trip_id={id}
GET /api/v1/internal/weather?trip_id={id}
```

## Data Quality

| Scenario | What Happens |
|----------|--------------|
| New passenger | reliability_score = 0.7 (default) |
| New driver | behavior_score = 0.6 (default) |
| No traffic data | traffic_level = 3 (neutral) |
| No weather data | weather_factor = 1.0 (clear) |

Fallbacks ensure system works even with incomplete historical data.

## Common Tasks

### Get current driver's behavior score
```php
$behavior = $trip->driverBehavior;
$score = $behavior->behavior_score ?? 0.6; // with fallback
```

### Calculate match quality
```php
$engine = app(MatchingEngine::class);
$score = $engine->calculateMatchingScore($trip, [$driverId])[$driverId];
echo "Match quality: " . ($score * 100) . "%"; // 82.34%
```

### Check weather impact
```php
$weather = $trip->weatherCondition;
if ($weather->weather_factor < 1.0) {
    echo "Adverse weather: {$weather->condition}";
}
```

### Check route conditions
```php
$route = $trip->routeState;
echo "Distance: {$route->distance_km}km";
echo "ETA: {$route->estimated_duration_min}min";
echo "Traffic: Level {$route->traffic_level}/5";
```

## Debugging

### Check if snapshots created
```php
$trip = Trip::with(['driverBehavior', 'passengerBehavior', 'routeState', 'weatherCondition'])->find($tripId);
dump($trip->driverBehavior);     // Should not be null
dump($trip->routeState);         // Should not be null
```

### Verify matching scores
```php
$engine = app(MatchingEngine::class);
$scores = $engine->calculateMatchingScore($trip, [1, 2, 3, 4, 5]);
dd($scores); // All should be 0-1, sorted descending
```

### Test internal APIs
```bash
curl -H "Authorization: Bearer $TOKEN" \
  "http://localhost:8000/api/v1/internal/driver/1/behavior" | jq
```

## Next Steps

1. ✅ Run migration
2. ✅ Test trip start flow
3. ✅ Verify snapshots created
4. ✅ Integrate MatchingEngine into driver assignment
5. ⏳ Set up dashboard to visualize factors
6. ⏳ A/B test weight adjustments
7. ⏳ Replace fallback values with real API data

## Documentation

- **Full Guide:** `SYSTEM_UPGRADE_MATCHING_FACTORS.md`
- **Implementation Details:** `SYSTEM_UPGRADE_IMPLEMENTATION_SUMMARY.md`
- **This File:** `SYSTEM_UPGRADE_QUICK_REFERENCE.md`

