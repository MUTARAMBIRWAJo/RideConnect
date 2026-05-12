# RideConnect Driver Trip Actions - API Reference

## Trip Management APIs for Flutter Drivers

### 1. Accept Trip Request

**Endpoint:** `POST /api/mobile/drivers/trips/{id}/accept`

**Request:**
```bash
curl -X POST https://api.rideconnect.local/api/mobile/drivers/trips/4/accept \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json"
```

**Success Response (200):**
```json
{
  "status": "success",
  "data": {
    "trip_id": "4",
    "trip_state": "ACCEPTED",
    "driver_id": "12",
    "accepted_at": "2025-05-12T08:15:30.000Z"
  }
}
```

**Error Responses:**

| Status | Code | Message | Handling |
|--------|------|---------|----------|
| 404 | TRIP_NOT_FOUND | Trip #4 does not exist | Show "Trip no longer available" |
| 409 | TRIP_ALREADY_ASSIGNED | Another driver already accepted | Show "Another driver took it" |
| 409 | TRIP_NOT_AVAILABLE | Trip status: COMPLETED | Show "Trip already completed" |
| 404 | DRIVER_NOT_FOUND | Driver profile not found | Show "Complete driver registration" |
| 422 | POLICY_VIOLATION | [custom message] | Show policy-specific error |
| 409 | TRIP_RACE_CONDITION | Another driver just accepted | Show "Try another trip" |

### 2. Reject Trip Request

**Endpoint:** `POST /api/mobile/drivers/trips/{id}/reject`

**Request:**
```bash
curl -X POST https://api.rideconnect.local/api/mobile/drivers/trips/4/reject \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json"
```

**Success Response (200):**
```json
{
  "status": "success",
  "message": "Trip rejected successfully.",
  "data": {
    "trip_id": "4",
    "total_rejections": 3
  }
}
```

**Error Responses:**

| Status | Code | Message | Handling |
|--------|------|---------|----------|
| 404 | TRIP_NOT_FOUND | Trip #4 does not exist | Show "Trip not found" |
| 409 | TRIP_NOT_AVAILABLE | Trip status: ACCEPTED | Show "Trip already accepted" |
| 404 | DRIVER_NOT_FOUND | Driver profile not found | Show "Complete registration" |

---

## Flutter Integration Examples

### Example 1: Simple Accept Button

```dart
class AcceptTripButton extends StatefulWidget {
  final int tripId;
  
  @override
  State<AcceptTripButton> createState() => _AcceptTripButtonState();
}

class _AcceptTripButtonState extends State<AcceptTripButton> {
  bool _isLoading = false;

  @override
  Widget build(BuildContext context) {
    return ElevatedButton(
      onPressed: _isLoading ? null : _acceptTrip,
      child: _isLoading 
        ? const CircularProgressIndicator(valueColor: AlwaysStoppedAnimation(Colors.white))
        : const Text('Accept'),
    );
  }

  Future<void> _acceptTrip() async {
    setState(() => _isLoading = true);

    try {
      final tripService = context.read<TripService>();
      final result = await tripService.acceptTrip(widget.tripId);
      
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('Trip ${result.tripId} accepted!')),
        );
      }
    } on TripAcceptanceException catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text(e.error.userFriendlyMessage)),
        );
      }
    } finally {
      setState(() => _isLoading = false);
    }
  }
}
```

### Example 2: Accept with Retry Logic

```dart
Future<void> acceptTripWithRetry(TripService service, int tripId, {int maxRetries = 3}) async {
  for (int attempt = 1; attempt <= maxRetries; attempt++) {
    try {
      final result = await service.acceptTrip(tripId);
      print('Trip accepted: ${result.tripId}');
      return;
    } on TripAcceptanceException catch (e) {
      if (e.error.type == 'TRIP_RACE_CONDITION' && attempt < maxRetries) {
        // Race condition - try another trip
        print('Attempt $attempt: Race condition detected, suggesting alternative...');
        await Future.delayed(Duration(seconds: attempt)); // Exponential backoff
        // Could fetch new trips here
        continue;
      }
      rethrow;
    }
  }
}
```

### Example 3: Error-Specific UI Response

```dart
void _handleTripError(TripErrorResponse error) {
  final message = error.userFriendlyMessage;
  final color = error.httpCode == 409 ? Colors.orange : Colors.red;
  final action = error.type == 'TRIP_RACE_CONDITION' 
    ? 'Try another trip' 
    : 'Dismiss';

  showDialog(
    context: context,
    builder: (context) => AlertDialog(
      icon: Icon(Icons.error_outline, color: color),
      title: const Text('Trip Action Failed'),
      content: Text(message),
      actions: [
        TextButton(
          onPressed: () => Navigator.pop(context),
          child: Text(action),
        ),
        if (error.type == 'TRIP_RACE_CONDITION')
          TextButton(
            onPressed: () {
              Navigator.pop(context);
              _showAlternativeTrips();
            },
            child: const Text('View Other Trips'),
          ),
      ],
    ),
  );
}
```

### Example 4: Trip List with Accept/Reject

```dart
class TripsListView extends StatelessWidget {
  final List<Trip> trips;

  @override
  Widget build(BuildContext context) {
    return ListView.builder(
      itemCount: trips.length,
      itemBuilder: (context, index) {
        final trip = trips[index];
        return TripCard(
          trip: trip,
          onAccept: () => _acceptTrip(context, trip.id),
          onReject: () => _rejectTrip(context, trip.id),
        );
      },
    );
  }

  Future<void> _acceptTrip(BuildContext context, int tripId) async {
    final service = context.read<TripService>();
    try {
      await service.acceptTrip(tripId);
      // Trip accepted - remove from list or navigate
      if (context.mounted) {
        Navigator.of(context).pop(tripId); // Return accepted trip ID
      }
    } on TripAcceptanceException catch (e) {
      _showError(context, e.error.userFriendlyMessage);
    }
  }

  Future<void> _rejectTrip(BuildContext context, int tripId) async {
    final confirmed = await showConfirmDialog(context, 'Reject this trip?');
    if (confirmed != true) return;

    final service = context.read<TripService>();
    try {
      await service.rejectTrip(tripId);
      // Trip rejected - remove from list and fetch more
      if (context.mounted) {
        _refreshTrips(context);
      }
    } on TripRejectionException catch (e) {
      _showError(context, e.error.userFriendlyMessage);
    }
  }

  void _showError(BuildContext context, String message) {
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(content: Text(message), backgroundColor: Colors.red),
    );
  }

  Future<void> _refreshTrips(BuildContext context) {
    // Fetch fresh available trips
    // Implementation depends on your state management
  }
}
```

---

## HTTP Status Code Reference

| Code | Meaning | Retry? | User Action |
|------|---------|--------|-------------|
| 200 | Success | No | Proceed |
| 400 | Bad request | No | Check input data |
| 404 | Not found | No | Trip/driver doesn't exist |
| 409 | Conflict | Sometimes* | Another driver acted first |
| 422 | Validation error | No | Check policy/requirements |
| 500 | Server error | Yes | Retry after delay |
| Network timeout | Connection issue | Yes | Check connection & retry |

*409 for TRIP_RACE_CONDITION should trigger automatic refresh

---

## Debugging Checklist

- [ ] Is Authorization header present with valid JWT token?
- [ ] Is trip ID valid and numeric?
- [ ] Is driver profile linked to user?
- [ ] Does trip exist in database?
- [ ] Is trip still in PENDING status?
- [ ] Is network connection stable?
- [ ] Are error responses being parsed correctly?

---

## Database Audit Queries

### Check Trip Rejections
```sql
SELECT 
  tr.trip_id,
  tr.driver_id,
  u.name as driver_name,
  COUNT(*) as rejection_count
FROM trip_rejections tr
JOIN users u ON tr.driver_id = u.id
GROUP BY tr.trip_id, tr.driver_id
ORDER BY rejection_count DESC;
```

### Monitor Acceptance Performance
```sql
SELECT 
  HOUR(accepted_at) as hour,
  COUNT(*) as accepted_trips,
  AVG(TIMESTAMPDIFF(SECOND, requested_at, accepted_at)) as avg_response_seconds
FROM trips
WHERE accepted_at IS NOT NULL
GROUP BY HOUR(accepted_at)
ORDER BY hour DESC;
```

### Find Problematic Trips
```sql
SELECT 
  id,
  requested_at,
  status,
  rejected_drivers_count,
  driver_id,
  (SELECT COUNT(*) FROM trip_rejections WHERE trip_id = trips.id) as actual_rejections
FROM trips
WHERE rejected_drivers_count > 5
ORDER BY rejected_drivers_count DESC;
```

---

**API Version:** 2.0  
**Last Updated:** May 12, 2025  
**SDK Support:** Dart/Flutter 3.0+, iOS 11+, Android 6+
