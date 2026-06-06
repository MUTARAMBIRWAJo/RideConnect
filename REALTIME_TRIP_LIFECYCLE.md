# Real-Time Trip Lifecycle & Notification System

## Overview

This document describes the complete real-time notification and trip lifecycle system for driver-passenger trip interactions in RideConnect's public bus system. The system manages trip assignment, driver acceptance/rejection, automatic ML-based reassignment, and real-time notifications.

## System Architecture

### Core Components

1. **PublicBusTripController** (`app/Http/Controllers/Api/PublicBusTripController.php`)
   - HTTP endpoint handler for driver trip actions
   - Orchestrates trip lifecycle state transitions
   - Validates business rules before accepting/rejecting trips

2. **TripLifecycleService** (`app/Services/TripLifecycleService.php`)
   - Core business logic for trip state management
   - Handles seat management for public buses
   - Coordinates with ML service for automatic reassignment
   - Manages notifications and event broadcasting

3. **NotificationService** (`app/Services/NotificationService.php`)
   - Creates in-app notifications in `user_notifications` table
   - Sends push notifications (FCM/APNs integration points)
   - Broadcasts notifications via real-time gateway

4. **Broadcasting Events** (`app/Events/`)
   - `TripAssigned.php` - Trip assigned to driver (by matching system)
   - `TripAccepted.php` - Driver accepts trip
   - `TripRejected.php` - Driver rejects trip
   - `TripReassignedToNewDriver.php` - Trip reassigned after rejection

## Trip Lifecycle States

```
PENDING_MATCH
    ↓
ASSIGNED (driver assigned)
    ├─→ [Driver accepts]
    │   ↓
    │   PASSENGER_WAITING
    │   ↓
    │   TRIP_STARTED
    │   ↓
    │   COMPLETED
    │
    └─→ [Driver rejects]
        ↓
        REJECTED_BY_DRIVER (temporary)
        ↓
        REASSIGNED (new driver assigned)
        ↓
        ASSIGNED (back to assigned state)
```

## API Endpoints

### 1. Get Driver's Assigned Trips

```
GET /api/v1/driver/trip-requests/assigned
Authorization: Bearer {token}
```

**Response:**
```json
{
  "success": true,
  "data": [
    {
      "id": 3,
      "status": "ASSIGNED",
      "pickup_location": "Kigali Station",
      "pickup_lat": -1.9536,
      "pickup_lng": 29.8739,
      "dropoff_location": "Airport",
      "dropoff_lat": -1.9650,
      "dropoff_lng": 30.1350,
      "fare": 2500,
      "distance": 18.5,
      "passenger_name": "Jean Mutua",
      "vehicle": {
        "id": 1,
        "type": "PUBLIC_BUS",
        "registration": "RW-001",
        "seating_capacity": 45,
        "available_seats": 12
      }
    }
  ]
}
```

**Error Responses:**
```json
{
  "success": false,
  "error_code": "DRIVER_NOT_FOUND",
  "message": "Driver profile not found"
}
```

### 2. Accept Trip

```
POST /api/v1/driver/trip-requests/{id}/accept
Authorization: Bearer {token}
```

**Success Response (200):**
```json
{
  "success": true,
  "message": "Trip accepted successfully",
  "data": {
    "trip_id": 3,
    "status": "PASSENGER_WAITING",
    "seats_remaining": 11,
    "passenger_notified": true
  }
}
```

**Error Responses:**
- `404 TRIP_NOT_FOUND`: Trip doesn't exist
- `404 DRIVER_NOT_FOUND`: Driver profile not found
- `422 SEAT_UNAVAILABLE`: No seats available (PUBLIC_BUS only)
- `409 TRIP_ALREADY_ACCEPTED`: Trip already accepted by another driver
- `500 ACCEPT_ERROR`: Unexpected error during acceptance

### 3. Reject Trip

```
POST /api/v1/driver/trip-requests/{id}/reject
Authorization: Bearer {token}
Content-Type: application/json

{
  "reason": "Too far away"
}
```

**Success Response (200):**
```json
{
  "success": true,
  "message": "Trip rejected, reassigning to new driver",
  "data": {
    "trip_id": 3,
    "status": "REASSIGNED",
    "new_driver_assigned": true,
    "new_driver_id": 5,
    "passenger_notified": true
  }
}
```

**Error Responses:**
- `404 TRIP_NOT_FOUND`: Trip doesn't exist
- `404 DRIVER_NOT_FOUND`: Driver profile not found
- `500 REJECT_ERROR`: Unexpected error during rejection

## Real-Time Broadcasting Events

All events broadcast on private channels with proper authorization. Subscribe using:
- **Driver channels**: `private-driver.{driver_id}`
- **Passenger channels**: `private-passenger.{passenger_id}`

### Event 1: Trip Assigned

**Triggered**: When matching system assigns trip to driver
**Channels**: `private-driver.{driver_id}`
**Event name**: `trip.assigned`

**Payload:**
```json
{
  "trip_id": 3,
  "status": "ASSIGNED",
  "pickup_location": "Kigali Station",
  "pickup_lat": -1.9536,
  "pickup_lng": 29.8739,
  "dropoff_location": "Airport",
  "dropoff_lat": -1.9650,
  "dropoff_lng": 30.1350,
  "fare": 2500,
  "distance": 18.5,
  "vehicle_info": {
    "type": "PUBLIC_BUS",
    "registration": "RW-001",
    "available_seats": 12
  }
}
```

### Event 2: Trip Accepted

**Triggered**: When driver accepts trip
**Channels**: `private-driver.{driver_id}`, `private-passenger.{passenger_id}`
**Event name**: `trip.accepted`

**Payload:**
```json
{
  "trip_id": 3,
  "status": "PASSENGER_WAITING",
  "driver_info": {
    "id": 1,
    "name": "Patrick Habimana",
    "phone": "+250788123456",
    "rating": 4.8
  },
  "passenger_waiting": true,
  "estimated_pickup_minutes": 8
}
```

### Event 3: Trip Rejected

**Triggered**: When driver rejects trip
**Channels**: `private-driver.{rejected_driver_id}`, `private-passenger.{passenger_id}`
**Event name**: `trip.rejected`

**Payload:**
```json
{
  "trip_id": 3,
  "status": "REJECTED_BY_DRIVER",
  "rejection_reason": "Too far away",
  "message": "Driver declined the trip. Finding new driver..."
}
```

### Event 4: Trip Reassigned

**Triggered**: After ML service assigns new driver following rejection
**Channels**: `private-driver.{new_driver_id}`, `private-passenger.{passenger_id}`, `private-driver.{old_driver_id}`
**Event name**: `trip.reassigned`

**Payload:**
```json
{
  "trip_id": 3,
  "status": "ASSIGNED",
  "new_driver_id": 5,
  "message": "New driver assigned to your trip"
}
```

## Notification Types

Notifications are stored in `user_notifications` table with the following types:

### TRIP_ASSIGNED
- **Title**: "New Trip Assigned"
- **Trigger**: Matching system assigns trip to driver
- **For**: Driver
- **Data**: `{trip_id, pickup_location, dropoff_location}`

### TRIP_ACCEPTED
- **Title**: "You Accepted the Trip"
- **Trigger**: Driver accepts trip
- **For**: Driver + Passenger
- **Data**: `{trip_id, driver_info, passenger_info}`

### TRIP_REJECTED
- **Title**: "Driver Unavailable"
- **Trigger**: Driver rejects trip
- **For**: Passenger
- **Data**: `{trip_id, reason, status}`

### TRIP_REASSIGNED
- **Title**: "New Driver Assigned"
- **Trigger**: ML service assigns new driver after rejection
- **For**: New driver + Passenger
- **Data**: `{trip_id, new_driver_id}`

## Seat Management

**For PUBLIC_BUS vehicles only:**

1. **When trip is assigned**: Seats are NOT decremented (just reserved)
2. **When driver accepts**: Seats are decremented by 1 (passenger confirmed)
3. **When driver rejects**: Seats remain unchanged (reservation cancelled, new driver found)
4. **Validation**: Accept fails with `422 SEAT_UNAVAILABLE` if `available_seats < 1`

**Key constraint**: Seat decrements happen in same transaction as status update to prevent double-booking.

## ML Service Integration

### Reassignment Trigger
When driver rejects trip → System calls ML service for new driver assignment

### Request Format
```
POST https://ml-service-j72g.onrender.com/reassign
Content-Type: application/json

{
  "trip_request_id": 3,
  "pickup_lat": -1.9536,
  "pickup_lng": 29.8739,
  "vehicle_type": "PUBLIC_BUS"
}
```

### Response Format
```json
{
  "assigned_driver_id": 5,
  "vehicle_id": 1
}
```

### Error Handling
- **Timeout**: 30 seconds with 2 automatic retries (100ms between retries)
- **Failed response**: Returns `{success: false, message: 'No drivers available'}`
- **Connection error**: Returns `{success: false, message: 'Connection error with matching service'}`

## Database Schema

### user_notifications Table
```sql
CREATE TABLE user_notifications (
  id BIGINT PRIMARY KEY,
  user_id BIGINT NOT NULL REFERENCES users(id),
  type ENUM('TRIP_ASSIGNED', 'TRIP_ACCEPTED', 'TRIP_REJECTED', 'TRIP_REASSIGNED', 'TRIP_STARTED', 'TRIP_COMPLETED') NOT NULL,
  title VARCHAR(255) NOT NULL,
  message TEXT NOT NULL,
  data JSON,
  is_read BOOLEAN DEFAULT FALSE,
  read_at TIMESTAMP NULL,
  expires_at TIMESTAMP NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE INDEX idx_user_notifications_user_id_created ON user_notifications(user_id, created_at DESC);
```

## Error Codes Reference

| Code | HTTP Status | Meaning |
|------|-------------|---------|
| TRIP_NOT_FOUND | 404 | Trip doesn't exist or not assigned to driver |
| DRIVER_NOT_FOUND | 404 | Driver profile not found |
| SEAT_UNAVAILABLE | 422 | No seats available on vehicle |
| TRIP_ALREADY_ACCEPTED | 409 | Trip was accepted by another driver |
| ACCEPT_ERROR | 500 | Unexpected error during acceptance |
| REJECT_ERROR | 500 | Unexpected error during rejection |
| ML_REASSIGNMENT_FAILED | 500 | ML service couldn't find new driver |

## Implementation Checklist

- [x] PublicBusTripController with accept/reject/getAssigned methods
- [x] TripLifecycleService with full lifecycle management
- [x] Broadcasting events (TripAssigned, TripAccepted, TripRejected, TripReassignedToNewDriver)
- [x] NotificationService creates records in user_notifications table
- [x] Seat management (PUBLIC_BUS only)
- [x] ML service integration with retries and timeout
- [x] Comprehensive error handling and error codes
- [x] Logging at INFO and ERROR levels
- [ ] Broadcasting driver configuration (WebSockets/Firebase/Supabase Realtime)
- [ ] Flutter client integration
- [ ] End-to-end testing

## Testing

### Manual API Testing

1. **Get assigned trips:**
   ```bash
   curl -X GET http://localhost:8000/api/v1/driver/trip-requests/assigned \
     -H "Authorization: Bearer {token}"
   ```

2. **Accept trip:**
   ```bash
   curl -X POST http://localhost:8000/api/v1/driver/trip-requests/3/accept \
     -H "Authorization: Bearer {token}"
   ```

3. **Reject trip:**
   ```bash
   curl -X POST http://localhost:8000/api/v1/driver/trip-requests/4/reject \
     -H "Authorization: Bearer {token}" \
     -H "Content-Type: application/json" \
     -d '{"reason": "Too far away"}'
   ```

### Database Verification

```sql
-- Check notifications created
SELECT id, user_id, type, title, message, created_at 
FROM user_notifications 
WHERE type IN ('TRIP_ASSIGNED', 'TRIP_ACCEPTED', 'TRIP_REJECTED', 'TRIP_REASSIGNED')
ORDER BY created_at DESC
LIMIT 10;

-- Check trip status updates
SELECT id, status, matched_driver_id, matched_vehicle_id, updated_at
FROM trip_requests
WHERE id IN (3, 4, 5)
ORDER BY updated_at DESC;

-- Check seat availability after accept
SELECT id, available_seats, status, updated_at
FROM vehicles
WHERE id = 1;
```

## Flutter Client Integration

### Subscribe to Trip Events

```dart
// Subscribe to driver's personal channel
supabase.channel('private-driver.${driverId}').subscribe().listen((status) {
  if (status == RealtimeSubscriptionStatus.subscribed) {
    debugPrint('Subscribed to driver channel');
  }
});

// Listen for trip events
supabase
    .channel('private-driver.${driverId}')
    .onBroadcast(
      event: 'trip.assigned',
      callback: (payload) {
        debugPrint('Trip assigned: ${payload['trip_id']}');
        // Update UI with new trip
      },
    )
    .subscribe();
```

### Update UI on Events

```dart
// Listen for acceptance
supabase
    .channel('private-driver.${driverId}')
    .onBroadcast(
      event: 'trip.accepted',
      callback: (payload) {
        debugPrint('Trip accepted, driver ETA: ${payload['estimated_pickup_minutes']}');
        // Update UI with driver info and ETA
      },
    )
    .subscribe();
```

## Deployment Notes

1. **Enable Broadcasting**: Configure `BROADCAST_DRIVER` in `.env` (websocket, pusher, supabase_realtime, firebase)
2. **ML Service Credentials**: Ensure `ML_SERVICE_URL` and optional `ML_SERVICE_API_KEY` are set in `.env`
3. **Database Migrations**: Ensure `user_notifications` table is created
4. **Queue Configuration**: If using queued broadcasts, ensure queue driver is properly configured

## Future Enhancements

1. **Driver Rating System**: Include driver ratings in TripAccepted event
2. **Route Optimization**: Include recommended route in TripAccepted event
3. **Payment Integration**: Update payment status in real-time
4. **Passenger Communication**: Allow in-app messaging between driver and passenger
5. **Analytics**: Track acceptance/rejection rates by driver and reason
6. **Automatic Escalation**: Escalate to supervisor if no driver accepts after N reassignments

---

**Last Updated**: 2024
**Version**: 1.0
