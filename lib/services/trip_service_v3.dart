import 'dart:async';
import 'dart:convert';
import 'package:http/http.dart' as http;
import '../config/api_config.dart';

class TripServiceV3 {
  final String _authToken;

  TripServiceV3(this._authToken);

  String get _baseUrl => '${ApiConfig.laravelBase}/api/v3';

  Map<String, String> get _headers => {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'Authorization': 'Bearer $_authToken',
      };

  /// Request a Motor Vehicle (moto, boda, tuk-tuk)
  Future<Map<String, dynamic>> requestMotorVehicle({
    required String pickupLocation,
    required double pickupLat,
    required double pickupLng,
    required String dropoffLocation,
    required double dropoffLat,
    required double dropoffLng,
    required String rideMode, // e.g. 'solo', 'share'
    required String paymentMethod, // e.g. 'cash', 'mobile_money'
  }) async {
    final response = await http.post(
      Uri.parse('$_baseUrl/trips/motor-vehicle/request'),
      headers: _headers,
      body: jsonEncode({
        'pickup_location': pickupLocation,
        'pickup_lat': pickupLat,
        'pickup_lng': pickupLng,
        'dropoff_location': dropoffLocation,
        'dropoff_lat': dropoffLat,
        'dropoff_lng': dropoffLng,
        'ride_mode': rideMode,
        'payment_method': paymentMethod,
      }),
    );
    return _handleResponse(response);
  }

  /// Request a Private Car (sedan, suv, minivan)
  Future<Map<String, dynamic>> requestPrivateCar({
    required String pickupLocation,
    required double pickupLat,
    required double pickupLng,
    required String dropoffLocation,
    required double dropoffLat,
    required double dropoffLng,
    required String carTypePreference, // e.g. 'sedan', 'suv'
    required String paymentMethod,
    String? scheduledTime,
    int? requestedSeats,
  }) async {
    final response = await http.post(
      Uri.parse('$_baseUrl/trips/private-car/request'),
      headers: _headers,
      body: jsonEncode({
        'pickup_location': pickupLocation,
        'pickup_lat': pickupLat,
        'pickup_lng': pickupLng,
        'dropoff_location': dropoffLocation,
        'dropoff_lat': dropoffLat,
        'dropoff_lng': dropoffLng,
        'car_type_preference': carTypePreference,
        'payment_method': paymentMethod,
        if (scheduledTime != null) 'scheduled_time': scheduledTime,
        if (requestedSeats != null) 'requested_seats': requestedSeats,
      }),
    );
    return _handleResponse(response);
  }

  /// Request a Public Bus
  Future<Map<String, dynamic>> requestPublicBus({
    required String pickupStop,
    required double pickupLat,
    required double pickupLng,
    required String dropoffStop,
    required double dropoffLat,
    required double dropoffLng,
    required int routeId,
    int? driverId,
    int? passengerCount,
    String? preferredTime,
  }) async {
    final response = await http.post(
      Uri.parse('$_baseUrl/trips/public-bus/request'),
      headers: _headers,
      body: jsonEncode({
        'pickup_stop': pickupStop,
        'pickup_lat': pickupLat,
        'pickup_lng': pickupLng,
        'dropoff_stop': dropoffStop,
        'dropoff_lat': dropoffLat,
        'dropoff_lng': dropoffLng,
        'route_id': routeId,
        if (driverId != null) 'driver_id': driverId,
        if (passengerCount != null) 'passenger_count': passengerCount,
        if (preferredTime != null) 'preferred_time': preferredTime,
      }),
    );
    return _handleResponse(response);
  }

  /// Get current matching progress status
  Future<Map<String, dynamic>> getMatchingStatus(int tripId) async {
    final response = await http.get(
      Uri.parse('$_baseUrl/trips/$tripId/matching-status'),
      headers: _headers,
    );
    return _handleResponse(response);
  }

  /// Get detailed trip status (status, driver location, ETA)
  Future<Map<String, dynamic>> getTripStatus(int tripId) async {
    final response = await http.get(
      Uri.parse('$_baseUrl/trips/$tripId/status'),
      headers: _headers,
    );
    return _handleResponse(response);
  }

  /// Cancel trip
  Future<Map<String, dynamic>> cancelTrip(int tripId, String reason) async {
    final response = await http.post(
      Uri.parse('$_baseUrl/trips/$tripId/cancel'),
      headers: _headers,
      body: jsonEncode({'reason': reason}),
    );
    return _handleResponse(response);
  }

  /// Pay for trip (Passenger)
  Future<Map<String, dynamic>> payTrip({
    required int tripId,
    required String paymentMethod,
    required double amount,
    String? paymentReference,
  }) async {
    final response = await http.post(
      Uri.parse('$_baseUrl/trips/$tripId/pay'),
      headers: _headers,
      body: jsonEncode({
        'payment_method': paymentMethod,
        'amount': amount,
        if (paymentReference != null) 'payment_reference': paymentReference,
      }),
    );
    return _handleResponse(response);
  }

  /// Rate trip (Passenger)
  Future<Map<String, dynamic>> rateTrip({
    required int tripId,
    required int rating,
    String? comment,
  }) async {
    final response = await http.post(
      Uri.parse('$_baseUrl/trips/$tripId/rate'),
      headers: _headers,
      body: jsonEncode({
        'rating': rating,
        if (comment != null) 'comment': comment,
      }),
    );
    return _handleResponse(response);
  }

  // ==========================================
  // DRIVER ACTIONS (For simulation or driver app)
  // ==========================================

  /// Accept incoming trip
  Future<Map<String, dynamic>> acceptTrip(int tripId) async {
    final response = await http.post(
      Uri.parse('$_baseUrl/trips/$tripId/accept'),
      headers: _headers,
    );
    return _handleResponse(response);
  }

  /// Reject incoming trip
  Future<Map<String, dynamic>> rejectTrip(int tripId, {String? reason}) async {
    final response = await http.post(
      Uri.parse('$_baseUrl/trips/$tripId/reject'),
      headers: _headers,
      body: jsonEncode({if (reason != null) 'reason': reason}),
    );
    return _handleResponse(response);
  }

  /// Mark driver arrived at pickup
  Future<Map<String, dynamic>> markArrived(int tripId) async {
    final response = await http.post(
      Uri.parse('$_baseUrl/trips/$tripId/arrived'),
      headers: _headers,
    );
    return _handleResponse(response);
  }

  /// Start the trip (Passengers picked up)
  Future<Map<String, dynamic>> startTrip(int tripId) async {
    final response = await http.post(
      Uri.parse('$_baseUrl/trips/$tripId/start'),
      headers: _headers,
    );
    return _handleResponse(response);
  }

  /// Complete the trip (Arrived at dropoff)
  Future<Map<String, dynamic>> completeTrip(int tripId) async {
    final response = await http.post(
      Uri.parse('$_baseUrl/trips/$tripId/complete'),
      headers: _headers,
    );
    return _handleResponse(response);
  }

  /// Send real-time driver coordinates
  Future<Map<String, dynamic>> updateDriverLocation({
    required int tripId,
    required double latitude,
    required double longitude,
    double? heading,
    double? speed,
  }) async {
    final response = await http.post(
      Uri.parse('$_baseUrl/driver/location'),
      headers: _headers,
      body: jsonEncode({
        'trip_id': tripId,
        'latitude': latitude,
        'longitude': longitude,
        if (heading != null) 'heading': heading,
        if (speed != null) 'speed': speed,
      }),
    );
    return _handleResponse(response);
  }

  // ==========================================
  // SIMULATED POLLING IMPLEMENTATION
  // ==========================================

  /// Polls the trip status at regular intervals until a target status/terminal status is met.
  Stream<Map<String, dynamic>> pollTripStatus(int tripId, {Duration interval = const Duration(seconds: 3)}) async* {
    while (true) {
      await Future.delayed(interval);
      try {
        final statusData = await getTripStatus(tripId);
        yield statusData;
        final status = statusData['status'];
        if (status == 'RATED' || status == 'CANCELLED' || status == 'FAILED') {
          break;
        }
      } catch (e) {
        // Stream the error and allow the subscriber to handle it
        yield {'error': e.toString()};
      }
    }
  }

  /// Polls the matching status until the driver is found or timeout occurs.
  Stream<Map<String, dynamic>> pollMatchingStatus(int tripId, {Duration interval = const Duration(seconds: 3)}) async* {
    while (true) {
      await Future.delayed(interval);
      try {
        final matchingData = await getMatchingStatus(tripId);
        yield matchingData;
        final status = matchingData['status'];
        if (status != 'MATCHING' && status != 'PENDING_MATCH' && status != 'REQUESTED') {
          break;
        }
      } catch (e) {
        yield {'error': e.toString()};
      }
    }
  }

  Map<String, dynamic> _handleResponse(http.Response response) {
    final body = jsonDecode(response.body) as Map<String, dynamic>;
    if (response.statusCode >= 200 && response.statusCode < 300) {
      return body;
    }
    throw Exception(body['message'] ?? 'API request failed with status: ${response.statusCode}');
  }
}
