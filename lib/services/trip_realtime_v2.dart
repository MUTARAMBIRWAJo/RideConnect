import 'package:supabase_flutter/supabase_flutter.dart';

class TripRealtimeV2 {
  final SupabaseClient _supabase = Supabase.instance.client;
  RealtimeChannel? _tripChannel;
  RealtimeChannel? _driverChannel;

  void subscribeAsPassenger({
    required int tripId,
    required void Function(double lat, double lng, double? speedKmh)
        onDriverLocation,
    required void Function(Map<String, dynamic> driver) onTripAccepted,
    required void Function(String status) onStatusChanged,
    required void Function() onTripCancelled,
  }) {
    _tripChannel = _supabase.channel('trip:$tripId')
      ..onBroadcast(
        event: 'driver_location_update',
        callback: (payload) {
          onDriverLocation(
            (payload['lat'] as num).toDouble(),
            (payload['lng'] as num).toDouble(),
            (payload['speed_kmh'] as num?)?.toDouble(),
          );
        },
      )
      ..onBroadcast(
        event: 'trip_accepted',
        callback: (payload) => onTripAccepted(payload),
      )
      ..onBroadcast(
        event: 'trip_status_changed',
        callback: (payload) {
          final status = payload['status'] as String? ?? '';
          if (status == 'cancelled') {
            onTripCancelled();
          } else {
            onStatusChanged(status);
          }
        },
      )
      ..onPostgresChanges(
        event: PostgresChangeEvent.update,
        schema: 'public',
        table: 'trips',
        filter: PostgresChangeFilter(
          type: PostgresChangeFilterType.eq,
          column: 'id',
          value: tripId,
        ),
        callback: (payload) {
          final status = payload.newRecord['status'] as String? ?? '';
          onStatusChanged(status);
        },
      )
      ..subscribe();
  }

  void subscribeAsDriver({
    required int driverId,
    required void Function(Map<String, dynamic> tripRequest) onNewTripRequest,
    required void Function(int tripId) onRequestExpired,
  }) {
    _driverChannel = _supabase.channel('driver:$driverId')
      ..onBroadcast(
        event: 'new_trip_request',
        callback: (payload) => onNewTripRequest(payload),
      )
      ..onBroadcast(
        event: 'request_expired',
        callback: (payload) {
          final tripId = payload['trip_id'] as int? ?? 0;
          onRequestExpired(tripId);
        },
      )
      ..subscribe();
  }

  Future<void> unsubscribe() async {
    if (_tripChannel != null) await _supabase.removeChannel(_tripChannel!);
    if (_driverChannel != null) await _supabase.removeChannel(_driverChannel!);
    _tripChannel = null;
    _driverChannel = null;
  }
}
