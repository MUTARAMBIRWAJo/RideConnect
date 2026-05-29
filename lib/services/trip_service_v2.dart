import 'dart:convert';
import 'package:http/http.dart' as http;
import '../config/api_config.dart';

class TripServiceV2 {
  final String _authToken;

  TripServiceV2(this._authToken);

  Map<String, String> get _headers => {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'Authorization': 'Bearer $_authToken',
      };

  Future<Map<String, dynamic>> createTrip({
    required int passengerId,
    required String pickupLocation,
    required String dropoffLocation,
    required double pickupLat,
    required double pickupLng,
    required double dropoffLat,
    required double dropoffLng,
    required String transportType,
    required String paymentMethod,
    String? pickupPlaceName,
    String? dropoffPlaceName,
    String? pickupZone,
    String? dropoffZone,
    String? idempotencyKey,
  }) async {
    final body = {
      'passenger_id': passengerId,
      'pickup_location': pickupLocation,
      'dropoff_location': dropoffLocation,
      'pickup_lat': pickupLat,
      'pickup_lng': pickupLng,
      'dropoff_lat': dropoffLat,
      'dropoff_lng': dropoffLng,
      'transport_type': transportType,
      'payment_method': paymentMethod,
      if (pickupPlaceName != null) 'pickup_place_name': pickupPlaceName,
      if (dropoffPlaceName != null) 'dropoff_place_name': dropoffPlaceName,
      if (pickupZone != null) 'pickup_zone': pickupZone,
      if (dropoffZone != null) 'dropoff_zone': dropoffZone,
      if (idempotencyKey != null) 'idempotency_key': idempotencyKey,
    };

    final response = await http.post(
      Uri.parse(ApiConfig.createTrip),
      headers: _headers,
      body: jsonEncode(body),
    );

    return _handleResponse(response);
  }

  Future<Map<String, dynamic>> respondToTrip({
    required int tripId,
    required String action,
    String? reason,
  }) async {
    final response = await http.put(
      Uri.parse(ApiConfig.tripRespond(tripId)),
      headers: _headers,
      body: jsonEncode({
        'action': action,
        if (reason != null) 'reason': reason,
      }),
    );

    return _handleResponse(response);
  }

  Future<Map<String, dynamic>> getTrip(int tripId) async {
    final response = await http.get(
      Uri.parse(ApiConfig.tripById(tripId)),
      headers: _headers,
    );

    return _handleResponse(response);
  }

  Future<Map<String, dynamic>> cancelTrip(int tripId, {String? reason}) async {
    final response = await http.post(
      Uri.parse(ApiConfig.tripCancel(tripId)),
      headers: _headers,
      body: jsonEncode({'reason': reason ?? 'passenger_cancelled'}),
    );

    return _handleResponse(response);
  }

  Future<void> updateDriverLocation({
    required double latitude,
    required double longitude,
    double? speedKmh,
    double? heading,
    double? accuracy,
  }) async {
    await http.post(
      Uri.parse(ApiConfig.updateDriverLocation),
      headers: _headers,
      body: jsonEncode({
        'latitude': latitude,
        'longitude': longitude,
        if (speedKmh != null) 'speed_kmh': speedKmh,
        if (heading != null) 'heading': heading,
        if (accuracy != null) 'accuracy': accuracy,
      }),
    );
  }

  Map<String, dynamic> _handleResponse(http.Response response) {
    final body = jsonDecode(response.body) as Map<String, dynamic>;
    if (response.statusCode == 200 || response.statusCode == 201) {
      return body;
    }

    throw ApiException(
      statusCode: response.statusCode,
      message: body['message'] ?? 'Unknown error',
    );
  }
}

class ApiException implements Exception {
  final int statusCode;
  final String message;

  ApiException({required this.statusCode, required this.message});

  @override
  String toString() => 'ApiException($statusCode): $message';
}
