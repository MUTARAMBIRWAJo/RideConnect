# Driver API Contract (Flutter)

Base URL:
- `https://<host>/api/v1`

Auth:
- All endpoints require `Authorization: Bearer <token>`
- Content-Type: `application/json`

## 1) Driver Status

### Update availability/location
- `PUT /driver/status`

Request:
```json
{
  "status": "online",
  "latitude": -1.9441,
  "longitude": 30.0619
}
```

Response:
```json
{
  "success": true,
  "message": "Status updated successfully",
  "data": {
    "status": "online",
    "latitude": -1.9441,
    "longitude": 30.0619,
    "last_online_at": "2026-03-12T12:30:00+00:00"
  }
}
```

## 2) Driver Ride Requests

### List incoming requests
- `GET /driver/requests`

Response item fields:
- `id`, `passenger`, `pickup_location`, `pickup_lat`, `pickup_lng`
- `dropoff_location`, `dropoff_lat`, `dropoff_lng`
- `fare`, `distance`, `status`, `requested_at`

### Accept request
- `PUT /driver/requests/{id}/accept`

Response:
```json
{
  "success": true,
  "message": "Ride request accepted",
  "data": {
    "id": 101,
    "status": "ACCEPTED",
    "accepted_at": "2026-03-12T12:35:00+00:00"
  }
}
```

### Reject request
- `PUT /driver/requests/{id}/reject`

Request (optional):
```json
{
  "reason": "Too far from pickup"
}
```

Response:
```json
{
  "success": true,
  "message": "Ride request rejected",
  "data": {
    "id": 101,
    "status": "PENDING"
  }
}
```

### Complete request
- `PUT /driver/requests/{id}/complete`

Request (all optional):
```json
{
  "actual_pickup_lat": -1.9442,
  "actual_pickup_lng": 30.0620,
  "actual_dropoff_lat": -1.9500,
  "actual_dropoff_lng": 30.0700,
  "actual_distance": 7.4,
  "actual_fare": 4200
}
```

Response:
```json
{
  "success": true,
  "message": "Ride completed successfully",
  "data": {
    "id": 101,
    "status": "COMPLETED",
    "completed_at": "2026-03-12T13:05:00+00:00",
    "actual_fare": 4200
  }
}
```

## 3) Driver Rides

### List own rides
- `GET /driver/rides`

### Create ride
- `POST /driver/rides`

Required request fields:
- `vehicle_id`
- `origin_address`, `origin_lat`, `origin_lng`
- `destination_address`, `destination_lat`, `destination_lng`
- `departure_time`
- `available_seats`
- `price_per_seat`

### Update ride
- `PUT /driver/rides/{id}`

### Delete ride
- `DELETE /driver/rides/{id}`

## 4) Driver Earnings

### Current period earnings
- `GET /driver/earnings?start_date=2026-03-01&end_date=2026-03-31`

Response fields:
- `period.start`, `period.end`
- `total_earnings`, `completed_trips`, `average_fare`
- `today_earnings`, `pending_payments`, `currency`

### Monthly earnings trend
- `GET /driver/earnings/monthly`

Response item fields:
- `month`, `month_name`, `earnings`, `trips`

## 5) Driver Supporting Endpoints

- `GET /driver/profile`
- `PUT /driver/profile`
- `GET /driver/stats`
- `GET /driver/bookings`
- `GET /driver/trips`
- `POST /driver/documents`
- `GET /driver/documents`

## 6) Auth Endpoints For Flutter Driver App

- Validate token: `GET /auth/token/validate`
- Clear session: `POST /auth/session/clear`

Clear session request:
```json
{
  "all_devices": true
}
```

---

Notes:
- Use exact request key names as shown above.
- Handle `401` by clearing local session and redirecting to login.
- Handle `403` for role/approval errors (driver not approved or wrong role).
