// File: lib/exceptions/trip_exceptions.dart
// Custom exceptions for trip-related errors
// Last Updated: May 29, 2026

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
    'Invalid trip ID: $tripId. Trip ID must be a positive integer.',
    code: 'INVALID_TRIP_ID',
    httpStatusCode: 400,
  );
}

class NoDriversAvailableException extends TripException {
  NoDriversAvailableException()
      : super(
        'No drivers available in your area at this time. Please try again later.',
        code: 'NO_DRIVERS_AVAILABLE',
        httpStatusCode: 404,
      );
}

class MatchingSessionExpiredException extends TripException {
  MatchingSessionExpiredException()
      : super(
        'Matching session has expired. Please request a new trip.',
        code: 'SESSION_EXPIRED',
        httpStatusCode: 408,
      );
}

class UnauthorizedException extends TripException {
  UnauthorizedException(String message)
      : super(
        message,
        code: 'UNAUTHORIZED',
        httpStatusCode: 401,
      );
}

class ForbiddenException extends TripException {
  ForbiddenException(String message)
      : super(
        message,
        code: 'FORBIDDEN',
        httpStatusCode: 403,
      );
}

class ValidationException extends TripException {
  final Map<String, dynamic>? errors;

  ValidationException(
    String message, {
    this.errors,
  }) : super(
    message,
    code: 'VALIDATION_ERROR',
    httpStatusCode: 422,
  );
}

class NetworkException extends TripException {
  NetworkException(String message)
      : super(
        message,
        code: 'NETWORK_ERROR',
        httpStatusCode: null,
      );
}

class TimeoutException extends TripException {
  TimeoutException(String message)
      : super(
        message,
        code: 'TIMEOUT',
        httpStatusCode: 408,
      );
}

class ServerException extends TripException {
  ServerException(String message)
      : super(
        message,
        code: 'SERVER_ERROR',
        httpStatusCode: 500,
      );
}
