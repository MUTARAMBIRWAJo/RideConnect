# Real-Time Trip Lifecycle System - Testing Guide

## System Status ✅

All components are now deployed and ready for testing:

- ✅ `PublicBusTripController` - Handles driver trip actions (accept/reject/list)
- ✅ `TripLifecycleService` - Manages trip state transitions and ML reassignment
- ✅ `NotificationService` - Creates in-app notifications in `user_notifications` table
- ✅ `TripAssigned`, `TripAccepted`, `TripRejected`, `TripReassignedToNewDriver` Events - Broadcasting
- ✅ Database migrations - Applied with idempotent checks
- ✅ Route configuration - POST endpoints properly ordered before PUT routes
- ✅ ML Service integration - Configured for automatic reassignment
- ✅ Broadcasting configuration - `config/broadcasting.php` created

## Pre-Testing Environment Setup

### 1. Configure Broadcasting Driver (Choose One)

**Option A: Using Log Driver (Development)**
```bash
# .env
BROADCAST_DRIVER=log
```
Perfect for testing - all broadcasts logged to `storage/logs/laravel.log`

**Option B: Using Pusher (Production-Ready)**
```bash
# .env
BROADCAST_DRIVER=pusher
PUSHER_APP_ID=your_app_id
PUSHER_APP_KEY=your_app_key
PUSHER_APP_SECRET=your_app_secret
PUSHER_APP_CLUSTER=your_cluster
```

**Option C: Using Supabase Realtime (Built-in to Supabase)**
```bash
# .env
BROADCAST_DRIVER=supabase
SUPABASE_URL=your_supabase_url
SUPABASE_KEY=your_supabase_key
```

### 2. Verify ML Service URL

```bash
# .env
ML_SERVICE_URL=https://ml-service-j72g.onrender.com
ML_SERVICE_TIMEOUT=30
```

### 3. Verify Database Connection

```bash
cd /home/joseph/projects/RideConnect
php artisan migrate:status
```

Expected output: All migrations should show `[batch] Ran`

### 4. Verify Routes

```bash
php artisan route:list | grep trip-requests
```

Expected output:
```
GET|HEAD        api/v1/driver/trip-requests/assigned Api\PublicBusTripController@getAssigned
POST            api/v1/driver/trip-requests/{id}/accept Api\PublicBusTripController@accept
POST            api/v1/driver/trip-requests/{id}/reject Api\PublicBusTripController@reject
```

## Testing Scenarios

### Test Data Setup

Before running tests, ensure you have:
1. A test driver user with valid authentication token
2. A test trip assigned to that driver
3. A passenger assigned to the trip

**Quick Setup Script:**
```sql
-- Create test trip assigned to driver 1
INSERT INTO trip_requests (
  passenger_id, corridor_id, 
  pickup_location, pickup_lat, pickup_lng,
  dropoff_location, dropoff_lat, dropoff_lng,
  matched_driver_id, matched_vehicle_id,
  estimated_fare, currency, status
) VALUES (
  2, 1,
  'Kigali Station', -1.9536, 29.8739,
  'Airport', -1.9650, 30.1350,
  1, 1,
  2500, 'RWF', 'ASSIGNED'
);
```

### Scenario 1: List Assigned Trips

**Endpoint:** `GET /api/v1/driver/trip-requests/assigned`

**Setup:**
- Authenticate as driver (User ID 2, Driver ID 1)
- Have at least 1 trip assigned

**Test Command:**
```bash
curl -X GET http://localhost:8000/api/v1/driver/trip-requests/assigned \
  -H "Authorization: Bearer YOUR_DRIVER_TOKEN" \
  -H "Accept: application/json"
```

**Expected Response (200):**
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

### Scenario 2: Driver Accepts Trip

**Endpoint:** `POST /api/v1/driver/trip-requests/{id}/accept`

**Test Command:**
```bash
curl -X POST http://localhost:8000/api/v1/driver/trip-requests/3/accept \
  -H "Authorization: Bearer YOUR_DRIVER_TOKEN" \
  -H "Content-Type: application/json"
```

**Expected Response (200):**
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

**Verification Steps:**

1. **Check Trip Status Updated:**
```sql
SELECT id, status, matched_driver_id, updated_at FROM trip_requests WHERE id = 3;
-- Expected: status = 'PASSENGER_WAITING'
```

2. **Check Seats Decremented (PUBLIC_BUS only):**
```sql
SELECT id, available_seats, updated_at FROM vehicles WHERE id = 1;
-- Expected: available_seats decreased by 1
```

3. **Check Notification Created:**
```sql
SELECT id, user_id, type, title, message, created_at FROM user_notifications 
WHERE type = 'TRIP_ACCEPTED' 
ORDER BY created_at DESC LIMIT 1;
-- Expected: New notification for driver
```

4. **Check Logs for Broadcast:**
```bash
tail -20 storage/logs/laravel.log | grep -i "trip.accepted\|broadcast"
-- Expected: Event broadcast logs (if using log driver)
```

### Scenario 3: Driver Rejects Trip

**Endpoint:** `POST /api/v1/driver/trip-requests/{id}/reject`

**Test Command:**
```bash
curl -X POST http://localhost:8000/api/v1/driver/trip-requests/4/reject \
  -H "Authorization: Bearer YOUR_DRIVER_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"reason": "Too far away"}'
```

**Expected Response (200):**
```json
{
  "success": true,
  "message": "Trip rejected, reassigning to new driver",
  "data": {
    "trip_id": 4,
    "status": "REASSIGNED",
    "new_driver_assigned": true,
    "new_driver_id": 5,
    "passenger_notified": true
  }
}
```

**Verification Steps:**

1. **Check Trip Status Updated to REASSIGNED:**
```sql
SELECT id, status, matched_driver_id, updated_at FROM trip_requests WHERE id = 4;
-- Expected: status = 'REASSIGNED', matched_driver_id changed
```

2. **Check Rejection Notification:**
```sql
SELECT type, message FROM user_notifications 
WHERE type = 'TRIP_REJECTED' 
ORDER BY created_at DESC LIMIT 1;
```

3. **Check ML Service Call in Logs:**
```bash
tail -50 storage/logs/laravel.log | grep -i "ML service\|reassign"
-- Expected: "Calling ML service for reassignment"
```

### Scenario 4: Error Cases

#### Error: Trip Not Found (404)
```bash
curl -X POST http://localhost:8000/api/v1/driver/trip-requests/999/accept \
  -H "Authorization: Bearer YOUR_DRIVER_TOKEN"
```

Expected: `{success: false, error_code: "TRIP_NOT_FOUND", message: "Trip not found"}`

#### Error: Driver Not Found (404)
If user has no driver profile:
```bash
curl -X GET http://localhost:8000/api/v1/driver/trip-requests/assigned \
  -H "Authorization: Bearer USER_WITHOUT_DRIVER_TOKEN"
```

Expected: `{success: false, error_code: "DRIVER_NOT_FOUND", message: "Driver profile not found"}`

#### Error: No Seats Available (422)
If vehicle has no available seats:
```bash
curl -X POST http://localhost:8000/api/v1/driver/trip-requests/5/accept \
  -H "Authorization: Bearer YOUR_DRIVER_TOKEN"
```

Expected: `{success: false, error_code: "SEAT_UNAVAILABLE", message: "No seats available on vehicle"}`

## Real-Time Broadcasting Verification

### With Log Driver

1. **Run the command to accept a trip:**
```bash
curl -X POST http://localhost:8000/api/v1/driver/trip-requests/3/accept \
  -H "Authorization: Bearer YOUR_DRIVER_TOKEN"
```

2. **Check logs for broadcast events:**
```bash
tail -50 storage/logs/laravel.log | grep -E "trip\.accepted|trip\.rejected|trip\.reassigned"
```

Expected output:
```
[INFO] Broadcasting event trip.accepted to channels [private-driver.1, private-passenger.2]
```

### With Pusher/WebSockets

1. **Monitor Pusher Debug Console** for channel activity
2. **Verify event is sent to correct channels:**
   - `private-driver.{driver_id}` 
   - `private-passenger.{passenger_id}`
3. **Check event payload matches specification** in REALTIME_TRIP_LIFECYCLE.md

## Database Integrity Checks

After running all tests, verify data integrity:

```sql
-- Check for orphaned notifications
SELECT COUNT(*) FROM user_notifications WHERE user_id NOT IN (SELECT id FROM users);

-- Check for trips with invalid status
SELECT id, status FROM trip_requests 
WHERE status NOT IN ('PENDING_MATCH', 'BUS_ASSIGNED', 'PASSENGER_WAITING', 'PASSENGER_BOARDED', 'IN_TRANSIT', 'COMPLETED', 'CANCELLED');

-- Check for trips with no driver
SELECT id, matched_driver_id FROM trip_requests 
WHERE matched_driver_id IS NULL AND status IN ('ASSIGNED', 'PASSENGER_WAITING');

-- Check for seats consistency
SELECT v.id, v.available_seats, COUNT(t.id) as trips_with_passenger
FROM vehicles v
LEFT JOIN trip_requests t ON v.id = t.matched_vehicle_id AND t.status = 'PASSENGER_WAITING'
GROUP BY v.id
HAVING COUNT(t.id) > v.available_seats;
```

## Performance Testing

### Load Test Accept Endpoint

```bash
# Using Apache Bench
ab -n 100 -c 10 \
  -H "Authorization: Bearer YOUR_DRIVER_TOKEN" \
  -p accept_payload.json \
  http://localhost:8000/api/v1/driver/trip-requests/3/accept
```

Expected: Response time < 500ms for 95th percentile

### Monitor Resource Usage

```bash
# Watch database connections
watch -n 1 "ps aux | grep postgres | wc -l"

# Watch Laravel process
watch -n 1 "ps aux | grep php-fpm | wc -l"
```

## Deployment Checklist

Before deploying to production:

- [ ] Set `BROADCAST_DRIVER=pusher` (or your chosen driver)
- [ ] Configure `PUSHER_*` environment variables
- [ ] Verify `ML_SERVICE_URL` points to production ML service
- [ ] Run `php artisan migrate --force` to apply all migrations
- [ ] Run `php artisan cache:clear` to clear route cache
- [ ] Run `php artisan config:cache` to cache configurations
- [ ] Verify SSL/HTTPS is enabled for secure WebSocket connections
- [ ] Test at least one complete trip lifecycle end-to-end
- [ ] Monitor logs for any broadcast errors
- [ ] Set up alerting for ML service failures

## Troubleshooting

### Issue: "405 Method Not Allowed" on POST /api/v1/driver/trip-requests/{id}/accept

**Solution:**
```bash
php artisan route:clear
php artisan cache:clear
```

### Issue: Broadcasts not appearing in logs

**Check:**
1. `BROADCAST_DRIVER` is set to `log` in `.env`
2. `storage/logs/` directory is writable
3. Check `config/logging.php` for correct channel configuration

**Enable:**
```bash
tail -f storage/logs/laravel.log
# Then run test, watch logs in real-time
```

### Issue: ML Service not responding

**Check:**
1. ML Service URL is correct: `https://ml-service-j72g.onrender.com/reassign`
2. Network connectivity: `curl https://ml-service-j72g.onrender.com/reassign`
3. Check logs for timeout: `tail storage/logs/laravel.log | grep "Failed to connect to ML"`

**Fallback:** Set `ML_SERVICE_TIMEOUT` to higher value:
```bash
ML_SERVICE_TIMEOUT=45  # 45 seconds instead of 30
```

### Issue: Notifications not being created

**Check:**
1. `user_notifications` table exists: `php artisan migrate:status | grep notifications`
2. User has valid record in `users` table
3. Check logs for errors: `tail storage/logs/laravel.log | grep -i "notification"`

## Success Criteria

A complete test is successful when:

1. ✅ Driver can list assigned trips (GET endpoint returns 200)
2. ✅ Driver can accept a trip (POST accept endpoint returns 200)
3. ✅ Trip status changes to `PASSENGER_WAITING` in database
4. ✅ Seats decrement for PUBLIC_BUS vehicles
5. ✅ Notifications created for driver and passenger
6. ✅ Broadcast events logged/sent to correct channels
7. ✅ Driver can reject a trip (POST reject endpoint returns 200)
8. ✅ ML service called and new driver assigned
9. ✅ New driver receives notification of reassignment
10. ✅ All error cases return appropriate error codes and messages

---

**Next Steps After Testing:**
1. Document any issues found
2. Run performance baseline tests
3. Deploy to staging environment
4. Set up monitoring and alerting
5. Coordinate with Flutter team for real-time subscription integration

**Documentation:** See [REALTIME_TRIP_LIFECYCLE.md](REALTIME_TRIP_LIFECYCLE.md) for complete system architecture and API reference.
