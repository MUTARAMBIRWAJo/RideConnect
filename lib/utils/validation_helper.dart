// File: lib/utils/validation_helper.dart
// Validation utilities for trip operations
// Last Updated: May 29, 2026

import '../exceptions/trip_exceptions.dart';

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
      throw InvalidTripIdException(tripId);
    }
    return tripId!;
  }

  /// Validate latitude coordinate
  static bool isValidLatitude(double? lat) {
    return lat != null && lat >= -90 && lat <= 90;
  }

  /// Validate longitude coordinate
  static bool isValidLongitude(double? lng) {
    return lng != null && lng >= -180 && lng <= 180;
  }

  /// Validate coordinates
  static bool isValidCoordinates(double? lat, double? lng) {
    return isValidLatitude(lat) && isValidLongitude(lng);
  }

  /// Assert coordinates are valid
  static void assertValidCoordinates(double? lat, double? lng) {
    if (!isValidLatitude(lat)) {
      throw ArgumentError('Invalid latitude: $lat. Must be between -90 and 90.');
    }
    if (!isValidLongitude(lng)) {
      throw ArgumentError('Invalid longitude: $lng. Must be between -180 and 180.');
    }
  }

  /// Validate location string
  static bool isValidLocation(String? location) {
    return location != null && location.trim().isNotEmpty && location.length >= 3;
  }

  /// Assert location is valid
  static String assertValidLocation(String? location, {String fieldName = 'Location'}) {
    if (!isValidLocation(location)) {
      throw ValidationException('$fieldName must be at least 3 characters long');
    }
    return location!.trim();
  }

  /// Validate email
  static bool isValidEmail(String? email) {
    if (email == null || email.isEmpty) return false;
    final emailRegex = RegExp(
      r'^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$',
    );
    return emailRegex.hasMatch(email);
  }

  /// Validate phone number
  static bool isValidPhone(String? phone) {
    if (phone == null || phone.isEmpty) return false;
    final phoneRegex = RegExp(r'^[\d+\-\s()]+$');
    return phoneRegex.hasMatch(phone) && phone.replaceAll(RegExp(r'[^\d]'), '').length >= 10;
  }

  /// Validate password strength
  static Map<String, bool> validatePassword(String? password) {
    if (password == null || password.isEmpty) {
      return {
        'isValid': false,
        'hasMinLength': false,
        'hasUppercase': false,
        'hasLowercase': false,
        'hasNumber': false,
        'hasSpecial': false,
      };
    }

    return {
      'isValid': password.length >= 8,
      'hasMinLength': password.length >= 8,
      'hasUppercase': password.contains(RegExp(r'[A-Z]')),
      'hasLowercase': password.contains(RegExp(r'[a-z]')),
      'hasNumber': password.contains(RegExp(r'[0-9]')),
      'hasSpecial': password.contains(RegExp(r'[!@#\$%^&*(),.?":{}|<>]')),
    };
  }

  /// Validate transport type
  static bool isValidTransportType(String? transportType) {
    final validTypes = ['motor_vehicle', 'motorcycle', 'moto', 'car', 'bike'];
    return transportType != null && validTypes.contains(transportType.toLowerCase());
  }

  /// Validate fare amount
  static bool isValidFare(double? fare) {
    return fare != null && fare > 0;
  }

  /// Assert fare is valid
  static double assertValidFare(double? fare) {
    if (!isValidFare(fare)) {
      throw ValidationException('Fare must be a positive number');
    }
    return fare!;
  }

  /// Validate trip status
  static bool isValidTripStatus(String? status) {
    final validStatuses = [
      'PENDING',
      'ACCEPTED',
      'STARTED',
      'COMPLETED',
      'CANCELLED',
    ];
    return status != null && validStatuses.contains(status.toUpperCase());
  }

  /// Validate datetime
  static bool isValidDateTime(DateTime? dateTime) {
    if (dateTime == null) return false;
    return dateTime.isBefore(DateTime.now().add(Duration(days: 365))) &&
        dateTime.isAfter(DateTime.now().subtract(Duration(days: 365)));
  }

  /// Sanitize string input
  static String sanitizeInput(String? input) {
    if (input == null) return '';
    return input.trim().replaceAll(RegExp(r'\s+'), ' ');
  }

  /// Validate UUID format
  static bool isValidUUID(String? uuid) {
    if (uuid == null || uuid.isEmpty) return false;
    final uuidRegex = RegExp(
      r'^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$',
      caseSensitive: false,
    );
    return uuidRegex.hasMatch(uuid);
  }

  /// Get human-readable validation error messages
  static String getErrorMessage(dynamic error) {
    if (error is InvalidTripIdException) {
      return 'Invalid trip ID. Please provide a valid trip.';
    } else if (error is TripNotFoundException) {
      return 'Trip not found. It may have been cancelled or completed.';
    } else if (error is ValidationException) {
      return error.message;
    } else if (error is ArgumentError) {
      return error.message ?? 'Invalid input';
    } else {
      return 'An unexpected error occurred.';
    }
  }
}
