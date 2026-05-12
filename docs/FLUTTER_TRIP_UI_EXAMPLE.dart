// File: lib/screens/trip_notification_screen.dart
// Flutter UI implementation for trip acceptance/rejection with proper error handling

import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../services/trip_service.dart';

class TripNotificationWidget extends StatefulWidget {
  final int tripId;
  final String pickupLocation;
  final String dropoffLocation;
  final String passengerName;

  const TripNotificationWidget({
    Key? key,
    required this.tripId,
    required this.pickupLocation,
    required this.dropoffLocation,
    required this.passengerName,
  }) : super(key: key);

  @override
  State<TripNotificationWidget> createState() => _TripNotificationWidgetState();
}

class _TripNotificationWidgetState extends State<TripNotificationWidget> {
  bool _isLoading = false;
  String? _errorMessage;

  @override
  Widget build(BuildContext context) {
    return Card(
      elevation: 2,
      child: Padding(
        padding: const EdgeInsets.all(16.0),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            // Trip Header
            Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                const Text(
                  'New Ride Request',
                  style: TextStyle(
                    fontSize: 18,
                    fontWeight: FontWeight.bold,
                    color: Colors.green,
                  ),
                ),
                Text(
                  DateTime.now().toString().substring(0, 16),
                  style: const TextStyle(fontSize: 12, color: Colors.grey),
                ),
              ],
            ),
            const SizedBox(height: 12),

            // Passenger Info
            ListTile(
              leading: const Icon(Icons.person, color: Colors.blue),
              title: Text(widget.passengerName),
              subtitle: const Text('Passenger'),
              dense: true,
            ),

            // Trip Details
            ListTile(
              leading: const Icon(Icons.location_on, color: Colors.red),
              title: Text(
                widget.pickupLocation,
                maxLines: 2,
                overflow: TextOverflow.ellipsis,
              ),
              subtitle: const Text('Pickup'),
              dense: true,
            ),
            ListTile(
              leading: const Icon(Icons.flag, color: Colors.orange),
              title: Text(
                widget.dropoffLocation,
                maxLines: 2,
                overflow: TextOverflow.ellipsis,
              ),
              subtitle: const Text('Dropoff'),
              dense: true,
            ),

            const SizedBox(height: 8),

            // Error Message (if any)
            if (_errorMessage != null)
              Container(
                padding: const EdgeInsets.all(8),
                decoration: BoxDecoration(
                  color: Colors.red.shade50,
                  border: Border.all(color: Colors.red, width: 1),
                  borderRadius: BorderRadius.circular(4),
                ),
                child: Row(
                  children: [
                    const Icon(Icons.error_outline, color: Colors.red, size: 20),
                    const SizedBox(width: 8),
                    Expanded(
                      child: Text(
                        _errorMessage!,
                        style: const TextStyle(color: Colors.red, fontSize: 12),
                        maxLines: 3,
                        overflow: TextOverflow.ellipsis,
                      ),
                    ),
                  ],
                ),
              ),

            const SizedBox(height: 12),

            // Action Buttons
            Row(
              children: [
                // Reject Button
                Expanded(
                  child: OutlinedButton.icon(
                    onPressed: _isLoading ? null : () => _rejectTrip(context),
                    icon: const Icon(Icons.close),
                    label: const Text('Reject'),
                    style: OutlinedButton.styleFrom(
                      side: BorderSide(
                        color: _isLoading ? Colors.grey : Colors.red,
                      ),
                      foregroundColor: _isLoading ? Colors.grey : Colors.red,
                    ),
                  ),
                ),
                const SizedBox(width: 12),
                // Accept Button
                Expanded(
                  child: ElevatedButton.icon(
                    onPressed: _isLoading ? null : () => _acceptTrip(context),
                    icon: _isLoading
                        ? SizedBox(
                            height: 16,
                            width: 16,
                            child: CircularProgressIndicator(
                              strokeWidth: 2,
                              valueColor:
                                  AlwaysStoppedAnimation<Color>(Colors.white),
                            ),
                          )
                        : const Icon(Icons.check),
                    label: Text(_isLoading ? 'Processing...' : 'Accept'),
                    style: ElevatedButton.styleFrom(
                      backgroundColor: Colors.green,
                      disabledBackgroundColor: Colors.grey,
                    ),
                  ),
                ),
              ],
            ),
          ],
        ),
      ),
    );
  }

  Future<void> _acceptTrip(BuildContext context) async {
    setState(() {
      _isLoading = true;
      _errorMessage = null;
    });

    try {
      final tripService = context.read<TripService>();
      final result = await tripService.acceptTrip(widget.tripId);

      if (!mounted) return;

      // Show success notification
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text('Trip ${result.tripId} accepted!'),
          backgroundColor: Colors.green,
          duration: const Duration(seconds: 2),
        ),
      );

      // Close notification after brief delay
      await Future.delayed(const Duration(milliseconds: 500));
      if (mounted) {
        Navigator.of(context).pop();
      }
    } on TripAcceptanceException catch (e) {
      setState(() {
        _errorMessage = e.error.userFriendlyMessage;
        _isLoading = false;
      });

      // Log the error type for analytics
      debugPrint('Trip acceptance error: ${e.error.type}');
      debugPrint('Error code: ${e.error.code}');
      debugPrint('Current status: ${e.error.currentStatus}');
    } catch (e) {
      setState(() {
        _errorMessage = 'Unexpected error: $e';
        _isLoading = false;
      });
    }
  }

  Future<void> _rejectTrip(BuildContext context) async {
    // Confirmation dialog before rejecting
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Reject Trip?'),
        content: const Text(
          'Are you sure you want to reject this trip? You can see other available trips.',
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context, false),
            child: const Text('Cancel'),
          ),
          TextButton(
            onPressed: () => Navigator.pop(context, true),
            child: const Text('Reject'),
          ),
        ],
      ),
    );

    if (confirmed != true) return;

    setState(() {
      _isLoading = true;
      _errorMessage = null;
    });

    try {
      final tripService = context.read<TripService>();
      await tripService.rejectTrip(widget.tripId);

      if (!mounted) return;

      // Show success notification
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('Trip rejected. Looking for other trips...'),
          backgroundColor: Colors.orange,
          duration: Duration(seconds: 2),
        ),
      );

      // Close notification
      if (mounted) {
        Navigator.of(context).pop();
      }
    } on TripRejectionException catch (e) {
      setState(() {
        _errorMessage = e.error.userFriendlyMessage;
        _isLoading = false;
      });

      debugPrint('Trip rejection error: ${e.error.type}');
    } catch (e) {
      setState(() {
        _errorMessage = 'Unexpected error: $e';
        _isLoading = false;
      });
    }
  }
}

// Usage example in a screen:
class AvailableTripsScreen extends StatelessWidget {
  const AvailableTripsScreen({Key? key}) : super(key: key);

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Available Trips'),
        backgroundColor: Colors.blue,
      ),
      body: ListView(
        children: [
          // Example notification
          Padding(
            padding: const EdgeInsets.all(8.0),
            child: TripNotificationWidget(
              tripId: 4,
              pickupLocation: '123 Main Street, Kigali',
              dropoffLocation: '456 Business Park, Kigali',
              passengerName: 'John Doe',
            ),
          ),
          // More trips...
        ],
      ),
    );
  }
}
