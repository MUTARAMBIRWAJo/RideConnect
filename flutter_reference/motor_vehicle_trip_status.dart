// REFERENCE FILE for the Flutter app.
// Drop into: lib/features/trips/models/motor_vehicle_trip_status.dart
//
// Models the GET /passenger/motor-vehicle/trip-requests/{id} response and maps
// the backend state machine onto the UI lifecycle the matching page renders.

/// UI-facing lifecycle phases. The whole UI switches on this — never on the raw
/// backend strings.
enum TripLifecyclePhase {
  searching, // looking for a driver
  matched, // a driver was assigned (pre-arrival)
  driverArrived, // driver is at the pickup
  tripStarted, // ride in progress
  tripCompleted, // ride finished -> payment/rating
  noDriversFound, // matching exhausted (terminal)
  cancelled, // cancelled by passenger or driver (terminal)
  expired, // timed out (terminal)
  unknown, // unrecognized backend value -> keep previous UI, log
}

extension TripLifecyclePhaseX on TripLifecyclePhase {
  bool get isTerminal =>
      this == TripLifecyclePhase.tripCompleted ||
      this == TripLifecyclePhase.noDriversFound ||
      this == TripLifecyclePhase.cancelled ||
      this == TripLifecyclePhase.expired;
}

class TripDriver {
  const TripDriver({
    this.id,
    this.name,
    this.phone,
    this.rating,
    this.vehiclePlate,
    this.lat,
    this.lng,
  });

  final int? id;
  final String? name;
  final String? phone;
  final double? rating;
  final String? vehiclePlate;
  final double? lat;
  final double? lng;

  bool get hasLocation => lat != null && lng != null;

  factory TripDriver.fromJson(Map<String, dynamic> json) {
    final loc = json['location'];
    return TripDriver(
      id: json['id'] as int?,
      name: json['name'] as String?,
      phone: json['phone'] as String?,
      rating: _toDouble(json['rating']),
      vehiclePlate: json['vehicle_plate'] as String?,
      lat: loc is Map ? _toDouble(loc['lat']) : null,
      lng: loc is Map ? _toDouble(loc['lng']) : null,
    );
  }
}

/// Parsed `data` object from GET /passenger/motor-vehicle/trip-requests/{id}.
class MotorVehicleTripStatus {
  const MotorVehicleTripStatus({
    required this.tripId,
    required this.status,
    required this.matchingStatus,
    required this.phase,
    this.driver,
    this.estimatedFare,
    this.actualFare,
    this.currency,
    this.retryCount,
    this.maxRetries,
    this.raw = const {},
  });

  final int tripId;
  final String status; // exact backend status
  final String? matchingStatus; // exact backend matching_status
  final TripLifecyclePhase phase; // mapped UI phase
  final TripDriver? driver;
  final num? estimatedFare;
  final num? actualFare;
  final String? currency;
  final int? retryCount;
  final int? maxRetries;
  final Map<String, dynamic> raw;

  factory MotorVehicleTripStatus.fromJson(Map<String, dynamic> data) {
    final status = (data['status'] ?? '').toString();
    final matching = data['matching_status']?.toString();
    final driverJson = data['driver'];
    return MotorVehicleTripStatus(
      tripId: (data['trip_id'] as num?)?.toInt() ?? 0,
      status: status,
      matchingStatus: matching,
      phase: mapPhase(status, matching),
      driver: driverJson is Map<String, dynamic>
          ? TripDriver.fromJson(driverJson)
          : null,
      estimatedFare: data['estimated_fare'] as num?,
      actualFare: data['actual_fare'] as num?,
      currency: data['currency'] as String?,
      retryCount: (data['retry_count'] as num?)?.toInt(),
      maxRetries: (data['max_retries'] as num?)?.toInt(),
      raw: data,
    );
  }

  /// Pure mapper — the single source of truth for backend -> UI translation.
  /// Backend status enum:
  ///   REQUESTED, MATCHING, MATCHING_PENDING, ASSIGNED, DRIVER_ASSIGNED,
  ///   PASSENGER_WAITING, IN_PROGRESS, COMPLETED, REJECTED_BY_DRIVER,
  ///   CANCELLED_BY_PASSENGER, CANCELLED_BY_DRIVER, EXPIRED
  /// Backend matching_status enum:
  ///   SEARCHING, RETRY_SCHEDULED, RETRYING, DRIVER_FOUND, FAILED_MAX_RETRIES
  static TripLifecyclePhase mapPhase(String status, String? matchingStatus) {
    switch (status) {
      case 'REQUESTED':
      case 'MATCHING':
      case 'MATCHING_PENDING':
        switch (matchingStatus) {
          case 'DRIVER_FOUND':
            return TripLifecyclePhase.matched;
          case 'FAILED_MAX_RETRIES':
            return TripLifecyclePhase.noDriversFound;
          case 'SEARCHING':
          case 'RETRY_SCHEDULED':
          case 'RETRYING':
          default:
            return TripLifecyclePhase.searching;
        }
      case 'ASSIGNED':
      case 'DRIVER_ASSIGNED':
        return TripLifecyclePhase.matched;
      case 'PASSENGER_WAITING':
        return TripLifecyclePhase.driverArrived;
      case 'IN_PROGRESS':
        return TripLifecyclePhase.tripStarted;
      case 'COMPLETED':
        return TripLifecyclePhase.tripCompleted;
      case 'REJECTED_BY_DRIVER':
        // Backend re-matches after a rejection; treat as still searching unless
        // matching has failed outright.
        return matchingStatus == 'FAILED_MAX_RETRIES'
            ? TripLifecyclePhase.noDriversFound
            : TripLifecyclePhase.searching;
      case 'CANCELLED_BY_PASSENGER':
      case 'CANCELLED_BY_DRIVER':
        return TripLifecyclePhase.cancelled;
      case 'EXPIRED':
        return TripLifecyclePhase.expired;
      default:
        return TripLifecyclePhase.unknown;
    }
  }
}

double? _toDouble(dynamic v) {
  if (v == null) return null;
  if (v is num) return v.toDouble();
  return double.tryParse(v.toString());
}
