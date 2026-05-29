class ApiConfig {
  static const bool _useProduction =
      bool.fromEnvironment('USE_PRODUCTION', defaultValue: false);

  static const String _localLaravelBase = 'http://10.0.2.2:8000';
  static const String _prodLaravelBase = 'https://rideconnect-emp0.onrender.com';

  static String get laravelBase =>
      _useProduction ? _prodLaravelBase : _localLaravelBase;

  static String get baseUrl => laravelBase;
  static const String mlServiceUrl = 'https://ml-service-j72g.onrender.com';

  static const String apiVersion = '/api/v1';
  static const String mobileApiPath = '/api/v1/mobile';
  static String get apiV1 => '$laravelBase/api/v1';
  static String get apiV2 => '$laravelBase/api/v2';

  static String get authBaseUrl => '$laravelBase$apiVersion/auth';
  static String get tripBaseUrl => '$laravelBase$mobileApiPath/trips';
  static String get driverBaseUrl => '$laravelBase$mobileApiPath/drivers';
  static String get passengerBaseUrl => '$laravelBase$apiVersion/passenger';
  static const String mlPredictionUrl = '$mlServiceUrl/predict';

  static String get createTrip => '$apiV2/trips';
  static String tripById(int id) => '$apiV2/trips/$id';
  static String tripRespond(int id) => '$apiV2/trips/$id/respond';
  static String tripStatus(int id) => '$apiV2/trips/$id/status';
  static String tripCancel(int id) => '$apiV2/trips/$id/cancel';

  static String get updateDriverLocation => '$apiV2/driver/location';

  static String get notifications => '$apiV2/notifications';
  static String markNotificationRead(int id) => '$apiV2/notifications/$id/read';

  static const String supabaseUrl =
      'https://tpahuvmhlfluztuhznfj.supabase.co';
  static const String supabaseAnonKey =
      'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6InRwYWh1dm1obGZsdXp0dWh6bmZqIiwicm9sZSI6ImFub24iLCJpYXQiOjE3NjU5OTg1ODQsImV4cCI6MjA4MTU3NDU4NH0.iwwdBlMDl-X97_ozEEaEBah8vBQ5Em02f0praEr6m0U';

  static const int connectTimeout = 30000;
  static const int receiveTimeout = 30000;
  static const int sendTimeout = 30000;
  static const int maxRetries = 3;
  static const int retryDelayMs = 1000;
  static const bool enableLogging = true;
  static const bool validateCertificates = true;

  static String getUrl(String endpoint, {String? service}) {
    final serviceUrl = service ?? laravelBase;
    return serviceUrl + endpoint;
  }

  static String getAuthUrl(String path) => '$authBaseUrl$path';
  static String getTripUrl(String path) => '$tripBaseUrl$path';
  static String getDriverUrl(String path) => '$driverBaseUrl$path';
  static String getPassengerUrl(String path) => '$passengerBaseUrl$path';
  static String getMlUrl(String path) => '$mlServiceUrl$path';
}

enum Environment {
  production,
  staging,
  development,
}

class EnvironmentConfig {
  static Environment current =
      ApiConfig._useProduction ? Environment.production : Environment.development;

  static String getBaseUrl() {
    switch (current) {
      case Environment.production:
        return 'https://rideconnect-emp0.onrender.com';
      case Environment.staging:
        return 'https://staging-rideconnect.onrender.com';
      case Environment.development:
        return 'http://10.0.2.2:8000';
    }
  }

  static String getMlServiceUrl() {
    switch (current) {
      case Environment.production:
        return 'https://ml-service-j72g.onrender.com';
      case Environment.staging:
        return 'https://staging-ml-service.onrender.com';
      case Environment.development:
        return 'http://10.0.2.2:8001';
    }
  }
}

class ApiEndpoints {
  static const String login = '/auth/mobile/login';
  static const String register = '/auth/register';
  static const String logout = '/auth/logout';
  static const String validateToken = '/auth/token/validate';

  static String tripDetail(int tripId) => '${ApiConfig.mobileApiPath}/trips/$tripId';
  static String cancelTrip(int tripId) => '${ApiConfig.mobileApiPath}/trips/$tripId/cancel';
  static String tripStatus(int tripId) => '${ApiConfig.mobileApiPath}/trips/$tripId/status';
  static String trackTrip(int tripId) => '${ApiConfig.mobileApiPath}/trips/$tripId/track';
  static const String requestTrip = '${ApiConfig.mobileApiPath}/trips/request';
  static const String currentTrip = '${ApiConfig.mobileApiPath}/trips/current';

  static const String availableDrivers = '${ApiConfig.mobileApiPath}/drivers/match';
  static String acceptTrip(int tripId) =>
      '${ApiConfig.mobileApiPath}/drivers/trips/$tripId/accept';
  static String rejectTrip(int tripId) =>
      '${ApiConfig.mobileApiPath}/drivers/trips/$tripId/reject';
  static String startTrip(int tripId) =>
      '${ApiConfig.mobileApiPath}/drivers/trips/$tripId/start';
  static String completeTrip(int tripId) =>
      '${ApiConfig.mobileApiPath}/drivers/trips/$tripId/complete';

  static String matchingSession(String sessionId) =>
      '${ApiConfig.mobileApiPath}/trips/$sessionId/matching-session';

  static const String userProfile = '/auth/profile';
  static const String updateProfile = '/auth/profile';
}

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
