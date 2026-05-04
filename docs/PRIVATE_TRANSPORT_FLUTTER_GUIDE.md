# Private Transport (CAR & MOTORCYCLE) - Flutter Implementation Guide

> Complete guide for implementing CAR (Scheduled + On-Demand) and MOTORCYCLE (On-Demand only) rides in Flutter

## Table of Contents

1. [Core Concepts](#core-concepts)
2. [API Endpoints](#api-endpoints)
3. [CAR Ride Flow](#car-ride-flow)
4. [MOTORCYCLE Ride Flow](#motorcycle-ride-flow)
5. [Flutter Code Examples](#flutter-code-examples)
6. [Error Handling](#error-handling)
7. [Testing](#testing)

---

## Core Concepts

### Transport Types

| Transport | Travel Mode | Flow | Booking | Trip |
|-----------|------------|------|---------|------|
| **BUS** | SCHEDULED only | Booking → Trip | ✅ Yes | ✅ From Booking |
| **CAR** | SCHEDULED or ON_DEMAND | Booking → Trip (scheduled) or Direct → Trip (on-demand) | ✅ Scheduled only | ✅ Direct (on-demand) |
| **MOTORCYCLE** | ON_DEMAND only | Direct → Trip | ❌ Never | ✅ Always |

### Ride Rules (from API)

Every ride response includes `ride_rules`:

```json
{
  "ride_rules": {
    "can_book": true,           // Can create booking
    "can_request_trip": false,  // Can request direct trip
    "allowed_flow": "BOOKING_ONLY"  // BOOKING_ONLY | TRIP_ONLY | BOTH | NONE
  }
}
```

**Flutter Usage:**

```dart
if (ride.rideRules.canBook) {
  // Show "Book Ride" button
}
if (ride.rideRules.canRequestTrip) {
  // Show "Request Ride Now" button
}
```

---

## API Endpoints

### Passenger Mobile APIs

```http
GET /api/mobile/rides
POST /api/mobile/bookings
POST /api/mobile/trips/request
GET /api/mobile/trips/current
GET /api/mobile/trips/{id}/track
PUT /api/mobile/trips/{id}/cancel
PUT /api/mobile/trips/{id}/complete
```

### Driver Mobile APIs

```http
POST /api/mobile/driver/status
GET /api/mobile/driver/trips/available
POST /api/mobile/driver/trips/{id}/accept
POST /api/mobile/driver/location
PUT /api/mobile/driver/trips/{id}/start
PUT /api/mobile/driver/trips/{id}/complete
PUT /api/mobile/driver/trips/{id}/cancel
```

---

## CAR Ride Flow

### Scenario 1: SCHEDULED CAR (Book → Trip)

```
User selects SCHEDULED CAR ride
    ↓
Shows "Book Ride" button (ride_rules.can_book = true)
    ↓
User enters: seats, pickup, dropoff, special requests
    ↓
POST /api/mobile/bookings
    ↓
Booking created (status: PENDING)
    ↓
User confirms booking
    ↓
POST /api/mobile/trips/request (with booking_id)
    ↓
Trip created (status: PENDING, driver auto-assigned)
    ↓
Show trip details with driver info
```

### Scenario 2: ON_DEMAND CAR (Direct Trip)

```
User enters pickup and dropoff locations
    ↓
Shows "Request Ride Now" button (ride_rules.can_request_trip = true)
    ↓
POST /api/mobile/trips/request (with locations)
    ↓
Trip created (status: PENDING, driver auto-assigned)
    ↓
Show waiting screen with driver location
    ↓
Driver accepts → status becomes ACCEPTED
```

### Flutter Code: CAR Booking

```dart
class CarBookingScreen extends StatefulWidget {
  final Ride ride;
  const CarBookingScreen({required this.ride});

  @override
  State<CarBookingScreen> createState() => _CarBookingScreenState();
}

class _CarBookingScreenState extends State<CarBookingScreen> {
  late final ApiService apiService;
  int selectedSeats = 1;
  bool isLoading = false;

  @override
  void initState() {
    super.initState();
    apiService = ApiService();
  }

  Future<void> _bookRide() async {
    setState(() => isLoading = true);

    try {
      final response = await apiService.post(
        '/api/mobile/bookings',
        data: {
          'ride_id': widget.ride.id,
          'seats': selectedSeats,
        },
      );

      final bookingId = response['data']['id'];
      
      // Convert booking to trip
      final tripResponse = await apiService.post(
        '/api/mobile/trips/request',
        data: {'booking_id': bookingId},
      );

      if (mounted) {
        context.go('/trips/${tripResponse['data']['id']}');
      }
    } catch (e) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text('Booking failed: $e')),
      );
    } finally {
      setState(() => isLoading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Book Car')),
      body: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          // Seats selection
          Text('Select seats', style: Theme.of(context).textTheme.titleMedium),
          Row(
            children: [
              IconButton(
                onPressed: () => setState(() => selectedSeats = max(1, selectedSeats - 1)),
                icon: const Icon(Icons.remove),
              ),
              Text('$selectedSeats', style: const TextStyle(fontSize: 18)),
              IconButton(
                onPressed: () => setState(() => selectedSeats = min(8, selectedSeats + 1)),
                icon: const Icon(Icons.add),
              ),
            ],
          ),
          const SizedBox(height: 24),

          // Book button
          ElevatedButton(
            onPressed: isLoading ? null : _bookRide,
            child: isLoading
                ? const SizedBox(
                    height: 20,
                    width: 20,
                    child: CircularProgressIndicator(strokeWidth: 2),
                  )
                : const Text('Book Ride'),
          ),
        ],
      ),
    );
  }
}
```

---

## MOTORCYCLE Ride Flow

### Scenario: ON_DEMAND MOTORCYCLE (Direct Trip Only)

```
User enters pickup and dropoff locations
    ↓
Shows "Request Boda Now" button (only option)
    ↓
POST /api/mobile/trips/request (with locations)
    ↓
Trip created (status: PENDING, driver auto-assigned)
    ↓
Show "Driver on the way" screen
    ↓
Driver location updates in real-time
    ↓
Driver accepts → status becomes ACCEPTED
    ↓
Driver starts trip → status becomes STARTED
```

## Supabase Realtime Integration

The backend now publishes realtime updates through Supabase Realtime using an abstraction layer in `App\Services\Realtime\RealtimeGateway`.

Channels are named by user and trip identity:

- `trip:{trip_id}` for trip lifecycle and location updates
- `driver:{driver_id}` for driver request notifications
- `passenger:{passenger_id}` for passenger-specific updates

### Flutter Subscription Example

```dart
final supabase = Supabase.instance.client;

final tripChannel = supabase.channel('trip:\$tripId')
  .onBroadcast(
    event: 'driver.location.updated',
    callback: (payload) {
      final lat = payload['lat'];
      final lng = payload['lng'];
      final timestamp = payload['timestamp'];
      // Update UI with live driver position.
    },
  )
  .onBroadcast(
    event: 'trip.started',
    callback: (payload) {
      // Trip has started.
    },
  )
  .onBroadcast(
    event: 'trip.completed',
    callback: (payload) {
      // Trip is complete.
    },
  )
  .subscribe();

final driverChannel = supabase.channel('driver:\$driverId')
  .onBroadcast(
    event: 'trip.request',
    callback: (payload) {
      final tripId = payload['trip_id'];
      final pickup = payload['pickup'];
      // Show incoming trip request on the driver app.
    },
  )
  .subscribe();
```

### Notes

- The backend sends `driver.location.updated` for live location changes.
- `trip.request` is emitted when a driver is matched to a trip request.
- `trip.started` and `trip.completed` are emitted on trip lifecycle transitions.
- This removes the need for polling and keeps the app synced with the backend in real time.

### Security

Supabase channels are namespace-scoped by ID, so the backend can restrict broadcasts to the correct passenger or driver channel. The same channel names are used by the backend and Flutter client.

### Price / distance relationship

No pricing logic changed in this realtime integration. Price remains calculated from trip distance and fare fields as represented by the existing ride/trip model flow.

### Flutter Code: MOTORCYCLE Request

```dart
class MotorcycleRequestScreen extends StatefulWidget {
  const MotorcycleRequestScreen({Key? key}) : super(key: key);

  @override
  State<MotorcycleRequestScreen> createState() => _MotorcycleRequestScreenState();
}

class _MotorcycleRequestScreenState extends State<MotorcycleRequestScreen> {
  late final ApiService apiService;
  
  String pickupLocation = '';
  String dropoffLocation = '';
  double? pickupLat, pickupLng;
  double? dropoffLat, dropoffLng;
  bool isLoading = false;

  @override
  void initState() {
    super.initState();
    apiService = ApiService();
    _initializeLocation();
  }

  Future<void> _initializeLocation() async {
    final position = await Geolocator.getCurrentPosition();
    setState(() {
      pickupLat = position.latitude;
      pickupLng = position.longitude;
      pickupLocation = await _getAddressFromCoordinates(pickupLat!, pickupLng!);
    });
  }

  Future<void> _requestMotorcycle() async {
    if (dropoffLocation.isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Enter dropoff location')),
      );
      return;
    }

    // Get dropoff coordinates
    final dropoffCoords = await _getCoordinatesFromAddress(dropoffLocation);
    dropoffLat = dropoffCoords['lat'];
    dropoffLng = dropoffCoords['lng'];

    setState(() => isLoading = true);

    try {
      final response = await apiService.post(
        '/api/mobile/trips/request',
        data: {
          'pickup_location': pickupLocation,
          'pickup_lat': pickupLat,
          'pickup_lng': pickupLng,
          'dropoff_location': dropoffLocation,
          'dropoff_lat': dropoffLat,
          'dropoff_lng': dropoffLng,
        },
      );

      final tripId = response['data']['id'];
      
      if (mounted) {
        context.go('/trips/$tripId');
      }
    } catch (e) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text('Request failed: $e')),
      );
    } finally {
      setState(() => isLoading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Request Motorcycle')),
      body: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          // Pickup (current location)
          Card(
            child: ListTile(
              leading: const Icon(Icons.location_on, color: Colors.green),
              title: const Text('Pickup'),
              subtitle: Text(pickupLocation),
              trailing: const Icon(Icons.check_circle, color: Colors.green),
            ),
          ),
          const SizedBox(height: 12),

          // Dropoff input
          TextFormField(
            onChanged: (value) => setState(() => dropoffLocation = value),
            decoration: InputDecoration(
              labelText: const Text('Dropoff Location'),
              prefixIcon: const Icon(Icons.location_on_outlined),
              border: const OutlineInputBorder(),
            ),
          ),
          const SizedBox(height: 24),

          // Request button
          ElevatedButton(
            onPressed: isLoading ? null : _requestMotorcycle,
            child: isLoading
                ? const SizedBox(
                    height: 20,
                    width: 20,
                    child: CircularProgressIndicator(strokeWidth: 2),
                  )
                : const Text('Request Motorcycle'),
          ),
        ],
| Code | Status | Meaning | Action |
|------|--------|---------|--------|
| `BOOKING_NOT_ALLOWED_FOR_TRAVEL_MODE` | 422 | Ride doesn't support booking | Use trip request instead |
| `TRIP_NOT_ALLOWED_FOR_TRAVEL_MODE` | 422 | Ride doesn't support direct trip | Use booking instead |
| `BUS_MUST_BE_SCHEDULED` | 422 | BUS rides must be SCHEDULED | Show error, cannot proceed |
| `MOTORCYCLE_MUST_BE_ON_DEMAND` | 422 | MOTORCYCLE must be ON_DEMAND | Show error, cannot proceed |
| `NO_DRIVER_AVAILABLE` | 422 | No driver to assign | Show retry option |
| `INSUFFICIENT_SEATS` | 422 | Not enough seats | Show available count |

### Error Handling in Flutter

```dart
Future<void> createTrip() async {
  try {
    final response = await apiService.post('/api/v1/passenger/trips', data: {
      ...tripData,
    });
    
    // Success
    handleTrip(response['data']);
  } on DioException catch (e) {
    final errorCode = e.response?.data['error_code'];
    
    switch (errorCode) {
      case 'TRIP_NOT_ALLOWED_FOR_TRAVEL_MODE':
        showError('This ride requires booking. Please use the booking flow.');
        break;
      case 'NO_DRIVER_AVAILABLE':
        showError('No driver available. Please try again.');
        break;
      case 'INSUFFICIENT_SEATS':
        showError('Not enough seats available.');
        break;
      default:
        showError('Request failed: ${e.message}');
    }
  }
}
```

---

## Testing

Run the test suites:

```bash
php artisan test tests/Feature/PassengerApiTest.php
php artisan test tests/Feature/DriverApiTest.php
```

Tests verify:
- ✅ Mobile API endpoints return correct response structure
- ✅ Cannot book ON_DEMAND rides
- ✅ Cannot request trip for SCHEDULED-only rides
- ✅ Driver status updates work
- ✅ Trip acceptance and state transitions
- ✅ Location updates are stored
- ✅ Vehicle compatibility enforcement
- ✅ API returns standardized error responses

---

## Summary

**Key Takeaways:**

1. **Check `ride_rules` in API response** - Determines allowed actions
2. **CAR is flexible** - Can be SCHEDULED (booking) or ON_DEMAND (trip)
3. **MOTORCYCLE is ON_DEMAND only** - Always direct trip, never booking
4. **Driver auto-assigned** - Happens when trip is created
5. **Locations are required** - Trip must always have pickup and dropoff
6. **State machine matters** - Follow PENDING → ACCEPTED → STARTED → COMPLETED flow

---

*Last updated: May 4, 2026*
