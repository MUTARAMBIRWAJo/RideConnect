import 'package:supabase_flutter/supabase_flutter.dart';

void subscribeToTripRealtime(int tripId) {
  final supabase = Supabase.instance.client;

  final tripChannel = supabase.channel('trip:$tripId')
    ..onBroadcast(
      event: 'driver_location_update',
      callback: (payload) {
        final lat = payload['lat'] as double;
        final lng = payload['lng'] as double;
        // Update marker on Google Maps
      },
    )
    ..onBroadcast(
      event: 'trip_accepted',
      callback: (payload) {
        // Show driver info card, start showing driver location on map
      },
    )
    ..onBroadcast(
      event: 'trip_status_changed',
      callback: (payload) {
        final newStatus = payload['status'] as String;
        // Update UI based on new status
      },
    )
    ..subscribe();

  supabase
      .from('trips')
      .stream(primaryKey: ['id'])
      .eq('id', tripId)
      .listen((List<Map<String, dynamic>> data) {
        if (data.isNotEmpty) {
          final trip = data.first;
          // Handle status updates: accepted, enroute_to_pickup, in_progress, completed
        }
      });

  // await supabase.removeChannel(tripChannel);
}
