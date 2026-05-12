// File: lib/services/trip_service.dart
// Flutter service for trip actions (accept/reject) with proper error handling

import 'package:dio/dio.dart';
import 'package:flutter/foundation.dart';

class TripResponse {
  final String? tripId;
  final String? tripState;
  final String? driverId;
  final String? acceptedAt;

  TripResponse({
    this.tripId,
    this.tripState,
    this.driverId,
    this.acceptedAt,
  });

  factory TripResponse.fromJson(Map<String, dynamic> json) {
    final data = json['data'] as Map<String, dynamic>?;
    return TripResponse(
      tripId: data?['trip_id']?.toString(),
      tripState: data?['trip_state'],
      driverId: data?['driver_id']?.toString(),
      acceptedAt: data?['accepted_at'],
    );
  }
}

class TripErrorResponse {
  final String type;
  final String message;
  final String code;
  final String? currentStatus;
  final int? assignedDriverId;
  final int httpCode;

  TripErrorResponse({
    required this.type,
    required this.message,
    required this.code,
    this.currentStatus,
    this.assignedDriverId,
    required this.httpCode,
  });

  factory TripErrorResponse.fromJson(Map<String, dynamic> json) {
    return TripErrorResponse(
      type: json['type'] ?? 'UNKNOWN_ERROR',
      message: json['message'] ?? 'An unexpected error occurred',
      code: json['code'] ?? 'ERROR',
      currentStatus: json['current_status'],
      assignedDriverId: json['assigned_driver_id'],
      httpCode: json['http_code'] ?? 500,
    );
  }

  String get userFriendlyMessage {
    switch (type) {
      case 'TRIP_NOT_FOUND':
        return 'Trip not found. It may have been cancelled.';
      case 'TRIP_NOT_AVAILABLE':
        return 'This trip is no longer available. Current status: $currentStatus.';
      case 'TRIP_ALREADY_ASSIGNED':
        return 'Another driver already accepted this trip.';
      case 'TRIP_RACE_CONDITION':
        return 'Another driver just accepted this trip. Please try another one.';
      case 'TRIP_CANNOT_BE_REJECTED':
        return 'Cannot reject this trip. Status: $currentStatus.';
      case 'DRIVER_NOT_FOUND':
        return 'Driver profile not found. Please complete registration.';
      case 'POLICY_VIOLATION':
        return message; // Custom policy message
      default:
        return message;
    }
  }
}

class TripService {
  final Dio dio;
  static const String baseUrl = 'https://api.rideconnect.local/api/mobile';

  TripService({required this.dio});

  /// Accept a trip request
  Future<TripResponse> acceptTrip(int tripId) async {
    try {
      final response = await dio.post(
        '$baseUrl/drivers/trips/$tripId/accept',
        options: Options(
          validateStatus: (status) => status != null && status < 500,
        ),
      );

      // Success case
      if (response.statusCode == 200) {
        return TripResponse.fromJson(response.data);
      }

      // Error case
      final error = TripErrorResponse.fromJson(response.data);
      throw TripAcceptanceException(error);

    } on DioException catch (e) {
      throw TripAcceptanceException(
        TripErrorResponse(
          type: 'NETWORK_ERROR',
          message: 'Network error: ${e.message}',
          code: 'NETWORK_ERROR',
          httpCode: e.response?.statusCode ?? 0,
        ),
      );
    }
  }

  /// Reject a trip request
  Future<void> rejectTrip(int tripId) async {
    try {
      final response = await dio.post(
        '$baseUrl/drivers/trips/$tripId/reject',
        options: Options(
          validateStatus: (status) => status != null && status < 500,
        ),
      );

      // Success case
      if (response.statusCode == 200) {
        debugPrint('Trip $tripId rejected successfully');
        return;
      }

      // Error case
      final error = TripErrorResponse.fromJson(response.data);
      throw TripRejectionException(error);

    } on DioException catch (e) {
      throw TripRejectionException(
        TripErrorResponse(
          type: 'NETWORK_ERROR',
          message: 'Network error: ${e.message}',
          code: 'NETWORK_ERROR',
          httpCode: e.response?.statusCode ?? 0,
        ),
      );
    }
  }
}

class TripAcceptanceException implements Exception {
  final TripErrorResponse error;

  TripAcceptanceException(this.error);

  @override
  String toString() => 'TripAcceptanceException: ${error.userFriendlyMessage}';
}

class TripRejectionException implements Exception {
  final TripErrorResponse error;

  TripRejectionException(this.error);

  @override
  String toString() => 'TripRejectionException: ${error.userFriendlyMessage}';
}
