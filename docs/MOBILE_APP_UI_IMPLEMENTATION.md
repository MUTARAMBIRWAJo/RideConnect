# Mobile App UI Implementation Specifications

**RideConnect Mobile Application**  
**Platform:** Flutter (iOS & Android)  
**Version:** 2.0  
**Last Updated:** May 2026

---

## Table of Contents

1. [Project Structure](#project-structure)
2. [Screen Implementations](#screen-implementations)
3. [Widget Library](#widget-library)
4. [State Management](#state-management)
5. [API Integration](#api-integration)
6. [Realtime Integration](#realtime-integration)
7. [Navigation](#navigation)
8. [Testing](#testing)

---

## Project Structure

```
ride_connect_mobile/
├── lib/
│   ├── main.dart
│   ├── config/
│   │   ├── app_config.dart
│   │   ├── api_config.dart
│   │   ├── theme.dart
│   │   └── routes.dart
│   │
│   ├── features/
│   │   ├── auth/
│   │   │   ├── presentation/
│   │   │   │   ├── pages/
│   │   │   │   │   ├── login_page.dart
│   │   │   │   │   ├── register_page.dart
│   │   │   │   │   └── splash_page.dart
│   │   │   │   ├── widgets/
│   │   │   │   │   └── auth_form.dart
│   │   │   │   └── bloc/
│   │   │   │       └── auth_bloc.dart
│   │   │   ├── domain/
│   │   │   └── data/
│   │   │
│   │   ├── passenger/
│   │   │   ├── presentation/
│   │   │   │   ├── pages/
│   │   │   │   │   ├── home_page.dart
│   │   │   │   │   ├── rides_search_page.dart
│   │   │   │   │   ├── booking_page.dart
│   │   │   │   │   ├── trips_page.dart
│   │   │   │   │   ├── bookings_page.dart
│   │   │   │   │   ├── profile_page.dart
│   │   │   │   │   ├── trip_tracking_page.dart
│   │   │   │   │   └── rating_page.dart
│   │   │   │   ├── widgets/
│   │   │   │   │   ├── ride_card.dart
│   │   │   │   │   ├── location_selector.dart
│   │   │   │   │   ├── payment_selector.dart
│   │   │   │   │   ├── trip_tracking_map.dart
│   │   │   │   │   ├── rating_widget.dart
│   │   │   │   │   └── trip_details_panel.dart
│   │   │   │   └── bloc/
│   │   │   │       ├── rides_bloc.dart
│   │   │   │       ├── booking_bloc.dart
│   │   │   │       ├── trips_bloc.dart
│   │   │   │       └── payment_bloc.dart
│   │   │   ├── domain/
│   │   │   └── data/
│   │   │
│   │   ├── driver/
│   │   │   ├── presentation/
│   │   │   │   ├── pages/
│   │   │   │   │   ├── dashboard_page.dart
│   │   │   │   │   ├── trip_request_page.dart
│   │   │   │   │   ├── trip_execution_page.dart
│   │   │   │   │   ├── earnings_page.dart
│   │   │   │   │   ├── profile_page.dart
│   │   │   │   │   └── documents_page.dart
│   │   │   │   ├── widgets/
│   │   │   │   │   ├── trip_request_card.dart
│   │   │   │   │   ├── active_trip_panel.dart
│   │   │   │   │   ├── navigation_widget.dart
│   │   │   │   │   ├── earnings_chart.dart
│   │   │   │   │   └── document_uploader.dart
│   │   │   │   └── bloc/
│   │   │   │       ├── driver_bloc.dart
│   │   │   │       ├── trip_management_bloc.dart
│   │   │   │       └── earnings_bloc.dart
│   │   │   ├── domain/
│   │   │   └── data/
│   │   │
│   │   └── shared/
│   │       ├── presentation/
│   │       │   ├── widgets/
│   │       │   │   ├── custom_button.dart
│   │       │   │   ├── custom_input.dart
│   │       │   │   ├── loading_indicator.dart
│   │       │   │   ├── error_widget.dart
│   │       │   │   ├── success_dialog.dart
│   │       │   │   ├── confirmation_dialog.dart
│   │       │   │   └── notification_banner.dart
│   │       │   └── pages/
│   │       │       ├── support_page.dart
│   │       │       └── settings_page.dart
│   │       ├── domain/
│   │       └── data/
│   │
│   ├── services/
│   │   ├── api_service.dart
│   │   ├── realtime_service.dart
│   │   ├── location_service.dart
│   │   ├── payment_service.dart
│   │   ├── notification_service.dart
│   │   ├── cache_service.dart
│   │   └── analytics_service.dart
│   │
│   └── utils/
│       ├── extensions.dart
│       ├── constants.dart
│       ├── validators.dart
│       └── formatters.dart
│
├── test/
│   ├── unit/
│   ├── widget/
│   └── integration/
│
└── pubspec.yaml
```

---

## Screen Implementations

### 1. Passenger Home Screen

```dart
class PassengerHomePage extends StatefulWidget {
  @override
  State<PassengerHomePage> createState() => _PassengerHomePageState();
}

class _PassengerHomePageState extends State<PassengerHomePage> {
  late final LocationService _locationService;
  late final RealtimeService _realtimeService;

  @override
  void initState() {
    super.initState();
    _locationService = LocationService();
    _realtimeService = RealtimeService();
    _initializeLocation();
  }

  void _initializeLocation() async {
    final location = await _locationService.getCurrentLocation();
    context.read<RidesBloc>().add(UpdateCurrentLocationEvent(location));
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('RideConnect'),
        elevation: 0,
        backgroundColor: Colors.white,
        actions: [
          IconButton(
            icon: const Icon(Icons.notifications_outlined),
            onPressed: _showNotifications,
          ),
        ],
      ),
      body: BlocListener<AuthBloc, AuthState>(
        listener: (context, state) {
          if (state is AuthUnauthenticated) {
            Navigator.of(context).pushReplacementNamed('/login');
          }
        },
        child: SafeArea(
          child: SingleChildScrollView(
            child: Column(
              children: [
                // Quick Search Card
                _buildQuickSearchCard(context),
                const SizedBox(height: 24),

                // Recent Activity
                _buildRecentActivitySection(context),
              ],
            ),
          ),
        ),
      ),
      bottomNavigationBar: BottomNavigationBar(
        items: const [
          BottomNavigationBarItem(icon: Icon(Icons.home), label: 'Home'),
          BottomNavigationBarItem(icon: Icon(Icons.map), label: 'Rides'),
          BottomNavigationBarItem(icon: Icon(Icons.bookmark), label: 'Bookings'),
          BottomNavigationBarItem(icon: Icon(Icons.person), label: 'Profile'),
        ],
        onTap: _onNavTap,
      ),
    );
  }

  Widget _buildQuickSearchCard(BuildContext context) {
    return Card(
      margin: const EdgeInsets.all(16),
      elevation: 2,
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          children: [
            Text(
              'Where would you like to go?',
              style: Theme.of(context).textTheme.titleMedium,
            ),
            const SizedBox(height: 16),
            TextField(
              decoration: InputDecoration(
                hintText: 'Pick up location',
                prefixIcon: const Icon(Icons.location_on),
                border: OutlineInputBorder(
                  borderRadius: BorderRadius.circular(8),
                ),
              ),
            ),
            const SizedBox(height: 12),
            TextField(
              decoration: InputDecoration(
                hintText: 'Drop off location',
                prefixIcon: const Icon(Icons.location_on_outlined),
                border: OutlineInputBorder(
                  borderRadius: BorderRadius.circular(8),
                ),
              ),
            ),
            const SizedBox(height: 16),
            ElevatedButton.icon(
              onPressed: () => Navigator.pushNamed(context, '/rides-search'),
              icon: const Icon(Icons.search),
              label: const Text('Find Rides'),
              style: ElevatedButton.styleFrom(
                minimumSize: const Size.fromHeight(48),
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildRecentActivitySection(BuildContext context) {
    return BlocBuilder<TripsBloc, TripsState>(
      builder: (context, state) {
        if (state is TripsLoading) {
          return const Center(child: CircularProgressIndicator());
        }

        if (state is TripsLoaded) {
          final recentTrips = state.trips.take(3);

          return Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Padding(
                padding: const EdgeInsets.symmetric(horizontal: 16),
                child: Text(
                  'Recent Activity',
                  style: Theme.of(context).textTheme.titleLarge,
                ),
              ),
              const SizedBox(height: 12),
              ...recentTrips.map((trip) => TripCard(trip: trip)),
            ],
          );
        }

        return const SizedBox.shrink();
      },
    );
  }

  void _onNavTap(int index) {
    final routes = ['/home', '/rides', '/bookings', '/profile'];
    if (index != 0) {
      Navigator.pushNamed(context, routes[index]);
    }
  }

  void _showNotifications() {
    showModalBottomSheet(
      context: context,
      builder: (context) => Container(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              'Notifications',
              style: Theme.of(context).textTheme.titleLarge,
            ),
            // Notification list
          ],
        ),
      ),
    );
  }
}
```

### 2. Rides Search Page

```dart
class RidesSearchPage extends StatefulWidget {
  final String? pickupLocation;
  final String? dropoffLocation;

  const RidesSearchPage({
    this.pickupLocation,
    this.dropoffLocation,
  });

  @override
  State<RidesSearchPage> createState() => _RidesSearchPageState();
}

class _RidesSearchPageState extends State<RidesSearchPage> {
  late TextEditingController _pickupController;
  late TextEditingController _dropoffController;
  String _selectedTransportType = 'CAR';
  DateTime _selectedDate = DateTime.now();

  @override
  void initState() {
    super.initState();
    _pickupController = TextEditingController(text: widget.pickupLocation);
    _dropoffController = TextEditingController(text: widget.dropoffLocation);
    _searchRides();
  }

  void _searchRides() {
    final filter = RideSearchFilter(
      pickupLocation: _pickupController.text,
      dropoffLocation: _dropoffController.text,
      transportType: _selectedTransportType,
      date: _selectedDate,
    );

    context.read<RidesBloc>().add(SearchRidesEvent(filter: filter));
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Find Rides'),
      ),
      body: Column(
        children: [
          // Search filters
          _buildSearchFilters(),
          Expanded(
            child: BlocBuilder<RidesBloc, RidesState>(
              builder: (context, state) {
                if (state is RidesLoading) {
                  return const Center(child: CircularProgressIndicator());
                }

                if (state is RidesError) {
                  return Center(
                    child: ErrorWidget(
                      message: state.message,
                      onRetry: _searchRides,
                    ),
                  );
                }

                if (state is RidesLoaded) {
                  if (state.rides.isEmpty) {
                    return const Center(
                      child: Text('No rides available for your search'),
                    );
                  }

                  return ListView.builder(
                    itemCount: state.rides.length,
                    itemBuilder: (context, index) {
                      return RideCard(
                        ride: state.rides[index],
                        onTap: () => _selectRide(state.rides[index]),
                      );
                    },
                  );
                }

                return const SizedBox.shrink();
              },
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildSearchFilters() {
    return Card(
      margin: const EdgeInsets.all(12),
      elevation: 2,
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          children: [
            // From/To locations
            TextField(
              controller: _pickupController,
              onChanged: (_) => _searchRides(),
              decoration: InputDecoration(
                hintText: 'From',
                prefixIcon: const Icon(Icons.location_on),
                border: OutlineInputBorder(
                  borderRadius: BorderRadius.circular(8),
                ),
              ),
            ),
            const SizedBox(height: 8),
            TextField(
              controller: _dropoffController,
              onChanged: (_) => _searchRides(),
              decoration: InputDecoration(
                hintText: 'To',
                prefixIcon: const Icon(Icons.location_on_outlined),
                border: OutlineInputBorder(
                  borderRadius: BorderRadius.circular(8),
                ),
              ),
            ),
            const SizedBox(height: 12),

            // Transport type
            SegmentedButton<String>(
              segments: const [
                ButtonSegment(label: Text('Car'), value: 'CAR'),
                ButtonSegment(label: Text('Motorcycle'), value: 'MOTORCYCLE'),
                ButtonSegment(label: Text('Bus'), value: 'BUS'),
              ],
              selected: {_selectedTransportType},
              onSelectionChanged: (newSelection) {
                setState(() => _selectedTransportType = newSelection.first);
                _searchRides();
              },
            ),

            const SizedBox(height: 12),

            // Date picker
            TextButton.icon(
              onPressed: _selectDate,
              icon: const Icon(Icons.calendar_today),
              label: Text(
                DateFormat('MMM d, yyyy').format(_selectedDate),
              ),
            ),
          ],
        ),
      ),
    );
  }

  void _selectDate() async {
    final picked = await showDatePicker(
      context: context,
      initialDate: _selectedDate,
      firstDate: DateTime.now(),
      lastDate: DateTime.now().add(const Duration(days: 30)),
    );

    if (picked != null && picked != _selectedDate) {
      setState(() => _selectedDate = picked);
      _searchRides();
    }
  }

  void _selectRide(Ride ride) {
    Navigator.pushNamed(
      context,
      '/ride-details',
      arguments: ride,
    );
  }

  @override
  void dispose() {
    _pickupController.dispose();
    _dropoffController.dispose();
    super.dispose();
  }
}
```

### 3. Trip Tracking Page (Realtime)

```dart
class TripTrackingPage extends StatefulWidget {
  final Trip trip;

  const TripTrackingPage({required this.trip});

  @override
  State<TripTrackingPage> createState() => _TripTrackingPageState();
}

class _TripTrackingPageState extends State<TripTrackingPage> {
  late final RealtimeService _realtimeService;
  late final LocationService _locationService;
  final MapController _mapController = MapController();
  
  LatLng? _driverLocation;
  Trip? _currentTrip;

  @override
  void initState() {
    super.initState();
    _realtimeService = RealtimeService();
    _locationService = LocationService();
    _currentTrip = widget.trip;
    _subscribeToTripUpdates();
  }

  void _subscribeToTripUpdates() {
    // Subscribe to realtime trip updates
    _realtimeService.subscribeToDriveLocationUpdates(
      tripId: _currentTrip!.id,
      onLocationUpdated: (lat, lng) {
        setState(() {
          _driverLocation = LatLng(lat, lng);
        });
        _updateMapView();
      },
      onTripStatusChanged: (status) {
        setState(() {
          _currentTrip = _currentTrip!.copyWith(status: status);
        });
        _handleStatusChange(status);
      },
    );
  }

  void _updateMapView() {
    if (_driverLocation != null) {
      _mapController.animateCamera(
        CameraUpdate.newLatLng(_driverLocation!),
      );
    }
  }

  void _handleStatusChange(String status) {
    switch (status) {
      case 'STARTED':
        _showNotification('Trip started!', 'Driver is on the way');
        break;
      case 'COMPLETED':
        _showTripCompletionDialog();
        break;
      case 'CANCELLED':
        _showCancellationDialog();
        break;
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Trip in Progress'),
        leading: IconButton(
          icon: const Icon(Icons.arrow_back),
          onPressed: _onBackPressed,
        ),
      ),
      body: Stack(
        children: [
          // Map view
          FlutterMap(
            mapController: _mapController,
            options: MapOptions(
              center: LatLng(
                _currentTrip!.pickupLat,
                _currentTrip!.pickupLng,
              ),
              zoom: 15,
            ),
            layers: [
              TileLayerOptions(
                urlTemplate:
                    'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',
                subdomains: const ['a', 'b', 'c'],
              ),
              MarkerLayerOptions(
                markers: [
                  // Pickup marker
                  Marker(
                    width: 40,
                    height: 40,
                    point: LatLng(
                      _currentTrip!.pickupLat,
                      _currentTrip!.pickupLng,
                    ),
                    builder: (_) => const Icon(
                      Icons.location_on,
                      color: Colors.red,
                      size: 40,
                    ),
                  ),
                  // Dropoff marker
                  Marker(
                    width: 40,
                    height: 40,
                    point: LatLng(
                      _currentTrip!.dropoffLat,
                      _currentTrip!.dropoffLng,
                    ),
                    builder: (_) => const Icon(
                      Icons.location_on,
                      color: Colors.green,
                      size: 40,
                    ),
                  ),
                  // Driver location
                  if (_driverLocation != null)
                    Marker(
                      width: 40,
                      height: 40,
                      point: _driverLocation!,
                      builder: (_) => Container(
                        decoration: BoxDecoration(
                          shape: BoxShape.circle,
                          color: Colors.blue,
                          border: Border.all(color: Colors.white, width: 2),
                        ),
                        child: const Icon(
                          Icons.directions_car,
                          color: Colors.white,
                        ),
                      ),
                    ),
                ],
              ),
            ],
          ),

          // Bottom panel with trip details
          Positioned(
            bottom: 0,
            left: 0,
            right: 0,
            child: _buildTripDetailsPanel(),
          ),
        ],
      ),
    );
  }

  Widget _buildTripDetailsPanel() {
    return DraggableScrollableSheet(
      initialChildSize: 0.3,
      minChildSize: 0.2,
      maxChildSize: 0.7,
      builder: (context, scrollController) => Container(
        decoration: BoxDecoration(
          color: Colors.white,
          borderRadius: const BorderRadius.only(
            topLeft: Radius.circular(16),
            topRight: Radius.circular(16),
          ),
          boxShadow: [
            BoxShadow(
              color: Colors.black.withOpacity(0.1),
              blurRadius: 8,
              offset: const Offset(0, -2),
            ),
          ],
        ),
        child: ListView(
          controller: scrollController,
          padding: const EdgeInsets.all(16),
          children: [
            Center(
              child: Container(
                width: 40,
                height: 4,
                decoration: BoxDecoration(
                  color: Colors.grey[300],
                  borderRadius: BorderRadius.circular(2),
                ),
              ),
            ),
            const SizedBox(height: 16),

            // Driver info
            Row(
              children: [
                CircleAvatar(
                  backgroundImage: NetworkImage(
                    _currentTrip!.driver?.profilePhoto ?? '',
                  ),
                  radius: 32,
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        _currentTrip!.driver?.name ?? 'Driver',
                        style: Theme.of(context).textTheme.titleMedium,
                      ),
                      Row(
                        children: [
                          const Icon(Icons.star, color: Colors.amber, size: 16),
                          const SizedBox(width: 4),
                          Text(
                            '${_currentTrip!.driver?.rating ?? 5.0}',
                            style: Theme.of(context).textTheme.bodySmall,
                          ),
                        ],
                      ),
                    ],
                  ),
                ),
                Column(
                  children: [
                    IconButton(
                      icon: const Icon(Icons.call),
                      onPressed: () => _callDriver(_currentTrip!.driver?.phone),
                    ),
                    IconButton(
                      icon: const Icon(Icons.message),
                      onPressed: () => _messageDriver(_currentTrip!.driver?.id),
                    ),
                  ],
                ),
              ],
            ),

            const SizedBox(height: 16),
            Divider(),
            const SizedBox(height: 16),

            // Trip progress
            Text(
              'Trip Progress',
              style: Theme.of(context).textTheme.titleSmall,
            ),
            const SizedBox(height: 8),
            LinearProgressIndicator(
              value: _calculateProgress(),
              minHeight: 8,
              borderRadius: BorderRadius.circular(4),
            ),
            const SizedBox(height: 12),

            // ETA and fare
            Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      'ETA',
                      style: Theme.of(context).textTheme.bodySmall,
                    ),
                    Text(
                      '${_currentTrip!.etaMinutes ?? '~'} min',
                      style: Theme.of(context).textTheme.titleMedium,
                    ),
                  ],
                ),
                Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      'Current Fare',
                      style: Theme.of(context).textTheme.bodySmall,
                    ),
                    Text(
                      '${_currentTrip!.fare ?? 0} RWF',
                      style: Theme.of(context).textTheme.titleMedium,
                    ),
                  ],
                ),
              ],
            ),

            const SizedBox(height: 16),

            // Cancel button
            OutlinedButton.icon(
              onPressed: _showCancelConfirmation,
              icon: const Icon(Icons.close),
              label: const Text('Cancel Trip'),
              style: OutlinedButton.styleFrom(
                foregroundColor: Colors.red,
              ),
            ),
          ],
        ),
      ),
    );
  }

  double _calculateProgress() {
    // Calculate progress based on trip status
    switch (_currentTrip?.status) {
      case 'PENDING':
        return 0.0;
      case 'ACCEPTED':
        return 0.25;
      case 'STARTED':
        return 0.5;
      case 'COMPLETED':
        return 1.0;
      default:
        return 0.0;
    }
  }

  void _callDriver(String? phoneNumber) {
    if (phoneNumber != null) {
      // Implement phone call
    }
  }

  void _messageDriver(int? driverId) {
    if (driverId != null) {
      // Navigate to chat/message screen
    }
  }

  void _showCancelConfirmation() {
    showDialog(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Cancel Trip?'),
        content: const Text('Are you sure you want to cancel this trip?'),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context),
            child: const Text('Keep Trip'),
          ),
          TextButton(
            onPressed: _confirmCancel,
            child: const Text('Cancel Trip'),
          ),
        ],
      ),
    );
  }

  void _confirmCancel() {
    Navigator.pop(context); // Close dialog
    context.read<TripsBloc>().add(CancelTripEvent(_currentTrip!.id));
  }

  void _showTripCompletionDialog() {
    showDialog(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Trip Completed!'),
        content: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Text('Total Fare: ${_currentTrip?.fare} RWF'),
            const SizedBox(height: 16),
            Text(
              'Would you like to rate your driver?',
              style: Theme.of(context).textTheme.bodyMedium,
            ),
          ],
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context),
            child: const Text('Later'),
          ),
          ElevatedButton(
            onPressed: () {
              Navigator.pop(context);
              _navigateToRating();
            },
            child: const Text('Rate Now'),
          ),
        ],
      ),
    );
  }

  void _navigateToRating() {
    Navigator.pushNamed(
      context,
      '/rate-trip',
      arguments: _currentTrip!.id,
    );
  }

  void _showNotification(String title, String message) {
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        content: ListTile(
          title: Text(title),
          subtitle: Text(message),
        ),
        behavior: SnackBarBehavior.floating,
        duration: const Duration(seconds: 3),
      ),
    );
  }

  void _showCancellationDialog() {
    showDialog(
      context: context,
      barrierDismissible: false,
      builder: (context) => AlertDialog(
        title: const Text('Trip Cancelled'),
        content: const Text('Your trip has been cancelled.'),
        actions: [
          ElevatedButton(
            onPressed: () {
              Navigator.pop(context);
              Navigator.pop(context);
            },
            child: const Text('Back Home'),
          ),
        ],
      ),
    );
  }

  void _onBackPressed() {
    showDialog(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Active Trip'),
        content:
            const Text('You have an active trip. Are you sure you want to leave?'),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context),
            child: const Text('Stay'),
          ),
          TextButton(
            onPressed: () {
              Navigator.pop(context);
              Navigator.pop(context);
            },
            child: const Text('Leave'),
          ),
        ],
      ),
    );
  }

  @override
  void dispose() {
    _realtimeService.unsubscribeDriveLocationUpdates(_currentTrip!.id);
    super.dispose();
  }
}
```

### 4. Driver Trip Request Page

```dart
class DriverTripRequestPage extends StatefulWidget {
  final TripRequest request;

  const DriverTripRequestPage({required this.request});

  @override
  State<DriverTripRequestPage> createState() => _DriverTripRequestPageState();
}

class _DriverTripRequestPageState extends State<DriverTripRequestPage> {
  @override
  void initState() {
    super.initState();
    // Show request with auto-dismiss after 30 seconds if not responded
    Future.delayed(const Duration(seconds: 30), () {
      if (mounted && !_hasResponded) {
        _reject();
      }
    });
  }

  bool _hasResponded = false;

  @override
  Widget build(BuildContext context) {
    return Dialog(
      child: Container(
        padding: const EdgeInsets.all(24),
        decoration: BoxDecoration(
          color: Colors.white,
          borderRadius: BorderRadius.circular(16),
        ),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Text(
              '🔔 NEW TRIP REQUEST',
              style: Theme.of(context).textTheme.titleLarge?.copyWith(
                    color: Colors.blue,
              ),
            ),
            const SizedBox(height: 24),

            // Passenger info
            CircleAvatar(
              backgroundImage: NetworkImage(
                widget.request.passenger.profilePhoto,
              ),
              radius: 40,
            ),
            const SizedBox(height: 12),
            Text(
              widget.request.passenger.name,
              style: Theme.of(context).textTheme.titleMedium,
            ),
            Row(
              mainAxisAlignment: MainAxisAlignment.center,
              children: [
                const Icon(Icons.star, color: Colors.amber, size: 16),
                const SizedBox(width: 4),
                Text('${widget.request.passenger.rating}'),
                const SizedBox(width: 4),
                Text(
                  '(${widget.request.passenger.totalTrips} trips)',
                  style: Theme.of(context).textTheme.bodySmall,
                ),
              ],
            ),

            const SizedBox(height: 24),

            // Trip details
            _buildLocationRow(
              context,
              icon: Icons.location_on,
              label: 'Pickup',
              value: widget.request.pickupLocation,
              color: Colors.red,
            ),
            const SizedBox(height: 8),
            _buildLocationRow(
              context,
              icon: Icons.location_on_outlined,
              label: 'Dropoff',
              value: widget.request.dropoffLocation,
              color: Colors.green,
            ),

            const SizedBox(height: 16),
            Divider(),
            const SizedBox(height: 16),

            // Trip metrics
            Row(
              mainAxisAlignment: MainAxisAlignment.spaceAround,
              children: [
                _buildMetric(
                  context,
                  '${widget.request.distanceFromDriver} km',
                  'Away',
                ),
                _buildMetric(
                  context,
                  '${widget.request.timeToPickup} min',
                  'Drive Time',
                ),
                _buildMetric(
                  context,
                  '${widget.request.estimatedFare} RWF',
                  'Earnings',
                ),
              ],
            ),

            const SizedBox(height: 24),

            // Countdown timer
            TweenAnimationBuilder(
              tween: Tween(begin: 30.0, end: 0.0),
              duration: const Duration(seconds: 30),
              builder: (context, value, child) {
                return Text(
                  'Expires in ${value.toInt()} seconds',
                  style: Theme.of(context).textTheme.bodySmall?.copyWith(
                        color: Colors.red,
                      ),
                );
              },
            ),

            const SizedBox(height: 16),

            // Action buttons
            Row(
              children: [
                Expanded(
                  child: OutlinedButton(
                    onPressed: _reject,
                    child: const Text('Reject'),
                  ),
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: ElevatedButton(
                    onPressed: _accept,
                    child: const Text('Accept'),
                  ),
                ),
              ],
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildLocationRow(
    BuildContext context, {
    required IconData icon,
    required String label,
    required String value,
    required Color color,
  }) {
    return Row(
      children: [
        Icon(icon, color: color, size: 20),
        const SizedBox(width: 12),
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                label,
                style: Theme.of(context).textTheme.bodySmall,
              ),
              Text(
                value,
                style: Theme.of(context).textTheme.bodyMedium,
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
              ),
            ],
          ),
        ),
      ],
    );
  }

  Widget _buildMetric(
    BuildContext context,
    String value,
    String label,
  ) {
    return Column(
      children: [
        Text(
          value,
          style: Theme.of(context).textTheme.titleMedium,
        ),
        Text(
          label,
          style: Theme.of(context).textTheme.bodySmall,
        ),
      ],
    );
  }

  void _accept() {
    _hasResponded = true;
    context.read<DriverBloc>().add(AcceptTripRequestEvent(widget.request.id));
    Navigator.pop(context);
  }

  void _reject() {
    _hasResponded = true;
    context.read<DriverBloc>().add(RejectTripRequestEvent(widget.request.id));
    Navigator.pop(context);
  }
}
```

---

## Widget Library

### Custom Button Component

```dart
class CustomButton extends StatelessWidget {
  final String label;
  final VoidCallback onPressed;
  final bool isLoading;
  final bool isEnabled;
  final ButtonStyle? style;
  final IconData? icon;

  const CustomButton({
    required this.label,
    required this.onPressed,
    this.isLoading = false,
    this.isEnabled = true,
    this.style,
    this.icon,
  });

  @override
  Widget build(BuildContext context) {
    return ElevatedButton.icon(
      onPressed: isEnabled && !isLoading ? onPressed : null,
      icon: isLoading
          ? SizedBox(
              width: 20,
              height: 20,
              child: CircularProgressIndicator(
                strokeWidth: 2,
                valueColor: AlwaysStoppedAnimation(
                  Theme.of(context).primaryColor,
                ),
              ),
            )
          : (icon != null ? Icon(icon) : const SizedBox.shrink()),
      label: Text(isLoading ? 'Processing...' : label),
      style: style ??
          ElevatedButton.styleFrom(
            minimumSize: const Size.fromHeight(48),
            padding: const EdgeInsets.symmetric(vertical: 12),
          ),
    );
  }
}
```

### Trip Card Component

```dart
class RideCard extends StatelessWidget {
  final Ride ride;
  final VoidCallback? onTap;
  final VoidCallback? onBookmark;

  const RideCard({
    required this.ride,
    this.onTap,
    this.onBookmark,
  });

  @override
  Widget build(BuildContext context) {
    return Card(
      margin: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
      elevation: 2,
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(12),
        child: Padding(
          padding: const EdgeInsets.all(16),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              // Header with ride type and price
              Row(
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                children: [
                  Chip(
                    label: Text(ride.transportType),
                    backgroundColor: _getTransportTypeColor(ride.transportType),
                    labelStyle: const TextStyle(color: Colors.white),
                  ),
                  Text(
                    '${ride.pricePerSeat} RWF',
                    style: Theme.of(context).textTheme.titleMedium?.copyWith(
                          fontWeight: FontWeight.bold,
                        ),
                  ),
                ],
              ),
              const SizedBox(height: 12),

              // Route
              Text(
                '${ride.originAddress} → ${ride.destinationAddress}',
                style: Theme.of(context).textTheme.bodyMedium,
                maxLines: 2,
                overflow: TextOverflow.ellipsis,
              ),
              const SizedBox(height: 12),

              // Driver info
              Row(
                children: [
                  CircleAvatar(
                    backgroundImage: NetworkImage(
                      ride.driver.profilePhoto,
                    ),
                    radius: 20,
                  ),
                  const SizedBox(width: 12),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          ride.driver.name,
                          style: Theme.of(context).textTheme.bodyMedium,
                        ),
                        Row(
                          children: [
                            const Icon(Icons.star,
                                color: Colors.amber, size: 14),
                            const SizedBox(width: 4),
                            Text(
                              '${ride.driver.rating}',
                              style: Theme.of(context).textTheme.bodySmall,
                            ),
                          ],
                        ),
                      ],
                    ),
                  ),
                  IconButton(
                    icon: Icon(
                      Icons.bookmark_border,
                      color: Colors.grey[600],
                    ),
                    onPressed: onBookmark,
                  ),
                ],
              ),

              const SizedBox(height: 12),

              // Time and seats
              Row(
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                children: [
                  Text(
                    DateFormat('hh:mm a').format(ride.departureTime),
                    style: Theme.of(context).textTheme.bodySmall,
                  ),
                  Text(
                    '${ride.availableSeats} seats available',
                    style: Theme.of(context).textTheme.bodySmall,
                  ),
                ],
              ),
            ],
          ),
        ),
      ),
    );
  }

  Color _getTransportTypeColor(String type) {
    switch (type) {
      case 'CAR':
        return Colors.blue;
      case 'MOTORCYCLE':
        return Colors.orange;
      case 'BUS':
        return Colors.green;
      default:
        return Colors.grey;
    }
  }
}
```

---

## State Management

### Rides BLoC

```dart
class RidesBloc extends Bloc<RidesEvent, RidesState> {
  final RideRepository rideRepository;

  RidesBloc({required this.rideRepository})
      : super(const RidesInitial()) {
    on<SearchRidesEvent>(_onSearchRides);
    on<GetRideDetailsEvent>(_onGetRideDetails);
    on<FilterRidesEvent>(_onFilterRides);
    on<UpdateCurrentLocationEvent>(_onUpdateCurrentLocation);
  }

  Future<void> _onSearchRides(
    SearchRidesEvent event,
    Emitter<RidesState> emit,
  ) async {
    try {
      emit(const RidesLoading());

      final rides = await rideRepository.searchRides(event.filter);

      emit(RidesLoaded(rides: rides));
    } on Exception catch (e) {
      emit(RidesError(message: e.toString()));
    }
  }

  Future<void> _onGetRideDetails(
    GetRideDetailsEvent event,
    Emitter<RidesState> emit,
  ) async {
    try {
      emit(const RidesLoading());

      final ride = await rideRepository.getRideDetails(event.rideId);

      emit(RideDetailsLoaded(ride: ride));
    } on Exception catch (e) {
      emit(RidesError(message: e.toString()));
    }
  }

  Future<void> _onFilterRides(
    FilterRidesEvent event,
    Emitter<RidesState> emit,
  ) async {
    if (state is RidesLoaded) {
      final currentState = state as RidesLoaded;
      final filtered = currentState.rides
          .where((ride) => event.filter(ride))
          .toList();

      emit(RidesLoaded(rides: filtered));
    }
  }

  Future<void> _onUpdateCurrentLocation(
    UpdateCurrentLocationEvent event,
    Emitter<RidesState> emit,
  ) async {
    // Update current location for filtering nearby rides
  }
}

// Events
abstract class RidesEvent extends Equatable {
  const RidesEvent();
}

class SearchRidesEvent extends RidesEvent {
  final RideSearchFilter filter;

  const SearchRidesEvent({required this.filter});

  @override
  List<Object?> get props => [filter];
}

// States
abstract class RidesState extends Equatable {
  const RidesState();
}

class RidesInitial extends RidesState {
  const RidesInitial();

  @override
  List<Object?> get props => [];
}

class RidesLoading extends RidesState {
  const RidesLoading();

  @override
  List<Object?> get props => [];
}

class RidesLoaded extends RidesState {
  final List<Ride> rides;

  const RidesLoaded({required this.rides});

  @override
  List<Object?> get props => [rides];
}

class RidesError extends RidesState {
  final String message;

  const RidesError({required this.message});

  @override
  List<Object?> get props => [message];
}
```

---

## API Integration

### API Service

```dart
class ApiService {
  final String baseUrl;
  final Dio _dio;

  ApiService({required this.baseUrl}) : _dio = Dio() {
    _setupInterceptors();
  }

  void _setupInterceptors() {
    _dio.interceptors.add(
      InterceptorsWrapper(
        onRequest: (options, handler) async {
          // Add auth token
          final token = await _getAuthToken();
          if (token != null) {
            options.headers['Authorization'] = 'Bearer $token';
          }
          return handler.next(options);
        },
        onError: (error, handler) {
          if (error.response?.statusCode == 401) {
            // Handle unauthorized
          }
          return handler.next(error);
        },
      ),
    );
  }

  Future<List<Ride>> searchRides(RideSearchFilter filter) async {
    try {
      final response = await _dio.get(
        '$baseUrl/passenger/rides/available',
        queryParameters: filter.toJson(),
      );

      final rides = (response.data['data'] as List)
          .map((r) => Ride.fromJson(r))
          .toList();

      return rides;
    } catch (e) {
      rethrow;
    }
  }

  Future<Trip> requestTrip(TripRequest request) async {
    try {
      final response = await _dio.post(
        '$baseUrl/passenger/trips/request',
        data: request.toJson(),
      );

      return Trip.fromJson(response.data['data']);
    } catch (e) {
      rethrow;
    }
  }

  Future<void> updateTripLocation(
    int tripId, {
    required double lat,
    required double lng,
  }) async {
    try {
      await _dio.post(
        '$baseUrl/driver/trips/$tripId/location',
        data: {
          'lat': lat,
          'lng': lng,
        },
      );
    } catch (e) {
      rethrow;
    }
  }

  Future<String?> _getAuthToken() async {
    // Retrieve from secure storage
    final storage = FlutterSecureStorage();
    return await storage.read(key: 'auth_token');
  }
}
```

---

## Realtime Integration

### Realtime Service

```dart
class RealtimeService {
  final RealtimeClient realtimeClient;

  RealtimeService({required this.realtimeClient});

  void subscribeToDriveLocationUpdates({
    required int tripId,
    required Function(double lat, double lng) onLocationUpdated,
    required Function(String status) onTripStatusChanged,
  }) {
    final channel = realtimeClient.channel('trip:$tripId');

    channel
        .onBroadcast(
          event: 'driver.location.updated',
          callback: (payload) {
            final lat = payload['lat'] as double;
            final lng = payload['lng'] as double;
            onLocationUpdated(lat, lng);
          },
        )
        .onBroadcast(
          event: 'trip.started',
          callback: (payload) => onTripStatusChanged('STARTED'),
        )
        .onBroadcast(
          event: 'trip.completed',
          callback: (payload) => onTripStatusChanged('COMPLETED'),
        )
        .onBroadcast(
          event: 'trip.cancelled',
          callback: (payload) => onTripStatusChanged('CANCELLED'),
        )
        .subscribe();
  }

  void subscribeToDriverRequests({
    required int driverId,
    required Function(TripRequest request) onRequestReceived,
  }) {
    final channel = realtimeClient.channel('driver:$driverId');

    channel
        .onBroadcast(
          event: 'trip.request',
          callback: (payload) {
            final request = TripRequest.fromJson(payload);
            onRequestReceived(request);
          },
        )
        .subscribe();
  }

  void unsubscribeDriveLocationUpdates(int tripId) {
    realtimeClient.removeChannel(
      realtimeClient.channel('trip:$tripId'),
    );
  }
}
```

---

**Implementation Version:** 2.0  
**Last Updated:** May 2026  
**Maintained By:** RideConnect Development Team
