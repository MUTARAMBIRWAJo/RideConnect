# 🚀 RideConnect Flutter API Quick Reference

## Phase 1: Driver Matching (Show available drivers)
```
POST /api/driver-matching
{
  "transport_type": "moto",
  "pickup_lat": -1.9399, "pickup_lng": 29.7733,
  "dropoff_lat": -1.9500, "dropoff_lng": 29.7800
}
→ Get: drivers[], matching_session_id, expires_at
```

## Phase 2: Request Trip (User selects driver)
```
POST /api/mobile/trips/request
{
  "selected_driver_id": 364,
  "matching_session_id": "uuid-from-phase1",
  "pickup_location": "Kigali Center",
  "pickup_lat": -1.9399, "pickup_lng": 29.7733,
  "dropoff_location": "Business District",
  "dropoff_lat": -1.9500, "dropoff_lng": 29.7800,
  "fare": 900
}
→ Get: trip_id, trip_state (PENDING), driver_action_required: true
```

## Phase 3: Monitor Status (Poll every 2 seconds)
```
GET /api/mobile/trips/current
→ Returns: trip_id, trip_state (PENDING→ACCEPTED→STARTED→COMPLETED)
```

## Phase 4: Real-time Tracking (After ACCEPTED)
```
GET /api/mobile/trips/{trip_id}/track
→ Returns: driver_location {lat, lng}, eta, route_path
```

---

## Key Points
✅ Save `matching_session_id` - expires in 20 seconds  
✅ Add `X-Idempotency-Key` header to prevent duplicates  
✅ Handle driver timeout (PENDING > 90s = retry)  
✅ Convert plus codes to coordinates  
✅ Validate coordinates are in Rwanda bounds

---

## Test Drivers Available
- 364: Jean Claude Moto (-1.9399, 29.7733)
- 365: Patrick Express (-1.9379, 29.7753)  
- 366: Sophie Rider (-1.9429, 29.7743)
- 367: Michel Transporteur (-1.9389, 29.7713)
- 368: Therese Voiture (-1.9419, 29.7723)

All drivers are online and ready to accept trips!
