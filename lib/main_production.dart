// File: lib/main_production.dart
// Production main entry point with proper initialization
// Last Updated: May 29, 2026

import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'config/api_config.dart';
import 'config/dio_config.dart';
import 'services/trip_service.dart';
import 'services/driver_matching_service.dart';

void main() async {
  // Initialize Dio with production URLs
  DioServiceLocator().initialize(authToken: null);
  
  // Run the app
  runApp(const RideConnectApp());
}

class RideConnectApp extends StatelessWidget {
  const RideConnectApp({Key? key}) : super(key: key);

  @override
  Widget build(BuildContext context) {
    final dio = DioServiceLocator().getMainDio();
    
    return MultiProvider(
      providers: [
        Provider<TripService>(
          create: (_) => TripService(dio: dio),
        ),
        Provider<DriverMatchingService>(
          create: (_) => DriverMatchingService(dio: dio),
        ),
      ],
      child: MaterialApp(
        title: 'RideConnect',
        theme: ThemeData(
          primarySwatch: Colors.green,
          useMaterial3: true,
        ),
        home: const HomePage(),
      ),
    );
  }
}

class HomePage extends StatelessWidget {
  const HomePage({Key? key}) : super(key: key);

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('RideConnect - Production API Integration'),
        centerTitle: true,
      ),
      body: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          // API Configuration Info
          Card(
            child: Padding(
              padding: const EdgeInsets.all(16),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  const Text(
                    'Production Configuration',
                    style: TextStyle(fontSize: 18, fontWeight: FontWeight.bold),
                  ),
                  const SizedBox(height: 12),
                  Text(
                    'Backend API: ${ApiConfig.baseUrl}',
                    style: const TextStyle(fontSize: 12),
                  ),
                  const SizedBox(height: 8),
                  Text(
                    'ML Service: ${ApiConfig.mlServiceUrl}',
                    style: const TextStyle(fontSize: 12),
                  ),
                  const SizedBox(height: 8),
                  Text(
                    'Mobile API: ${ApiConfig.mobileApiPath}',
                    style: const TextStyle(fontSize: 12),
                  ),
                ],
              ),
            ),
          ),
          const SizedBox(height: 16),
          
          // Driver Matching Section
          Card(
            child: Padding(
              padding: const EdgeInsets.all(16),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  const Text(
                    'Available Drivers',
                    style: TextStyle(fontSize: 18, fontWeight: FontWeight.bold),
                  ),
                  const SizedBox(height: 12),
                  ElevatedButton(
                    onPressed: () => _showDriversTest(context),
                    child: const Text('Test Driver Matching'),
                  ),
                ],
              ),
            ),
          ),
          const SizedBox(height: 16),
          
          // Trip Operations Section
          Card(
            child: Padding(
              padding: const EdgeInsets.all(16),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  const Text(
                    'Trip Operations',
                    style: TextStyle(fontSize: 18, fontWeight: FontWeight.bold),
                  ),
                  const SizedBox(height: 12),
                  ElevatedButton(
                    onPressed: () => _testInvalidTripId(context),
                    child: const Text('Test Invalid Trip ID (0)'),
                  ),
                  const SizedBox(height: 8),
                  ElevatedButton(
                    onPressed: () => _testNegativeTripId(context),
                    child: const Text('Test Negative Trip ID (-1)'),
                  ),
                  const SizedBox(height: 8),
                  ElevatedButton(
                    onPressed: () => _testRequestTrip(context),
                    child: const Text('Test Request Trip'),
                  ),
                ],
              ),
            ),
          ),
        ],
      ),
    );
  }

  void _showDriversTest(BuildContext context) {
    final driverService = Provider.of<DriverMatchingService>(
      context,
      listen: false,
    );

    showDialog(
      context: context,
      builder: (context) => Dialog(
        child: Padding(
          padding: const EdgeInsets.all(16),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              const Text(
                'Testing Driver Matching',
                style: TextStyle(fontSize: 16, fontWeight: FontWeight.bold),
              ),
              const SizedBox(height: 16),
              FutureBuilder(
                future: driverService.getAvailableDrivers(
                  latitude: -1.2866,
                  longitude: 36.7753,
                  transportType: 'motor_vehicle',
                ),
                builder: (context, snapshot) {
                  if (snapshot.connectionState == ConnectionState.waiting) {
                    return const CircularProgressIndicator();
                  }

                  if (snapshot.hasError) {
                    return Text('Error: ${snapshot.error}');
                  }

                  final response = snapshot.data;
                  if (response == null) {
                    return const Text('No response');
                  }

                  if (response.isEmpty) {
                    return Column(
                      children: [
                        const Icon(
                          Icons.person_outline,
                          size: 48,
                          color: Colors.grey,
                        ),
                        const SizedBox(height: 8),
                        Text(
                          response.getEmptyStateMessage(),
                          textAlign: TextAlign.center,
                        ),
                      ],
                    );
                  }

                  return Column(
                    children: [
                      Text('Found ${response.drivers.length} drivers'),
                      const SizedBox(height: 8),
                      ...response.drivers.map((driver) => Text(
                        '${driver.name} - ${driver.distance.toStringAsFixed(2)} km',
                      )),
                    ],
                  );
                },
              ),
              const SizedBox(height: 16),
              ElevatedButton(
                onPressed: () => Navigator.pop(context),
                child: const Text('Close'),
              ),
            ],
          ),
        ),
      ),
    );
  }

  void _testInvalidTripId(BuildContext context) {
    final tripService = Provider.of<TripService>(context, listen: false);

    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        content: Text(
          'Testing trip ID = 0... (will throw: Invalid trip ID)',
        ),
        duration: const Duration(seconds: 2),
      ),
    );

    try {
      tripService.acceptTrip(0); // This will throw immediately
    } catch (e) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text('✓ Correctly caught error: $e'),
          backgroundColor: Colors.green,
        ),
      );
    }
  }

  void _testNegativeTripId(BuildContext context) {
    final tripService = Provider.of<TripService>(context, listen: false);

    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        content: Text(
          'Testing trip ID = -1... (will throw: Invalid trip ID)',
        ),
        duration: const Duration(seconds: 2),
      ),
    );

    try {
      tripService.acceptTrip(-1); // This will throw immediately
    } catch (e) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text('✓ Correctly caught error: $e'),
          backgroundColor: Colors.green,
        ),
      );
    }
  }

  void _testRequestTrip(BuildContext context) {
    final tripService = Provider.of<TripService>(context, listen: false);

    showDialog(
      context: context,
      builder: (context) => Dialog(
        child: Padding(
          padding: const EdgeInsets.all(16),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              const Text(
                'Testing Request Trip',
                style: TextStyle(fontSize: 16, fontWeight: FontWeight.bold),
              ),
              const SizedBox(height: 16),
              FutureBuilder(
                future: tripService.requestTrip(
                  pickupLat: -1.2866,
                  pickupLng: 36.7753,
                  pickupLocation: 'Downtown Terminal',
                  dropoffLat: -1.3195,
                  dropoffLng: 36.9273,
                  dropoffLocation: 'Airport',
                  transportType: 'motor_vehicle',
                ),
                builder: (context, snapshot) {
                  if (snapshot.connectionState == ConnectionState.waiting) {
                    return const CircularProgressIndicator();
                  }

                  if (snapshot.hasError) {
                    return Text('Error: ${snapshot.error}');
                  }

                  final response = snapshot.data;
                  if (response == null) {
                    return const Text('No response');
                  }

                  return Column(
                    children: [
                      Text('Trip ID: ${response.tripId}'),
                      const SizedBox(height: 8),
                      Text('Status: ${response.tripState}'),
                      const SizedBox(height: 8),
                      Text('Fare: ${response.fare}'),
                    ],
                  );
                },
              ),
              const SizedBox(height: 16),
              ElevatedButton(
                onPressed: () => Navigator.pop(context),
                child: const Text('Close'),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
