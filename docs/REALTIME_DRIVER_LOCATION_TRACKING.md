# Real-Time Driver Location Tracking System

## Overview

The Real-Time Driver Location Tracking system enables passengers to track driver locations in real-time as they move during active trips. Drivers continuously update their position while online, and passengers can fetch live location data via API endpoints or WebSocket connections.

## Architecture

### Components

1. **DriverLocation Model** - Stores driver position data with movement metrics
2. **DriverLocationService** - Business logic for location updates and broadcasting
3. **MobileDriverController** - Handles driver location updates from mobile app
4. **DriverTrackingController** - Provides tracking data endpoints for passengers
5. **RealtimeGateway** - Broadcasts location updates via Supabase Realtime
6. **CleanupStaleDriverLocations Job** - Maintains data consistency for offline drivers

### Database Schema

#### driver_locations Table

```sql
CREATE TABLE driver_locations (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    driver_id BIGINT FOREIGN KEY,
    latitude DECIMAL(10,7),
    longitude DECIMAL(10,7),
    speed_kmh DECIMAL(5,2),                    -- Current speed in km/h
    heading DECIMAL(5,1),                      -- Direction 0-360 degrees
    accuracy DECIMAL(6,2),                     -- GPS accuracy in meters
    updated_at TIMESTAMP,                      -- Last position update
    last_activity_at TIMESTAMP,                -- Last activity timestamp
    is_online BOOLEAN DEFAULT FALSE,           -- Driver online status
    UNIQUE KEY unique_driver_id (driver_id)
);
```

## API Endpoints

### Driver Endpoints (POST)

#### Update Driver Live Location
```
POST /api/v1/mobile/drivers/live-location
Authorization: Bearer {token}
Content-Type: application/json

{
    "lat": -1.9548,
    "lng": 30.0618,
    "speed_kmh": 45.5,              // Optional
    "heading": 180.0,               // Optional (0-360 degrees)
    "accuracy": 5.2,                // Optional (meters)
    "is_online": true               // Optional
}
```

**Response:**
```json
{
    "status": "success",
    "data": {
        "driver_id": 123,
        "latitude": -1.9548,
        "longitude": 30.0618,
        "speed_kmh": 45.5,
        "heading": 180.0,
        "accuracy": 5.2,
        "is_online": true,
        "updated_at": "2026-05-05T10:30:00Z"
    }
}
```

### Passenger Endpoints (GET)

#### Get Current Driver Location
```
GET /api/v1/mobile/tracking/driver/{driverId}
Authorization: Bearer {token}
```

**Response:**
```json
{
    "success": true,
    "data": {
        "driver_id": 123,
        "latitude": -1.9548,
        "longitude": 30.0618,
        "speed_kmh": 45.5,
        "heading": 180.0,
        "accuracy": 5.2,
        "is_online": true,
        "last_updated": "2026-05-05T10:30:00Z",
        "last_activity": "2026-05-05T10:30:00Z"
    }
}
```

#### Get Driver Location for Trip
```
GET /api/v1/mobile/tracking/trip/{tripId}
Authorization: Bearer {token}
```

**Response:**
```json
{
    "success": true,
    "data": {
        "trip_id": 456,
        "driver_id": 123,
        "driver_name": "John Doe",
        "latitude": -1.9548,
        "longitude": 30.0618,
        "speed_kmh": 45.5,
        "heading": 180.0,
        "accuracy": 5.2,
        "is_online": true,
        "last_updated": "2026-05-05T10:30:00Z",
        "last_activity": "2026-05-05T10:30:00Z",
        "trip_status": "STARTED"
    }
}
```

#### Get Nearby Online Drivers
```
GET /api/v1/mobile/tracking/nearby
Authorization: Bearer {token}
Query Parameters:
    - latitude (required): -90 to 90
    - longitude (required): -180 to 180
    - radius_km (optional, default: 5.0): 0.1 to 50

Example: GET /api/v1/mobile/tracking/nearby?latitude=-1.9548&longitude=30.0618&radius_km=10
```

**Response:**
```json
{
    "success": true,
    "data": [
        {
            "driver_id": 123,
            "latitude": -1.9548,
            "longitude": 30.0618,
            "speed_kmh": 45.5,
            "heading": 180.0,
            "accuracy": 5.2,
            "is_online": true,
            "distance_km": 2.3,
            "last_updated": "2026-05-05T10:30:00Z",
            "last_activity": "2026-05-05T10:30:00Z"
        }
        // ... more drivers
    ]
}
```

## Real-Time Updates via WebSocket

Passengers can subscribe to real-time driver location updates using Supabase Realtime:

```typescript
// Flutter/Dart example
import 'package:supabase_flutter/supabase_flutter.dart';

final supabase = Supabase.instance.client;

// Subscribe to driver location updates
final subscription = supabase
    .channel('driver:123')
    .onBroadcast(
        event: 'driver.location.updated',
        callback: (payload) {
            print('Driver location updated: ${payload}');
            // Update map marker position
        },
    )
    .subscribe();

// Unsubscribe
await supabase.removeChannel(subscription);
```

**Real-time Event Structure:**
```json
{
    "driver_id": 123,
    "latitude": -1.9548,
    "longitude": 30.0618,
    "speed_kmh": 45.5,
    "heading": 180.0,
    "accuracy": 5.2,
    "is_online": true,
    "updated_at": "2026-05-05T10:30:00Z"
}
```

## Data Flow

### Location Update Flow

1. **Driver App** sends location periodically (recommended: every 5-10 seconds)
2. **MobileDriverController** validates and stores location
3. **DriverLocationService** updates database and broadcasts via Realtime
4. **RealtimeGateway** sends update to Supabase channel
5. **Passenger App** receives update via WebSocket subscription
6. **Map UI** updates driver marker in real-time

### Online Status Management

- Driver goes **online**: Initial location update sets `is_online=true`
- Driver goes **offline**: Manual status update or auto-detection via `CleanupStaleDriverLocations` job
- **Stale threshold**: 5 minutes (configurable in `DriverLocationService`)
- **Auto cleanup**: Runs every 5 minutes as scheduled job

## Implementation Guide

### For Driver Mobile App

```dart
// Send location every 10 seconds while online
Timer.periodic(Duration(seconds: 10), (timer) {
    if (!isDriverOnline) {
        timer.cancel();
        return;
    }

    final position = await getCurrentPosition(); // Use geolocator package
    
    await dio.post(
        'https://api.rideconnect.dev/api/v1/mobile/drivers/live-location',
        data: {
            'lat': position.latitude,
            'lng': position.longitude,
            'speed_kmh': position.speed,
            'heading': position.heading,
            'accuracy': position.accuracy,
            'is_online': true,
        },
        options: Options(headers: {'Authorization': 'Bearer $token'}),
    );
});
```

### For Passenger Mobile App

```dart
// Option 1: Polling (HTTP requests)
Timer.periodic(Duration(seconds: 5), (timer) {
    dio.get(
        'https://api.rideconnect.dev/api/v1/mobile/tracking/trip/$tripId',
        options: Options(headers: {'Authorization': 'Bearer $token'}),
    ).then((response) {
        updateMapMarker(response.data['data']);
    });
});

// Option 2: Real-time WebSocket (Recommended)
final subscription = supabase.channel('driver:${trip.driverId}')
    .onBroadcast(event: 'driver.location.updated', callback: (payload) {
        updateMapMarker(payload);
    })
    .subscribe();
```

## Performance Considerations

### Location Update Frequency

- **Recommended**: 5-10 seconds for active trips
- **During waiting**: 30-60 seconds
- **Offline drivers**: No updates needed

### Data Optimization

1. **Caching**: Location cached for 10 minutes
2. **Database**: Unique constraint on driver_id ensures single record per driver
3. **Cleanup**: Stale locations auto-marked as offline every 5 minutes
4. **Broadcasting**: Only active drivers trigger Realtime broadcasts

### Scalability

- Designed for thousands of concurrent drivers
- Location service is horizontally scalable
- Supabase Realtime handles WebSocket connections
- Database queries optimized with proper indexing

## Troubleshooting

### Driver Location Not Updating

1. Check driver authentication token is valid
2. Verify `is_online` status is true
3. Check network connectivity on device
4. Ensure location permissions granted in mobile app
5. Review API logs: `php artisan logs:show | grep location`

### High Battery Drain

- Reduce location update frequency if acceptable
- Use background location service appropriately
- Implement adaptive frequency (slower when not on active trip)
- Consider geofencing to reduce constant updates

### Stale Location Data

- Cleanup job runs every 5 minutes automatically
- Manual cleanup: `php artisan drivers:cleanup-locations --force`
- Check `last_activity_at` timestamp to identify inactive drivers

## Monitoring

### Health Check Metrics

```bash
# Check online drivers count
php artisan drivers:cleanup-locations

# View recent location updates
SELECT * FROM driver_locations 
WHERE is_online = true 
AND last_activity_at > DATE_SUB(NOW(), INTERVAL 1 MINUTE);

# Find stale drivers
SELECT * FROM driver_locations 
WHERE is_online = true 
AND last_activity_at < DATE_SUB(NOW(), INTERVAL 5 MINUTE);
```

### Key Metrics to Monitor

- Total online drivers
- Average location update latency
- WebSocket connection count
- Database query performance
- Realtime broadcast failures

## Configuration

### Scheduler Configuration (routes/console.php)

```php
// Run cleanup every 5 minutes
Schedule::job(new CleanupStaleDriverLocations())
    ->everyFiveMinutes()
    ->name('cleanup-stale-driver-locations');
```

### Service Configuration (app/Services/Location/DriverLocationService.php)

```php
private const ONLINE_TIMEOUT_MINUTES = 5;        // Mark driver offline after 5 min inactivity
private const LOCATION_CACHE_TTL_MINUTES = 10;   // Cache location for 10 minutes
```

## Security

- All endpoints require `auth:sanctum` middleware
- Driver can only update their own location
- Passenger can only view authorized trip driver location
- Location data is encrypted in transit (HTTPS)
- Supabase Realtime uses JWT authentication

## Future Enhancements

1. **Geofencing**: Auto-update status based on zone boundaries
2. **Route History**: Store polyline history for trip analysis
3. **ETA Calculations**: Use live location for accurate ETAs
4. **Heatmap Analytics**: Aggregate location data for demand patterns
5. **Driver Heat Maps**: Show high-activity areas
6. **Predictive Positioning**: Use ML to predict next location
