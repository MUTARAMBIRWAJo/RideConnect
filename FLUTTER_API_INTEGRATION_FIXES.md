# Flutter Mobile Integration Fixes and Best Practices

## Production Configuration

### API Base URLs
- **Laravel Backend API:** `https://rideconnect-emp0.onrender.com`
- **ML/AI Service:** `https://ml-service-j72g.onrender.com`
- **Mobile API Endpoints:** `https://rideconnect-emp0.onrender.com/api/v1/mobile`

### Environment Setup
Add to your Flutter `pubspec.yaml`:
```yaml
environment:
  sdk: ">=3.0.0 <4.0.0"

dependencies:
  dio: ^5.0.0
  flutter:
    sdk: flutter
```

Update your API configuration:
```dart
// lib/config/api_config.dart
class ApiConfig {
  static const String baseUrl = 'https://rideconnect-emp0.onrender.com';
  static const String mlServiceUrl = 'https://ml-service-j72g.onrender.com';
  static const String mobileApiPath = '/api/v1/mobile';
}
```

---

## Overview
This document provides fixes and guidelines for Flutter mobile app integration with the RideConnect API, addressing issues with:
1. Invalid trip IDs (specifically ID = 0)
2. Empty matching session/drivers responses
3. API resilience and error handling

---

## Issue 1: Avoid Sending 0 as Trip ID

### Problem
The Flutter app may send trip_id = 0 in some edge cases, which will now be rejected by the backend API with proper validation. The backend has been updated to gracefully handle invalid IDs with a 404 response instead of internal errors.

### Solution

#### 1.1 Add Validation Helper
Create a utility function to validate trip IDs before making API calls:

```dart
// File: lib/utils/validation_helper.dart

class ValidationHelper {
  /// Validates that a trip ID is valid (not null, not 0, positive integer)
  static bool isValidTripId(int? tripId) {
    return tripId != null && tripId > 0;
  }

  /// Safely parse trip ID from various sources
  static int? parseTripId(dynamic tripId) {
    if (tripId == null) return null;
    
    if (tripId is int) {
      return tripId > 0 ? tripId : null;
    }
    
    if (tripId is String) {
      try {
        final parsed = int.parse(tripId);
        return parsed > 0 ? parsed : null;
      } catch (e) {
        return null;
      }
    }
    
    return null;
  }

  /// Asserts that a trip ID is valid, throws if not
  static int assertValidTripId(int? tripId, {String? message}) {
    if (!isValidTripId(tripId)) {
      throw ArgumentError(
        message ?? 'Trip ID must be a positive integer, got: $tripId',
      );
    }
    return tripId!;
  }
}
```

#### 1.2 Update Trip Service

Update the TripService to validate trip IDs before making API calls:

```dart
// File: lib/services/trip_service.dart

class TripService {
  final Dio dio;
  static const String baseUrl = 'https://rideconnect-emp0.onrender.com/api/v1/mobile';

  TripService({required this.dio});

  /// Accept a trip request - with trip ID validation
  Future<TripResponse> acceptTrip(int tripId) async {
    // Validate trip ID before sending request
    final validTripId = ValidationHelper.assertValidTripId(tripId);
    
    try {
      final response = await dio.post(
        '$baseUrl/drivers/trips/$validTripId/accept',
        options: Options(
          validateStatus: (status) => status != null && status < 500,
        ),
      );

      if (response.statusCode == 200) {
        return TripResponse.fromJson(response.data);
      }

      if (response.statusCode == 404) {
        throw TripNotFoundException('Trip $validTripId not found or was cancelled');
      }

      return _handleErrorResponse(response);
    } catch (e) {
      rethrow;
    }
  }

  /// Get trip details - with trip ID validation
  Future<TripResponse> getTripDetails(int tripId) async {
    final validTripId = ValidationHelper.assertValidTripId(tripId);
    
    try {
      final response = await dio.get(
        '$baseUrl/trips/$validTripId',
        options: Options(
          validateStatus: (status) => status != null && status < 500,
        ),
      );

      if (response.statusCode == 200) {
        return TripResponse.fromJson(response.data);
      }

      if (response.statusCode == 404) {
        throw TripNotFoundException('Trip $validTripId not found');
      }

      return _handleErrorResponse(response);
    } catch (e) {
      rethrow;
    }
  }

  /// Start a trip - with trip ID validation
  Future<TripResponse> startTrip(int tripId) async {
    final validTripId = ValidationHelper.assertValidTripId(tripId);
    
    try {
      final response = await dio.put(
        '$baseUrl/drivers/trips/$validTripId/start',
        options: Options(
          validateStatus: (status) => status != null && status < 500,
        ),
      );

      if (response.statusCode == 200) {
        return TripResponse.fromJson(response.data);
      }

      if (response.statusCode == 404) {
        throw TripNotFoundException('Trip $validTripId not found');
      }

      return _handleErrorResponse(response);
    } catch (e) {
      rethrow;
    }
  }

  /// Complete a trip - with trip ID validation
  Future<TripResponse> completeTrip(int tripId) async {
    final validTripId = ValidationHelper.assertValidTripId(tripId);
    
    try {
      final response = await dio.put(
        '$baseUrl/drivers/trips/$validTripId/complete',
        options: Options(
          validateStatus: (status) => status != null && status < 500,
        ),
      );

      if (response.statusCode == 200) {
        return TripResponse.fromJson(response.data);
      }

      if (response.statusCode == 404) {
        throw TripNotFoundException('Trip $validTripId not found');
      }

      return _handleErrorResponse(response);
    } catch (e) {
      rethrow;
    }
  }

  /// Cancel a trip - with trip ID validation
  Future<void> cancelTrip(int tripId, {String reason = 'User cancelled'}) async {
    final validTripId = ValidationHelper.assertValidTripId(tripId);
    
    try {
      final response = await dio.put(
        '$baseUrl/trips/$validTripId/cancel',
        data: {'reason': reason},
        options: Options(
          validateStatus: (status) => status != null && status < 500,
        ),
      );

      if (response.statusCode == 200) {
        return;
      }

      if (response.statusCode == 404) {
        throw TripNotFoundException('Trip $validTripId not found');
      }

      _handleErrorResponse(response);
    } catch (e) {
      rethrow;
    }
  }
}
```

#### 1.3 Update UI Layer

Update UI components to validate trip IDs before passing to the service:

```dart
// File: lib/screens/trip_notification_screen.dart

class _TripNotificationWidgetState extends State<TripNotificationWidget> {
  bool _isLoading = false;
  String? _errorMessage;

  @override
  void initState() {
    super.initState();
    // Validate trip ID on init
    if (!ValidationHelper.isValidTripId(widget.tripId)) {
      _errorMessage = 'Invalid trip ID received. Please try again.';
    }
  }

  Future<void> _acceptTrip(BuildContext context) async {
    // Validate trip ID before making API call
    if (!ValidationHelper.isValidTripId(widget.tripId)) {
      _showError('Invalid trip ID. Cannot proceed.');
      return;
    }

    setState(() => _isLoading = true);

    try {
      final tripService = Provider.of<TripService>(context, listen: false);
      final response = await tripService.acceptTrip(widget.tripId);

      if (mounted) {
        _showSuccess('Trip accepted!');
        // Navigate to trip in progress screen
        Navigator.pushReplacementNamed(
          context,
          '/trip-in-progress',
          arguments: response.tripId,
        );
      }
    } on TripNotFoundException catch (e) {
      _showError('This trip is no longer available: ${e.message}');
    } on ArgumentError catch (e) {
      _showError('Invalid trip information: ${e.message}');
    } catch (e) {
      _showError('Failed to accept trip: $e');
    } finally {
      if (mounted) {
        setState(() => _isLoading = false);
      }
    }
  }

  void _showError(String message) {
    setState(() => _errorMessage = message);
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        content: Text(message),
        backgroundColor: Colors.red,
        duration: Duration(seconds: 4),
      ),
    );
  }

  void _showSuccess(String message) {
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        content: Text(message),
        backgroundColor: Colors.green,
      ),
    );
  }
}
```

---

## Issue 2: Handle Empty Matching Session/Drivers Response

### Problem
When the `/api/v1/mobile/drivers/match` endpoint returns an empty list of drivers or when the matching session has no available drivers, the Flutter UI needs to handle this gracefully without crashing.

### Solution

#### 2.1 Add Model for Empty Response Handling

```dart
// File: lib/models/driver_matching_response.dart

class DriverMatchingResponse {
  final List<AvailableDriver> drivers;
  final String? matchingSessionId;
  final bool hasAvailableDrivers;
  final String? noDriversReason;

  DriverMatchingResponse({
    required this.drivers,
    this.matchingSessionId,
    required this.hasAvailableDrivers,
    this.noDriversReason,
  });

  factory DriverMatchingResponse.fromJson(Map<String, dynamic> json) {
    final drivers = (json['data'] as List?)
        ?.map((d) => AvailableDriver.fromJson(d))
        .toList() ?? [];

    return DriverMatchingResponse(
      drivers: drivers,
      matchingSessionId: json['matching_session_id'] as String?,
      hasAvailableDrivers: drivers.isNotEmpty,
      noDriversReason: drivers.isEmpty 
          ? 'No drivers currently available in your area'
          : null,
    );
  }

  /// Check if response is effectively empty
  bool get isEmpty => drivers.isEmpty;

  /// Get appropriate message for empty response
  String getEmptyStateMessage() {
    if (isEmpty) {
      return noDriversReason ?? 'No drivers available at the moment. Please try again later.';
    }
    return '';
  }
}

class AvailableDriver {
  final int id;
  final String name;
  final double rating;
  final String vehicleType;
  final double distance;
  final int eta; // minutes
  final String vehicleNumber;

  AvailableDriver({
    required this.id,
    required this.name,
    required this.rating,
    required this.vehicleType,
    required this.distance,
    required this.eta,
    required this.vehicleNumber,
  });

  factory AvailableDriver.fromJson(Map<String, dynamic> json) {
    return AvailableDriver(
      id: json['id'] ?? 0,
      name: json['name'] ?? 'Unknown Driver',
      rating: (json['rating'] ?? 0.0).toDouble(),
      vehicleType: json['vehicle_type'] ?? 'Unknown',
      distance: (json['distance'] ?? 0.0).toDouble(),
      eta: json['eta'] ?? 0,
      vehicleNumber: json['vehicle_number'] ?? '',
    );
  }
}
```

#### 2.2 Update Driver Matching Service

```dart
// File: lib/services/driver_matching_service.dart

class DriverMatchingService {
  final Dio dio;
  static const String baseUrl = 'https://rideconnect-emp0.onrender.com/api/v1/mobile';

  DriverMatchingService({required this.dio});

  /// Fetch available drivers for matching
  /// Gracefully handles empty driver list
  Future<DriverMatchingResponse> getAvailableDrivers({
    required double latitude,
    required double longitude,
    String? transportType,
    int maxResults = 10,
  }) async {
    try {
      final response = await dio.get(
        '$baseUrl/drivers/match',
        queryParameters: {
          'lat': latitude,
          'lng': longitude,
          if (transportType != null) 'transport_type': transportType,
          'max_results': maxResults,
        },
        options: Options(
          validateStatus: (status) => status != null && status < 500,
        ),
      );

      if (response.statusCode == 200) {
        final matchingResponse = DriverMatchingResponse.fromJson(response.data);
        
        // Handle empty driver list gracefully
        if (matchingResponse.isEmpty) {
          return matchingResponse; // Return with empty drivers but valid response
        }
        
        return matchingResponse;
      }

      if (response.statusCode == 404 || response.statusCode == 422) {
        // Return empty response instead of throwing error
        return DriverMatchingResponse(
          drivers: [],
          matchingSessionId: null,
          hasAvailableDrivers: false,
          noDriversReason: 'No drivers found in your area. Please try again.',
        );
      }

      throw Exception('Failed to fetch drivers: ${response.statusCode}');
    } catch (e) {
      // Return empty response on error instead of crashing
      return DriverMatchingResponse(
        drivers: [],
        matchingSessionId: null,
        hasAvailableDrivers: false,
        noDriversReason: 'Unable to connect. Please check your internet and try again.',
      );
    }
  }

  /// Get matching session details
  /// Handles empty/null session gracefully
  Future<MatchingSessionResponse?> getMatchingSession(String sessionId) async {
    if (sessionId.isEmpty) {
      return null; // Gracefully return null for invalid session
    }

    try {
      final response = await dio.get(
        '$baseUrl/trips/matching-session/$sessionId',
        options: Options(
          validateStatus: (status) => status != null && status < 500,
        ),
      );

      if (response.statusCode == 200) {
        return MatchingSessionResponse.fromJson(response.data);
      }

      if (response.statusCode == 404) {
        return null; // Session expired or not found
      }

      return null; // Return null on any error
    } catch (e) {
      return null; // Gracefully return null on exception
    }
  }
}

class MatchingSessionResponse {
  final List<AvailableDriver> drivers;
  final bool isActive;
  final DateTime? expiresAt;

  MatchingSessionResponse({
    required this.drivers,
    required this.isActive,
    this.expiresAt,
  });

  factory MatchingSessionResponse.fromJson(Map<String, dynamic> json) {
    final drivers = (json['drivers'] as List?)
        ?.map((d) => AvailableDriver.fromJson(d))
        .toList() ?? [];

    return MatchingSessionResponse(
      drivers: drivers,
      isActive: json['is_active'] ?? false,
      expiresAt: json['expires_at'] != null 
          ? DateTime.parse(json['expires_at'])
          : null,
    );
  }

  bool get isEmpty => drivers.isEmpty;
  bool get isExpired => expiresAt != null && DateTime.now().isAfter(expiresAt!);
}
```

#### 2.3 Update Trip Request UI

```dart
// File: lib/screens/trip_request_screen.dart

class _TripRequestScreenState extends State<TripRequestScreen> {
  DriverMatchingResponse? _matchingResponse;
  bool _isLoadingDrivers = false;
  String? _errorMessage;

  @override
  void initState() {
    super.initState();
    _loadAvailableDrivers();
  }

  Future<void> _loadAvailableDrivers() async {
    setState(() => _isLoadingDrivers = true);

    try {
      final driverService = Provider.of<DriverMatchingService>(
        context,
        listen: false,
      );

      final response = await driverService.getAvailableDrivers(
        latitude: widget.pickupLat,
        longitude: widget.pickupLng,
        transportType: 'motor_vehicle',
        maxResults: 10,
      );

      if (mounted) {
        setState(() {
          _matchingResponse = response;
          _isLoadingDrivers = false;
          
          // Show message if no drivers available
          if (response.isEmpty) {
            _errorMessage = response.getEmptyStateMessage();
          }
        });
      }
    } catch (e) {
      if (mounted) {
        setState(() {
          _isLoadingDrivers = false;
          _errorMessage = 'Failed to load drivers: $e';
        });
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Available Drivers'),
      ),
      body: _buildBody(),
    );
  }

  Widget _buildBody() {
    if (_isLoadingDrivers) {
      return const Center(child: CircularProgressIndicator());
    }

    if (_matchingResponse == null) {
      return Center(
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Icon(Icons.error_outline, size: 48, color: Colors.grey),
            SizedBox(height: 16),
            Text(_errorMessage ?? 'Unable to load drivers'),
            SizedBox(height: 24),
            ElevatedButton(
              onPressed: _loadAvailableDrivers,
              child: const Text('Retry'),
            ),
          ],
        ),
      );
    }

    // Handle empty drivers response
    if (_matchingResponse!.isEmpty) {
      return Center(
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Icon(
              Icons.person_outline,
              size: 64,
              color: Colors.grey[300],
            ),
            SizedBox(height: 16),
            Text(
              _matchingResponse!.getEmptyStateMessage(),
              textAlign: TextAlign.center,
              style: TextStyle(
                fontSize: 16,
                color: Colors.grey[600],
              ),
            ),
            SizedBox(height: 24),
            ElevatedButton(
              onPressed: _loadAvailableDrivers,
              child: const Text('Try Again'),
            ),
            SizedBox(height: 12),
            OutlinedButton(
              onPressed: () => Navigator.pop(context),
              child: const Text('Cancel'),
            ),
          ],
        ),
      );
    }

    // Display drivers list
    return ListView.builder(
      padding: const EdgeInsets.all(8),
      itemCount: _matchingResponse!.drivers.length,
      itemBuilder: (context, index) {
        final driver = _matchingResponse!.drivers[index];
        return _buildDriverCard(driver);
      },
    );
  }

  Widget _buildDriverCard(AvailableDriver driver) {
    return Card(
      margin: const EdgeInsets.symmetric(vertical: 8, horizontal: 0),
      child: ListTile(
        title: Text(driver.name),
        subtitle: Row(
          children: [
            Icon(Icons.star, size: 16, color: Colors.amber),
            SizedBox(width: 4),
            Text('${driver.rating.toStringAsFixed(1)} • ${driver.distance.toStringAsFixed(1)} km'),
          ],
        ),
        trailing: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Text('${driver.eta} min'),
            Text(driver.vehicleNumber, style: TextStyle(fontSize: 12)),
          ],
        ),
        onTap: () => _selectDriver(driver),
      ),
    );
  }

  void _selectDriver(AvailableDriver driver) {
    // Validate driver ID before using it
    if (!ValidationHelper.isValidTripId(driver.id)) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('Invalid driver selection. Please try again.'),
          backgroundColor: Colors.red,
        ),
      );
      return;
    }

    // Proceed with driver selection
    Navigator.pop(context, driver);
  }
}
```

---

## Issue 3: Error Handling Best Practices

### Create Custom Exceptions

```dart
// File: lib/exceptions/trip_exceptions.dart

class TripException implements Exception {
  final String message;
  final String? code;
  final int? httpStatusCode;

  TripException(
    this.message, {
    this.code,
    this.httpStatusCode,
  });

  @override
  String toString() => message;
}

class TripNotFoundException extends TripException {
  TripNotFoundException(String message) : super(
    message,
    code: 'TRIP_NOT_FOUND',
    httpStatusCode: 404,
  );
}

class InvalidTripIdException extends TripException {
  InvalidTripIdException(int? tripId) : super(
    'Invalid trip ID: $tripId',
    code: 'INVALID_TRIP_ID',
  );
}

class NoDriversAvailableException extends TripException {
  NoDriversAvailableException()
      : super(
        'No drivers available in your area',
        code: 'NO_DRIVERS_AVAILABLE',
        httpStatusCode: 404,
      );
}

class MatchingSessionExpiredException extends TripException {
  MatchingSessionExpiredException()
      : super(
        'Matching session has expired',
        code: 'SESSION_EXPIRED',
      );
}
```

---

## Testing

Add unit tests to verify the fixes:

```dart
// File: test/services/driver_matching_service_test.dart

void main() {
  group('DriverMatchingService', () {
    test('handles empty driver list gracefully', () async {
      final service = DriverMatchingService(dio: mockDio);
      
      // Mock empty response
      when(mockDio.get(...)).thenAnswer(
        (_) async => Response(data: {'data': []}, statusCode: 200),
      );

      final response = await service.getAvailableDrivers(
        latitude: 0.0,
        longitude: 0.0,
      );

      expect(response.isEmpty, isTrue);
      expect(response.drivers, isEmpty);
      expect(response.getEmptyStateMessage(), isNotEmpty);
    });

    test('returns valid response when drivers available', () async {
      final service = DriverMatchingService(dio: mockDio);
      
      // Mock response with drivers
      when(mockDio.get(...)).thenAnswer(
        (_) async => Response(
          data: {
            'data': [
              {'id': 1, 'name': 'John', 'rating': 4.5},
            ],
          },
          statusCode: 200,
        ),
      );

      final response = await service.getAvailableDrivers(
        latitude: 0.0,
        longitude: 0.0,
      );

      expect(response.isEmpty, isFalse);
      expect(response.drivers.length, equals(1));
    });

    test('validates trip ID before making API call', () async {
      final service = TripService(dio: mockDio);
      
      expect(
        () => service.acceptTrip(0),
        throwsA(isA<ArgumentError>()),
      );
      
      expect(
        () => service.acceptTrip(-1),
        throwsA(isA<ArgumentError>()),
      );
    });
  });
}
```

---

## Summary

These fixes ensure:
✅ Trip IDs are validated before API calls
✅ 0 or invalid trip IDs are rejected gracefully at the Flutter level
✅ Empty driver matching responses are handled without crashes
✅ Proper error messages are shown to users
✅ API resilience is improved for edge cases

---

## Deployment Checklist

- [ ] Update `lib/utils/validation_helper.dart` with trip ID validation
- [ ] Update `lib/services/trip_service.dart` with ID validation in all trip methods
- [ ] Update `lib/services/driver_matching_service.dart` with empty response handling
- [ ] Update `lib/models/driver_matching_response.dart` with response models
- [ ] Update `lib/exceptions/trip_exceptions.dart` with custom exceptions
- [ ] Update all UI screens to use new validation and error handling
- [ ] Add unit tests for all changes
- [ ] Test with empty driver list scenarios
- [ ] Test with invalid trip IDs (0, -1, non-existent IDs)
- [ ] Test with expired/invalid matching sessions
- [ ] Deploy to test environment for QA verification
