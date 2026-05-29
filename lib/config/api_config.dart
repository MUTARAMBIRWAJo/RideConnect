// File: lib/config/api_config.dart
// RideConnect API Configuration for Production
// Last Updated: May 29, 2026

class ApiConfig {
  // Production URLs
  static const String baseUrl = 'https://rideconnect-emp0.onrender.com';
  static const String mlServiceUrl = 'https://ml-service-j72g.onrender.com';
  
  // API Paths
  static const String apiVersion = '/api/v1';
  static const String mobileApiPath = '/api/v1/mobile';
  
  // Full URLs for services
  static const String authBaseUrl = '$baseUrl$apiVersion/auth';
  static const String tripBaseUrl = '$baseUrl$mobileApiPath/trips';
  static const String driverBaseUrl = '$baseUrl$mobileApiPath/drivers';
  static const String passengerBaseUrl = '$baseUrl$apiVersion/passenger';
  static const String mlPredictionUrl = '$mlServiceUrl/predict';
  
  // Timeouts
  static const int connectTimeout = 30000; // 30 seconds
  static const int receiveTimeout = 30000; // 30 seconds
  static const int sendTimeout = 30000;    // 30 seconds
  
  // Retry configuration
  static const int maxRetries = 3;
  static const int retryDelayMs = 1000;
  
  // Feature flags
  static const bool enableLogging = true;
  static const bool validateCertificates = true;
  
  /// Get full URL for a given endpoint
  static String getUrl(String endpoint, {String? service}) {
    final serviceUrl = service ?? baseUrl;
    return serviceUrl + endpoint;
  }
  
  /// Get authentication URL
  static String getAuthUrl(String path) => '$authBaseUrl$path';
  
  /// Get trip URL
  static String getTripUrl(String path) => '$tripBaseUrl$path';
  
  /// Get driver URL
  static String getDriverUrl(String path) => '$driverBaseUrl$path';
  
  /// Get passenger URL
  static String getPassengerUrl(String path) => '$passengerBaseUrl$path';
  
  /// Get ML service URL
  static String getMlUrl(String path) => '$mlServiceUrl$path';
}

/// Environment-specific configuration
enum Environment {
  production,
  staging,
  development,
}

class EnvironmentConfig {
  static Environment current = Environment.production;
  
  static String getBaseUrl() {
    switch (current) {
      case Environment.production:
        return 'https://rideconnect-emp0.onrender.com';
      case Environment.staging:
        return 'https://staging-rideconnect.onrender.com';
      case Environment.development:
        return 'http://localhost:8000';
    }
  }
  
  static String getMlServiceUrl() {
    switch (current) {
      case Environment.production:
        return 'https://ml-service-j72g.onrender.com';
      case Environment.staging:
        return 'https://staging-ml-service.onrender.com';
      case Environment.development:
        return 'http://localhost:5000';
    }
  }
}

/// API Endpoints - Used for type-safe URL construction
class ApiEndpoints {
  // Authentication
  static const String login = '/auth/mobile/login';
  static const String register = '/auth/register';
  static const String logout = '/auth/logout';
  static const String validateToken = '/auth/token/validate';
  
  // Trips
  static String tripDetail(int tripId) => '$mobileApiPath/trips/$tripId';
  static String cancelTrip(int tripId) => '$mobileApiPath/trips/$tripId/cancel';
  static String tripStatus(int tripId) => '$mobileApiPath/trips/$tripId/status';
  static String trackTrip(int tripId) => '$mobileApiPath/trips/$tripId/track';
  static const String requestTrip = '$mobileApiPath/trips/request';
  static const String currentTrip = '$mobileApiPath/trips/current';
  
  // Drivers
  static const String availableDrivers = '$mobileApiPath/drivers/match';
  static String acceptTrip(int tripId) => '$mobileApiPath/drivers/trips/$tripId/accept';
  static String rejectTrip(int tripId) => '$mobileApiPath/drivers/trips/$tripId/reject';
  static String startTrip(int tripId) => '$mobileApiPath/drivers/trips/$tripId/start';
  static String completeTrip(int tripId) => '$mobileApiPath/drivers/trips/$tripId/complete';
  
  // Matching
  static String matchingSession(String sessionId) => '$mobileApiPath/trips/$sessionId/matching-session';
  
  // User
  static const String userProfile = '/auth/profile';
  static const String updateProfile = '/auth/profile';
}

/// HTTP Header constants
class ApiHeaders {
  static const String contentType = 'Content-Type';
  static const String authorization = 'Authorization';
  static const String acceptJson = 'Accept';
  
  static const String contentTypeJson = 'application/json';
  static const String acceptJsonValue = 'application/json';
  
  static Map<String, String> defaultHeaders({String? token}) {
    return {
      contentType: contentTypeJson,
      acceptJson: acceptJsonValue,
      if (token != null) authorization: 'Bearer $token',
    };
  }
}
