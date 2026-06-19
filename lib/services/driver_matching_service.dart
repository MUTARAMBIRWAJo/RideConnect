// File: lib/services/driver_matching_service.dart
// Production Driver Matching Service with empty response handling
// Last Updated: May 29, 2026

import 'package:dio/dio.dart';
import '../config/api_config.dart';
import '../exceptions/trip_exceptions.dart';

class AvailableDriver {
  final int id;
  final String name;
  final double rating;
  final String vehicleType;
  final double distance;
  final int eta; // minutes
  final String vehicleNumber;
  final String? vehicleColor;
  final String? licensePlate;

  AvailableDriver({
    required this.id,
    required this.name,
    required this.rating,
    required this.vehicleType,
    required this.distance,
    required this.eta,
    required this.vehicleNumber,
    this.vehicleColor,
    this.licensePlate,
  });

  factory AvailableDriver.fromJson(Map<String, dynamic> json) {
    final vehicle = json['vehicle'] as Map<String, dynamic>?;
    return AvailableDriver(
      id: json['driver_id'] ?? json['id'] ?? 0,
      name: json['driver_name'] ?? json['name'] ?? 'Unknown Driver',
      rating: (json['rating'] ?? 0.0).toDouble(),
      vehicleType: vehicle?['vehicle_type'] ?? json['vehicle_type'] ?? 'Unknown',
      distance: (json['distance_km'] ?? json['distance'] ?? 0.0).toDouble(),
      eta: json['estimated_arrival_minutes'] ?? json['eta'] ?? 0,
      vehicleNumber: vehicle?['plate_number'] ?? json['vehicle_number'] ?? json['license_plate'] ?? '',
      vehicleColor: vehicle?['color'] ?? json['vehicle_color'],
      licensePlate: vehicle?['plate_number'] ?? json['license_plate'],
    );
  }

  Map<String, dynamic> toJson() => {
    'id': id,
    'name': name,
    'rating': rating,
    'vehicle_type': vehicleType,
    'distance': distance,
    'eta': eta,
    'vehicle_number': vehicleNumber,
    'vehicle_color': vehicleColor,
    'license_plate': licensePlate,
  };
}

class DriverMatchingResponse {
  final List<AvailableDriver> drivers;
  final String? matchingSessionId;
  final bool hasAvailableDrivers;
  final String? noDriversReason;
  final DateTime? expiresAt;

  DriverMatchingResponse({
    required this.drivers,
    this.matchingSessionId,
    required this.hasAvailableDrivers,
    this.noDriversReason,
    this.expiresAt,
  });

  factory DriverMatchingResponse.fromJson(Map<String, dynamic> json) {
    final dataObj = json['data'];
    final List? rawDrivers = dataObj is Map<String, dynamic> 
        ? (dataObj['drivers'] as List?) 
        : (dataObj as List?);
    
    final drivers = rawDrivers
            ?.map((d) => AvailableDriver.fromJson(d as Map<String, dynamic>))
            .toList() ??
        [];

    final String? sessionId = dataObj is Map<String, dynamic>
        ? dataObj['matching_session_id'] as String?
        : json['matching_session_id'] as String?;

    final String? expiryStr = dataObj is Map<String, dynamic>
        ? dataObj['expires_at'] as String?
        : json['expires_at'] as String?;

    return DriverMatchingResponse(
      drivers: drivers,
      matchingSessionId: sessionId,
      hasAvailableDrivers: drivers.isNotEmpty,
      noDriversReason:
          drivers.isEmpty
              ? json['message'] ?? 'No drivers currently available in your area'
              : null,
      expiresAt:
          expiryStr != null
              ? DateTime.tryParse(expiryStr)
              : null,
    );
  }

  /// Check if response is effectively empty
  bool get isEmpty => drivers.isEmpty;

  /// Get appropriate message for empty response
  String getEmptyStateMessage() {
    if (isEmpty) {
      return noDriversReason ??
          'No drivers available at the moment. Please try again later.';
    }
    return '';
  }

  /// Check if session has expired
  bool get isExpired => expiresAt != null && DateTime.now().isAfter(expiresAt!);
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
    final drivers =
        (json['drivers'] as List?)
            ?.map((d) => AvailableDriver.fromJson(d as Map<String, dynamic>))
            .toList() ??
        [];

    return MatchingSessionResponse(
      drivers: drivers,
      isActive: json['is_active'] ?? false,
      expiresAt:
          json['expires_at'] != null
              ? DateTime.tryParse(json['expires_at'])
              : null,
    );
  }

  bool get isEmpty => drivers.isEmpty;
  bool get isExpired => expiresAt != null && DateTime.now().isAfter(expiresAt!);
}

class DriverMatchingService {
  final Dio dio;

  DriverMatchingService({required this.dio});

  /// Fetch available drivers for matching
  /// Gracefully handles empty driver list
  Future<DriverMatchingResponse> getAvailableDrivers({
    required double latitude,
    required double longitude,
    double? dropoffLatitude,
    double? dropoffLongitude,
    String? transportType,
    int maxResults = 10,
  }) async {
    try {
      final double dLat = dropoffLatitude ?? (latitude + 0.009);
      final double dLng = dropoffLongitude ?? (longitude + 0.009);

      final response = await dio.get(
        ApiEndpoints.availableDrivers,
        queryParameters: {
          'pickup_lat': latitude,
          'pickup_lng': longitude,
          'dropoff_lat': dLat,
          'dropoff_lng': dLng,
          if (transportType != null)
            'transport_type': _normalizeTransportType(transportType),
          'limit': maxResults,
        },
        options: Options(
          validateStatus: (status) => status != null && status < 500,
        ),
      );

      if (response.statusCode == 200) {
        final matchingResponse = DriverMatchingResponse.fromJson(response.data);
        
        // Handle empty driver list gracefully
        if (matchingResponse.isEmpty) {
          return matchingResponse;
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

      // Handle server errors gracefully
      return DriverMatchingResponse(
        drivers: [],
        matchingSessionId: null,
        hasAvailableDrivers: false,
        noDriversReason: 'Unable to load drivers. Status: ${response.statusCode}',
      );
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
      return null;
    }

    try {
      final tripId = int.tryParse(sessionId) ?? 0;
      final response = await dio.get(
        '${ApiConfig.laravelBase}/api/v3/trips/$tripId/matching-status',
        options: Options(
          validateStatus: (status) => status != null && status < 500,
        ),
      );

      if (response.statusCode == 200) {
        final data = response.data['data'] ?? response.data;
        final String status = data['status'] ?? 'MATCHING';
        final bool isActive = status == 'MATCHING' || status == 'searching';

        return MatchingSessionResponse(
          drivers: [],
          isActive: isActive,
          expiresAt: DateTime.now().add(Duration(seconds: 180 - (data['elapsed_seconds'] as int? ?? 0))),
        );
      }

      if (response.statusCode == 404) {
        return null; // Session expired or not found
      }

      return null; // Return null on any error
    } catch (e) {
      return null; // Gracefully return null on exception
    }
  }

  /// Fetch drivers with retry logic for better reliability
  Future<DriverMatchingResponse> getAvailableDriversWithRetry({
    required double latitude,
    required double longitude,
    double? dropoffLatitude,
    double? dropoffLongitude,
    String? transportType,
    int maxResults = 10,
    int maxAttempts = 3,
  }) async {
    DriverMatchingResponse? lastResponse;
    
    for (int attempt = 1; attempt <= maxAttempts; attempt++) {
      try {
        lastResponse = await getAvailableDrivers(
          latitude: latitude,
          longitude: longitude,
          dropoffLatitude: dropoffLatitude,
          dropoffLongitude: dropoffLongitude,
          transportType: transportType,
          maxResults: maxResults,
        );

        // If we got drivers or a valid empty response, return it
        if (lastResponse.drivers.isNotEmpty || attempt == maxAttempts) {
          return lastResponse;
        }

        // Wait before retrying (exponential backoff)
        if (attempt < maxAttempts) {
          await Future.delayed(Duration(milliseconds: 500 * attempt));
        }
      } catch (e) {
        if (attempt == maxAttempts) {
          rethrow;
        }
        // Continue to next attempt
      }
    }

    return lastResponse ?? DriverMatchingResponse(
      drivers: [],
      matchingSessionId: null,
      hasAvailableDrivers: false,
      noDriversReason: 'Unable to fetch drivers after multiple attempts',
    );
  }

  /// Filter drivers by rating
  List<AvailableDriver> filterByRating(
    List<AvailableDriver> drivers, {
    double minRating = 3.0,
  }) {
    return drivers.where((d) => d.rating >= minRating).toList();
  }

  /// Sort drivers by distance
  List<AvailableDriver> sortByDistance(List<AvailableDriver> drivers) {
    final sorted = [...drivers];
    sorted.sort((a, b) => a.distance.compareTo(b.distance));
    return sorted;
  }

  /// Sort drivers by ETA
  List<AvailableDriver> sortByEta(List<AvailableDriver> drivers) {
    final sorted = [...drivers];
    sorted.sort((a, b) => a.eta.compareTo(b.eta));
    return sorted;
  }

  /// Get best driver (closest with highest rating)
  AvailableDriver? getBestDriver(List<AvailableDriver> drivers) {
    if (drivers.isEmpty) return null;
    
    final sorted = [...drivers];
    sorted.sort((a, b) {
      // Primary: distance (closer is better)
      // Secondary: rating (higher is better)
      final distanceDiff = a.distance.compareTo(b.distance);
      if (distanceDiff != 0) return distanceDiff;
      return b.rating.compareTo(a.rating);
    });
    
    return sorted.first;
  }
}

String _normalizeTransportType(String value) {
  final lower = value.trim().toLowerCase();
  if (lower == 'bus' || lower == 'public_bus' || lower == 'public bus') {
    return 'public_bus';
  }
  if (lower == 'car' ||
      lower == 'private' ||
      lower == 'private_car' ||
      lower == 'private car') {
    return 'private_car';
  }
  if (lower == 'motorcycle' ||
      lower == 'motor_vehicle' ||
      lower == 'motor vehicle' ||
      lower == 'moto') {
    return 'motor_vehicle';
  }
  return value.trim();
}
