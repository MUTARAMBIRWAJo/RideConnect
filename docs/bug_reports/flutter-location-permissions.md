# Bug: Flutter app shows "Please enable location" despite device location enabled

Summary
-------
Passengers sometimes see a message like "Please enable location to continue" even when device location is turned on. Backend requires top-level numeric `pickup_lat`/`pickup_lng` and `dropoff_lat`/`dropoff_lng`. The mobile app sometimes sends coordinates in alternate shapes or omits numeric fields causing server-side validation to fail.

Backend change
--------------
I added normalization on the server to accept several payload shapes (top-level numeric fields, nested `pickup`/`dropoff` objects, camelCase variants) and to coerce numeric strings. Files changed:

- `app/Http/Controllers/Api/PassengerController.php`
- `app/Http/Controllers/Api/TripController.php`

Branch: `fix/location-normalization` (pushed)

What the Flutter app must do (developer checklist)
-------------------------------------------------
1. Ensure location permission is requested at runtime (foreground) and, if available, request high-accuracy/GPS.
2. Always include top-level numeric coordinates in API payloads: `pickup_lat`, `pickup_lng`, `dropoff_lat`, `dropoff_lng` (numbers, not empty strings).
3. If using nested objects, prefer the top-level ones but you may send `{ "pickup": { "lat": ..., "lng": ... } }` as a fallback (server will accept it).
4. If a fresh GPS fix isn't available within 5s, use last-known location (if permission granted) and surface "Using last known location — tap to refresh".
5. Show a friendly Settings prompt when permission is denied instead of raw server errors.
6. Log request payloads (coords present/missing) to help triage.

Sample payload (preferred)

```json
{
  "driver_id": 456,
  "pickup_location": "Kimihurura Roundabout",
  "pickup_lat": -1.9536,
  "pickup_lng": 30.0606,
  "dropoff_location": "Kigali City Tower",
  "dropoff_lat": -1.9441,
  "dropoff_lng": 30.0619,
  "fare": 2500
}
```

Nested alternative (server accepts)

```json
{
  "pickup": { "lat": -1.9536, "lng": 30.0606 },
  "dropoff": { "lat": -1.9441, "lng": 30.0619 }
}
```

Android (Kotlin) permission example
-----------------------------------
Request permission and get last-known/fresh location using `FusedLocationProviderClient`:

```kotlin
// Check permission
if (ContextCompat.checkSelfPermission(this, Manifest.permission.ACCESS_FINE_LOCATION) != PackageManager.PERMISSION_GRANTED) {
  ActivityCompat.requestPermissions(this, arrayOf(Manifest.permission.ACCESS_FINE_LOCATION), LOCATION_REQ)
} else {
  fusedLocationClient.lastLocation.addOnSuccessListener { location ->
    if (location != null) {
      sendRideRequest(pickupLat = location.latitude, pickupLng = location.longitude)
    } else {
      // Start a one-off location request with high accuracy
    }
  }
}
```

iOS (Swift) permission example
------------------------------
Request permission and fetch location with `CLLocationManager`:

```swift
let manager = CLLocationManager()
manager.requestWhenInUseAuthorization()
if CLLocationManager.authorizationStatus() == .authorizedWhenInUse || CLLocationManager.authorizationStatus() == .authorizedAlways {
  manager.requestLocation()
}

func locationManager(_ manager: CLLocationManager, didUpdateLocations locations: [CLLocation]) {
  if let loc = locations.first {
    sendRideRequest(pickupLat: loc.coordinate.latitude, pickupLng: loc.coordinate.longitude)
  }
}
```

Error mapping (UI)
------------------
- Map `422` validation errors mentioning missing coords to a user-friendly dialog: "We couldn't get your location. Please enable Location Services and allow the app to access your location, then try again." Include a button to open Settings and a Retry button.

QA checklist
------------
- [ ] Android: permission GRANTED, GPS ON — ride request succeeds.
- [ ] Android: permission DENIED — show Settings prompt and do not send coords.
- [ ] iOS: permission GRANTED, location ON — ride request succeeds.
- [ ] iOS: denied — show Settings prompt.
- [ ] Test payloads with top-level numeric fields and nested objects.

Notes
-----
Server now tolerates alternate shapes but client-side fixes are recommended for reliability and analytics.
