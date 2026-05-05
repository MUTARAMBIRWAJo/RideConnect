# 🛠️ Flutter Mobile App - Implementation Guide & Best Practices

**RideConnect Mobile Development Guide**  
**Version:** 1.0  
**Last Updated:** May 2026

---

## 📑 Quick Links

- [Setup Instructions](#setup-instructions)
- [Core Architecture](#core-architecture)
- [API Integration Patterns](#api-integration-patterns)
- [Real-Time Features](#real-time-features)
- [Common Implementations](#common-implementations)
- [Performance Optimization](#performance-optimization)
- [Error Handling](#error-handling)
- [Testing](#testing)

---

## 🚀 Setup Instructions

### **1. Project Dependencies**

```pubspec.yaml
dependencies:
  flutter:
    sdk: flutter
  
  # HTTP & API
  dio: ^5.0.0
  retrofit: ^4.0.0
  
  # Real-Time
  supabase_flutter: ^2.0.0
  
  # Location
  geolocator: ^11.0.0
  google_maps_flutter: ^2.0.0
  
  # State Management
  provider: ^6.0.0
  flutter_bloc: ^8.0.0
  
  # Local Storage
  hive: ^2.0.0
  hive_flutter: ^1.0.0
  shared_preferences: ^2.0.0
  
  # Utils
  intl: ^0.19.0
  uuid: ^4.0.0
  logger: ^2.0.0
  json_serializable: ^6.0.0
  
dev_dependencies:
  build_runner: ^2.0.0
  hive_generator: ^2.0.0
```

### **2. Initialize Supabase (main.dart)**

```dart
import 'package:supabase_flutter/supabase_flutter.dart';

void main() async {
  WidgetsFlutterBinding.ensureInitialized();
  
  await Supabase.initialize(
    url: 'https://your-project.supabase.co',
    anonKey: 'your-anon-key',
  );
  
  runApp(const MyApp());
}

class MyApp extends StatelessWidget {
  const MyApp({Key? key}) : super(key: key);

  @override
  Widget build(BuildContext context) {
    return MaterialApp(
      title: 'RideConnect',
      theme: ThemeData(
        primarySwatch: Colors.blue,
        useMaterial3: true,
      ),
      home: const AuthWrapper(),
    );
  }
}
```

### **3. Dio HTTP Client Setup**

```dart
class ApiClient {
  static final ApiClient _instance = ApiClient._internal();
  
  late Dio _dio;
  
  factory ApiClient() {
    return _instance;
  }
  
  ApiClient._internal() {
    _dio = Dio(
      BaseOptions(
        baseUrl: 'https://api.rideconnect.rw/api/v1',
        connectTimeout: const Duration(seconds: 10),
        receiveTimeout: const Duration(seconds: 10),
        headers: {
          'Content-Type': 'application/json',
        },
      ),
    );
    
    // Add interceptors
    _dio.interceptors.add(AuthInterceptor());
    _dio.interceptors.add(LoggingInterceptor());
    _dio.interceptors.add(ErrorInterceptor());
  }
  
  Dio get dio => _dio;
}
```

---

## 🏗️ Core Architecture

### **Recommended Project Structure**

```
lib/
├── main.dart
├── config/
│   ├── constants.dart
│   ├── theme.dart
│   └── routes.dart
├── models/
│   ├── user.dart
│   ├── trip.dart
│   ├── driver.dart
│   └── payment.dart
├── services/
│   ├── api_service.dart
│   ├── auth_service.dart
│   ├── trip_service.dart
│   ├── driver_service.dart
│   └── location_service.dart
├── providers/
│   ├── auth_provider.dart
│   ├── trip_provider.dart
│   └── location_provider.dart
├── screens/
│   ├── auth/
│   │   ├── login_screen.dart
│   │   ├── register_screen.dart
│   │   └── splash_screen.dart
│   ├── passenger/
│   │   ├── home_screen.dart
│   │   ├── booking_screen.dart
│   │   ├── tracking_screen.dart
│   │   └── history_screen.dart
│   ├── driver/
│   │   ├── dashboard_screen.dart
│   │   ├── available_trips_screen.dart
│   │   ├── active_trip_screen.dart
│   │   └── earnings_screen.dart
│   └── common/
│       ├── profile_screen.dart
│       └── settings_screen.dart
├── widgets/
│   ├── common/
│   ├── passenger/
│   └── driver/
└── utils/
    ├── logger.dart
    ├── validators.dart
    └── extensions.dart
```

---

## 🔌 API Integration Patterns

### **Pattern 1: Service Layer (Recommended)**

```dart
// Service
class TripService {
  final ApiClient _apiClient;
  
  TripService(this._apiClient);
  
  Future<Trip> requestTrip({
    required String pickupLocation,
    required double pickupLat,
    required double pickupLng,
    required String dropoffLocation,
    required double dropoffLat,
    required double dropoffLng,
    required int passengers,
  }) async {
    try {
      final response = await _apiClient.dio.post(
        '/mobile/trips/request',
        data: {
          'pickup_location': pickupLocation,
          'pickup_lat': pickupLat,
          'pickup_lng': pickupLng,
          'dropoff_location': dropoffLocation,
          'dropoff_lat': dropoffLat,
          'dropoff_lng': dropoffLng,
          'number_of_passengers': passengers,
        },
      );
      
      return Trip.fromJson(response.data['data']);
    } on DioError catch (e) {
      throw ApiException(e.response?.statusCode ?? 0, e.message ?? '');
    }
  }
  
  Future<Trip> getCurrentTrip() async {
    try {
      final response = await _apiClient.dio.get('/mobile/trips/current');
      return Trip.fromJson(response.data['data']);
    } catch (e) {
      rethrow;
    }
  }
}

// Provider
class TripProvider extends ChangeNotifier {
  final TripService _tripService;
  
  Trip? _currentTrip;
  bool _isLoading = false;
  String? _error;
  
  TripProvider(this._tripService);
  
  Trip? get currentTrip => _currentTrip;
  bool get isLoading => _isLoading;
  String? get error => _error;
  
  Future<void> requestTrip({
    required String pickupLocation,
    required double pickupLat,
    // ... parameters
  }) async {
    _isLoading = true;
    _error = null;
    notifyListeners();
    
    try {
      _currentTrip = await _tripService.requestTrip(
        pickupLocation: pickupLocation,
        pickupLat: pickupLat,
        // ... parameters
      );
      _error = null;
    } catch (e) {
      _error = e.toString();
      _currentTrip = null;
    } finally {
      _isLoading = false;
      notifyListeners();
    }
  }
}

// Widget
class BookingScreen extends StatelessWidget {
  @override
  Widget build(BuildContext context) {
    return Consumer<TripProvider>(
      builder: (context, tripProvider, _) {
        if (tripProvider.isLoading) {
          return const LoadingWidget();
        }
        
        if (tripProvider.error != null) {
          return ErrorWidget(message: tripProvider.error!);
        }
        
        return TripDetailsWidget(trip: tripProvider.currentTrip!);
      },
    );
  }
}
```

### **Pattern 2: Repository Pattern**

```dart
// Repository
abstract class ITripRepository {
  Future<Trip> requestTrip(TripRequest request);
  Future<Trip> getCurrentTrip();
  Future<void> completeTrip(int tripId, TripCompletion completion);
}

class TripRepository implements ITripRepository {
  final Dio _dio;
  
  TripRepository(this._dio);
  
  @override
  Future<Trip> requestTrip(TripRequest request) async {
    try {
      final response = await _dio.post('/mobile/trips/request', data: request);
      return Trip.fromJson(response.data['data']);
    } catch (e) {
      rethrow;
    }
  }
  
  @override
  Future<Trip> getCurrentTrip() async {
    final response = await _dio.get('/mobile/trips/current');
    return Trip.fromJson(response.data['data']);
  }
  
  @override
  Future<void> completeTrip(int tripId, TripCompletion completion) async {
    await _dio.put('/mobile/trips/$tripId/complete', data: completion);
  }
}
```

---

## 🌐 Real-Time Features

### **Real-Time Driver Location Tracking**

```dart
class LocationStreamService {
  final SupabaseClient _supabase;
  
  StreamSubscription<dynamic>? _subscription;
  
  // Listen to driver location updates
  Stream<DriverLocation> listenToDriverLocation(int driverId) async* {
    try {
      final channel = _supabase.channel('driver:$driverId');
      
      _subscription = channel
          .onBroadcast(
            event: 'driver.location.updated',
            callback: (payload) {
              // Emit location update
            },
          )
          .subscribe();
      
    } catch (e) {
      print('Error listening to driver location: $e');
      rethrow;
    }
  }
  
  // Stop listening
  Future<void> dispose() async {
    await _subscription?.cancel();
    await _supabase.removeChannel(_subscription!);
  }
}

// Usage in Provider
class DriverTrackingProvider extends ChangeNotifier {
  final LocationStreamService _locationService;
  
  DriverLocation? _driverLocation;
  StreamSubscription? _subscription;
  
  DriverTrackingProvider(this._locationService);
  
  void startTracking(int driverId) {
    _subscription = _locationService
        .listenToDriverLocation(driverId)
        .listen((location) {
          _driverLocation = location;
          notifyListeners();
        }, onError: (error) {
          print('Error: $error');
        });
  }
  
  @override
  void dispose() {
    _subscription?.cancel();
    super.dispose();
  }
}
```

---

## 📍 Common Implementations

### **1. Continuous Location Tracking (Driver)**

```dart
class DriverLocationProvider extends ChangeNotifier {
  late Timer _locationTimer;
  final LocationService _locationService;
  final ApiClient _apiClient;
  
  bool _isOnline = false;
  bool get isOnline => _isOnline;
  
  DriverLocationProvider(this._locationService, this._apiClient);
  
  Future<void> goOnline() async {
    _isOnline = true;
    
    // Update status on server
    await _apiClient.dio.post('/mobile/drivers/status', data: {
      'status': 'online',
      'availability_status': 'available',
    });
    
    // Start location tracking
    _startLocationTracking();
    notifyListeners();
  }
  
  Future<void> goOffline() async {
    _isOnline = false;
    _locationTimer.cancel();
    
    await _apiClient.dio.post('/mobile/drivers/status', data: {
      'status': 'offline',
    });
    
    notifyListeners();
  }
  
  void _startLocationTracking() {
    _locationTimer = Timer.periodic(Duration(seconds: 10), (_) async {
      try {
        final position = await _locationService.getCurrentLocation();
        
        await _apiClient.dio.post('/mobile/drivers/live-location', data: {
          'lat': position.latitude,
          'lng': position.longitude,
          'speed_kmh': position.speed,
          'heading': position.heading,
          'accuracy': position.accuracy,
          'is_online': true,
        });
      } catch (e) {
        print('Location update failed: $e');
      }
    });
  }
  
  @override
  void dispose() {
    if (_isOnline) {
      _locationTimer.cancel();
    }
    super.dispose();
  }
}
```

### **2. Trip Polling (Passenger)**

```dart
class TripPollingProvider extends ChangeNotifier {
  late Timer _pollingTimer;
  Trip? _currentTrip;
  
  Trip? get currentTrip => _currentTrip;
  
  void startPollingTrip(int tripId, ApiClient apiClient) {
    _pollingTimer = Timer.periodic(Duration(seconds: 2), (_) async {
      try {
        final response = await apiClient.dio.get('/mobile/trips/$tripId/track');
        _currentTrip = Trip.fromJson(response.data['data']);
        notifyListeners();
      } catch (e) {
        print('Poll error: $e');
      }
    });
  }
  
  void stopPolling() {
    _pollingTimer.cancel();
  }
  
  @override
  void dispose() {
    stopPolling();
    super.dispose();
  }
}
```

### **3. Payment Processing**

```dart
class PaymentService {
  final ApiClient _apiClient;
  
  Future<Payment> processPayment({
    required double amount,
    required String paymentMethod,
    required String tripId,
  }) async {
    try {
      final response = await _apiClient.dio.post(
        '/passenger/payments',
        data: {
          'amount': amount,
          'currency': 'RWF',
          'payment_method': paymentMethod,
          'trip_id': tripId,
        },
      );
      
      final payment = Payment.fromJson(response.data['data']);
      
      // Handle payment URL if needed
      if (payment.paymentUrl != null) {
        // Launch URL for payment gateway
        await _launchPaymentUrl(payment.paymentUrl!);
      }
      
      return payment;
    } catch (e) {
      rethrow;
    }
  }
  
  Future<void> _launchPaymentUrl(String url) async {
    if (await canLaunch(url)) {
      await launch(url);
    }
  }
}
```

### **4. Map Integration**

```dart
class MapProvider extends ChangeNotifier {
  GoogleMapController? _mapController;
  Set<Marker> _markers = {};
  PolylinePoints polylinePoints = PolylinePoints();
  Map<PolylineId, Polyline> polylines = {};
  
  GoogleMapController? get mapController => _mapController;
  Set<Marker> get markers => _markers;
  
  void addDriverMarker(int driverId, double lat, double lng, String driverName) {
    _markers.add(
      Marker(
        markerId: MarkerId('driver_$driverId'),
        position: LatLng(lat, lng),
        infoWindow: InfoWindow(title: driverName),
        icon: BitmapDescriptor.defaultMarkerWithHue(BitmapDescriptor.hueBlue),
      ),
    );
    notifyListeners();
  }
  
  void updateDriverLocation(int driverId, double lat, double lng) {
    _markers.removeWhere((m) => m.markerId.value == 'driver_$driverId');
    addDriverMarker(driverId, lat, lng, 'Driver');
  }
  
  Future<void> drawRoute(LatLng start, LatLng end) async {
    PolylineResult result = await polylinePoints.getRouteBetweenCoordinates(
      googleApiKey: 'YOUR_GOOGLE_API_KEY',
      request: PolylineRequest(
        origin: PointLatLng(start.latitude, start.longitude),
        destination: PointLatLng(end.latitude, end.longitude),
        mode: TravelMode.driving,
      ),
    );
    
    if (result.points.isNotEmpty) {
      for (var point in result.points) {
        // Add points to polyline
      }
    }
  }
}
```

---

## ⚡ Performance Optimization

### **1. Image Caching**

```dart
final imageCache = ImageCache();
imageCache.maximumSize = 100;
imageCache.maximumSizeBytes = 50 * 1024 * 1024; // 50 MB

// In app
CachedNetworkImage(
  imageUrl: 'https://...',
  placeholder: (context, url) => CircularProgressIndicator(),
  errorWidget: (context, url, error) => Icon(Icons.error),
  cacheManager: CacheManager(
    Config('imageCache', stalePeriod: Duration(days: 7)),
  ),
)
```

### **2. Efficient List Rendering**

```dart
ListView.builder(
  itemCount: trips.length,
  itemBuilder: (context, index) {
    return TripCard(trip: trips[index]);
  },
  // Improve performance with addAutomaticKeepAlives
  addAutomaticKeepAlives: false,
)
```

### **3. Location Updates Throttling**

```dart
class ThrottledLocationProvider extends ChangeNotifier {
  DateTime _lastUpdate = DateTime.now();
  
  Future<void> updateLocation(Position position) async {
    final now = DateTime.now();
    final difference = now.difference(_lastUpdate);
    
    // Only update every 5 seconds
    if (difference.inSeconds >= 5) {
      _lastUpdate = now;
      
      // Send location update
      await _apiClient.dio.post('/mobile/drivers/live-location', data: {
        'lat': position.latitude,
        'lng': position.longitude,
      });
      
      notifyListeners();
    }
  }
}
```

---

## ⚠️ Error Handling

### **Custom Exception Classes**

```dart
class ApiException implements Exception {
  final int statusCode;
  final String message;
  
  ApiException(this.statusCode, this.message);
  
  @override
  String toString() => 'ApiException: $statusCode - $message';
}

class NetworkException implements Exception {
  final String message;
  
  NetworkException(this.message);
  
  @override
  String toString() => 'NetworkException: $message';
}

class ValidationException implements Exception {
  final Map<String, List<String>> errors;
  
  ValidationException(this.errors);
}
```

### **Global Error Handler**

```dart
class ErrorHandler {
  static String getErrorMessage(Object error) {
    if (error is DioError) {
      if (error.response != null) {
        final data = error.response?.data;
        return data['message'] ?? 'An error occurred';
      }
      return error.message ?? 'Network error';
    }
    return error.toString();
  }
  
  static void showErrorSnackbar(BuildContext context, Object error) {
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        content: Text(getErrorMessage(error)),
        backgroundColor: Colors.red,
        duration: Duration(seconds: 4),
      ),
    );
  }
}

// Usage
try {
  await _service.doSomething();
} catch (e) {
  ErrorHandler.showErrorSnackbar(context, e);
}
```

---

## 🧪 Testing

### **Unit Testing API Service**

```dart
void main() {
  group('TripService', () {
    late MockDio mockDio;
    late TripService tripService;
    
    setUp(() {
      mockDio = MockDio();
      tripService = TripService(mockDio);
    });
    
    test('requestTrip returns trip on success', () async {
      // Arrange
      final mockResponse = Response(
        data: {
          'status': 'success',
          'data': {
            'id': 789,
            'status': 'matching',
            'estimated_fare': 3500,
          }
        },
        statusCode: 200,
        requestOptions: RequestOptions(path: ''),
      );
      
      when(mockDio.post(
        '/mobile/trips/request',
        data: anyNamed('data'),
      )).thenAnswer((_) async => mockResponse);
      
      // Act
      final trip = await tripService.requestTrip(
        pickupLocation: 'Remera',
        pickupLat: -1.9536,
        pickupLng: 30.0605,
        dropoffLocation: 'Kimironko',
        dropoffLat: -1.9365,
        dropoffLng: 30.1200,
        passengers: 1,
      );
      
      // Assert
      expect(trip.id, 789);
      expect(trip.status, 'matching');
    });
  });
}
```

### **Widget Testing**

```dart
void main() {
  testWidgets('BookingScreen shows loading', (WidgetTester tester) async {
    // Build widget
    await tester.pumpWidget(
      MultiProvider(
        providers: [
          ChangeNotifierProvider(create: (_) => mockTripProvider),
        ],
        child: const MaterialApp(home: BookingScreen()),
      ),
    );
    
    // Verify loading shows
    expect(find.byType(LoadingWidget), findsOneWidget);
  });
}
```

---

## 📋 Checklist Before Launch

- [ ] All API endpoints tested with actual backend
- [ ] Location permissions requested and handled
- [ ] Payment integration tested
- [ ] Error handling for all edge cases
- [ ] Offline mode with local caching
- [ ] Push notifications working
- [ ] App icon and splash screen set
- [ ] Version number updated
- [ ] Privacy policy and terms displayed
- [ ] Analytics integrated
- [ ] Crash reporting configured
- [ ] Performance tested on low-end devices
- [ ] Dark mode support
- [ ] Multiple language support (if required)
- [ ] Signed APK/IPA builds created

---

## 🔗 Additional Resources

- **Flutter Docs:** https://flutter.dev/docs
- **Dio Package:** https://pub.dev/packages/dio
- **Supabase Flutter:** https://supabase.com/docs/reference/dart
- **Provider Pattern:** https://pub.dev/packages/provider
- **Google Maps:** https://pub.dev/packages/google_maps_flutter

---

**Version:** 1.0  
**Last Updated:** May 2026
