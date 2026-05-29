# Flutter RideConnect Integration - Setup Guide

**Last Updated:** May 29, 2026

## Production API URLs

- **Backend API:** `https://rideconnect-emp0.onrender.com`
- **ML/AI Service:** `https://ml-service-j72g.onrender.com`
- **Mobile API Base:** `https://rideconnect-emp0.onrender.com/api/v1/mobile`

---

## Quick Start

### 1. Copy Configuration Files

Copy these files from the repository to your Flutter project:

```bash
# Copy configuration
cp lib/config/api_config.dart <your-flutter-project>/lib/config/
cp lib/config/dio_config.dart <your-flutter-project>/lib/config/

# Copy services
cp lib/services/trip_service.dart <your-flutter-project>/lib/services/
cp lib/services/driver_matching_service.dart <your-flutter-project>/lib/services/

# Copy utilities
cp lib/utils/validation_helper.dart <your-flutter-project>/lib/utils/
cp lib/exceptions/trip_exceptions.dart <your-flutter-project>/lib/exceptions/
```

### 2. Update Dependencies

Add to your `pubspec.yaml`:

```yaml
dependencies:
  flutter:
    sdk: flutter
  dio: ^5.0.0
  provider: ^6.0.0
```

Run:
```bash
flutter pub get
```

### 3. Initialize in main.dart

```dart
import 'lib/config/dio_config.dart';
import 'lib/config/api_config.dart';

void main() {
  // Initialize Dio with production URLs
  DioServiceLocator().initialize(authToken: null);
  
  runApp(MyApp());
}

class MyApp extends StatelessWidget {
  @override
  Widget build(BuildContext context) {
    return MaterialApp(
      title: 'RideConnect',
      home: MyHomePage(),
    );
  }
}
```

### 4. Use Services in Your Widgets

```dart
import 'lib/services/trip_service.dart';
import 'lib/services/driver_matching_service.dart';

class TripPage extends StatefulWidget {
  @override
  State<TripPage> createState() => _TripPageState();
}

class _TripPageState extends State<TripPage> {
  late TripService _tripService;
  late DriverMatchingService _driverService;

  @override
  void initState() {
    super.initState();
    
    final dio = DioServiceLocator().getMainDio();
    _tripService = TripService(dio: dio);
    _driverService = DriverMatchingService(dio: dio);
  }

  Future<void> _loadDrivers() async {
    try {
      final response = await _driverService.getAvailableDrivers(
        latitude: -1.2866,
        longitude: 36.7753,
        transportType: 'motor_vehicle',
      );

      if (response.isEmpty) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text(response.getEmptyStateMessage())),
        );
      } else {
        // Use response.drivers
      }
    } catch (e) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text('Error: $e')),
      );
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: Text('Available Drivers')),
      body: ElevatedButton(
        onPressed: _loadDrivers,
        child: Text('Load Drivers'),
      ),
    );
  }
}
```

---

## API Endpoints

### Trip Endpoints

| Method | Endpoint | Description |
|--------|----------|-------------|
| `GET` | `/api/v1/mobile/trips/{id}` | Get trip details |
| `POST` | `/api/v1/mobile/trips/request` | Request new trip |
| `GET` | `/api/v1/mobile/trips/current` | Get current trip |
| `GET` | `/api/v1/mobile/trips/{id}/status` | Get trip status |
| `GET` | `/api/v1/mobile/trips/{id}/track` | Track trip/driver |
| `PUT` | `/api/v1/mobile/trips/{id}/cancel` | Cancel trip |
| `POST` | `/api/v1/mobile/drivers/trips/{id}/accept` | Accept trip (driver) |
| `PUT` | `/api/v1/mobile/drivers/trips/{id}/start` | Start trip (driver) |
| `PUT` | `/api/v1/mobile/drivers/trips/{id}/complete` | Complete trip (driver) |

### Driver Endpoints

| Method | Endpoint | Description |
|--------|----------|-------------|
| `GET` | `/api/v1/mobile/drivers/match` | Get available drivers |
| `GET` | `/api/v1/mobile/trips/{id}/matching-session` | Get matching session |

### Authentication Endpoints

| Method | Endpoint | Description |
|--------|----------|-------------|
| `POST` | `/api/v1/auth/register` | Register new user |
| `POST` | `/api/v1/auth/mobile/login` | Login (email/phone) |
| `GET` | `/api/v1/auth/profile` | Get user profile |
| `POST` | `/api/v1/auth/logout` | Logout |

---

## Error Handling

### Trip ID Validation

Always validate trip IDs before using them:

```dart
import 'lib/utils/validation_helper.dart';

// Validate manually
if (!ValidationHelper.isValidTripId(tripId)) {
  print('Invalid trip ID');
  return;
}

// Or assert (throws exception if invalid)
try {
  final validId = ValidationHelper.assertValidTripId(tripId);
} on ArgumentError catch (e) {
  print('Error: ${e.message}');
}
```

### Handle Empty Driver Responses

```dart
final response = await driverService.getAvailableDrivers(
  latitude: lat,
  longitude: lng,
);

if (response.isEmpty) {
  print(response.getEmptyStateMessage());
  // Show retry UI
} else {
  // Display drivers
}
```

### Custom Exceptions

```dart
import 'lib/exceptions/trip_exceptions.dart';

try {
  await tripService.acceptTrip(tripId);
} on TripNotFoundException catch (e) {
  print('Trip not found: ${e.message}');
} on InvalidTripIdException catch (e) {
  print('Invalid trip ID: ${e.message}');
} on TripException catch (e) {
  print('Trip error: ${e.message}');
}
```

---

## Configuration

### Update Auth Token

When user logs in:

```dart
final authToken = response.token; // From login API

// Update Dio clients with new token
DioServiceLocator().updateAuthToken(authToken);
```

### Clear Auth Token

When user logs out:

```dart
// Remove token from Dio clients
DioServiceLocator().clearAuthToken();
```

### Change Environment (if needed)

```dart
// For staging/development
EnvironmentConfig.current = Environment.staging;
```

---

## Testing

### Unit Tests

```dart
// test/services/trip_service_test.dart
import 'package:flutter_test/flutter_test.dart';
import 'package:mockito/mockito.dart';

void main() {
  group('TripService', () {
    test('validates trip ID before API call', () {
      expect(
        () => tripService.acceptTrip(0),
        throwsA(isA<ArgumentError>()),
      );
    });

    test('returns trip response on success', () async {
      // Mock response
      when(dio.post(...)).thenAnswer(
        (_) async => Response(data: {'data': {...}}, statusCode: 200),
      );

      final response = await tripService.acceptTrip(123);
      expect(response.tripId, 123);
    });
  });
}
```

### Widget Tests

```dart
void main() {
  testWidgets('Shows empty state when no drivers', (WidgetTester tester) async {
    // Mock empty driver response
    when(driverService.getAvailableDrivers(...))
        .thenAnswer((_) async => DriverMatchingResponse(
          drivers: [],
          hasAvailableDrivers: false,
        ));

    await tester.pumpWidget(MyApp());
    
    expect(find.text('No drivers available'), findsOneWidget);
  });
}
```

---

## Logging & Debugging

### Enable API Logging

```dart
// Dio client is created with logging enabled by default
// Check console output for:
// 🌐 [API REQUEST]
// ✅ [API RESPONSE]
// ❌ [API ERROR]
```

### Check Request/Response

```dart
// Logs will show:
// 🌐 [API REQUEST]
//    URL: https://rideconnect-emp0.onrender.com/api/v1/mobile/drivers/match
//    Method: GET
//    Headers: {Authorization: Bearer token...}

// ✅ [API RESPONSE]
//    Status: 200
//    Data: {"data": [...]}
```

---

## Common Issues & Solutions

### Issue: "No drivers available" always shows

**Solution:**
1. Check API logs in Render dashboard
2. Verify latitude/longitude are valid
3. Ensure auth token is valid
4. Check network connectivity

### Issue: Trip ID = 0 being sent

**Solution:**
1. Use `ValidationHelper.assertValidTripId()` before API calls
2. Validate trip IDs from all sources (database, cache, notifications)
3. Check how trip IDs are generated/stored in your app

### Issue: 404 errors on valid endpoints

**Solution:**
1. Verify you're using correct base URL: `https://rideconnect-emp0.onrender.com`
2. Check API version path: `/api/v1/`
3. Check endpoint path format in `ApiEndpoints` class

### Issue: Connection timeout

**Solution:**
1. Check network connectivity
2. Verify API server is running (check Render dashboard)
3. Increase timeout in `ApiConfig` if needed
4. Check firewall/proxy settings

---

## Support

For issues or questions:
1. Check Flutter integration guide: `FLUTTER_API_INTEGRATION_FIXES.md`
2. Review API documentation: API contract document
3. Check API logs in Render dashboard
4. Contact backend team for API issues

---

## Files Reference

```
lib/
├── config/
│   ├── api_config.dart           # API URLs and endpoints
│   └── dio_config.dart           # HTTP client configuration
├── services/
│   ├── trip_service.dart         # Trip operations
│   └── driver_matching_service.dart # Driver matching
├── utils/
│   └── validation_helper.dart    # Trip ID validation
└── exceptions/
    └── trip_exceptions.dart      # Custom exceptions
```

---

## Deployment Checklist

- [ ] Copy all configuration files to project
- [ ] Update `pubspec.yaml` with dependencies
- [ ] Initialize `DioServiceLocator` in `main()`
- [ ] Update all API calls to use new services
- [ ] Add validation for trip IDs
- [ ] Test with empty driver list scenarios
- [ ] Test authentication token handling
- [ ] Test error scenarios
- [ ] Verify all endpoints are accessible
- [ ] Check logs for any errors
- [ ] Deploy to test environment
- [ ] Conduct QA testing
- [ ] Deploy to production

---

**Ready for integration! ✅**
