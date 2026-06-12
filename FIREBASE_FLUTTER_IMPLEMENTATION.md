# Firebase Firestore Integration Implementation Guide

**Target:** Flutter RideConnect App  
**Framework:** Firebase SDK for Dart  
**Status:** Production-Ready

---

## 📦 Flutter Dependencies

Add to `pubspec.yaml`:

```yaml
dependencies:
  flutter:
    sdk: flutter
  firebase_core: ^2.24.0
  firebase_firestore: ^4.14.0
  firebase_messaging: ^14.7.0
  firebase_auth: ^4.13.0
  cloud_firestore: ^4.14.0
  geoflutterfire_plus: ^1.4.0  # For geoqueries

dev_dependencies:
  build_runner: ^2.4.0
  json_serializable: ^6.7.0
```

---

## 🔧 Firestore Service Implementation

**File:** `lib/services/firestore_service.dart`

```dart
import 'package:cloud_firestore/cloud_firestore.dart';
import 'package:firebase_auth/firebase_auth.dart';
import 'package:firebase_messaging/firebase_messaging.dart';
import 'package:geoflutterfire_plus/geoflutterfire_plus.dart';
import '../models/models.dart';

class FirestoreService {
  static final FirestoreService _instance = FirestoreService._internal();
  
  factory FirestoreService() {
    return _instance;
  }
  
  FirestoreService._internal();
  
  final FirebaseFirestore _db = FirebaseFirestore.instance;
  final FirebaseAuth _auth = FirebaseAuth.instance;
  final FirebaseMessaging _messaging = FirebaseMessaging.instance;

  // ==================== USER MANAGEMENT ====================
  
  /// Initialize user profile in Firestore after authentication
  Future<void> initializeUserProfile({
    required String userId,
    required String email,
    required String name,
    required String role, // 'passenger' or 'driver'
  }) async {
    try {
      final now = DateTime.now();
      
      // Create user document
      await _db.collection('users').doc(userId).set({
        'email': email,
        'name': name,
        'role': role,
        'is_online': true,
        'last_seen': now,
        'rating': 0.0,
        'completed_trips': 0,
        'cancelled_trips': 0,
        'metadata': {
          'created_at': now,
          'updated_at': now,
          'firebase_token': null,
          'app_version': '1.0.0',
        },
      }, SetOptions(merge: true));

      // Create driver or passenger profile
      if (role == 'driver') {
        await _db.collection('drivers').doc(userId).set({
          'user_id': userId,
          'status': 'offline',
          'current_location': {
            'latitude': 0,
            'longitude': 0,
            'accuracy': 0,
            'updated_at': now,
          },
          'current_trip_id': null,
          'vehicle': {
            'type': 'economy',
            'license_plate': '',
            'color': '',
            'model': '',
          },
          'service_types': ['private_car'],
          'response_time': 0,
          'acceptance_rate': 0,
          'cancellation_rate': 0,
          'average_rating': 0.0,
          'total_earnings': 0,
          'available_capacity': 1,
          'metadata': {
            'last_location_update': now,
            'shift_start': null,
            'shift_end': null,
            'offline_reason': null,
          },
        });
      } else if (role == 'passenger') {
        await _db.collection('passengers').doc(userId).set({
          'user_id': userId,
          'home_address': {
            'latitude': 0,
            'longitude': 0,
            'address': '',
            'place_id': '',
          },
          'work_address': {
            'latitude': 0,
            'longitude': 0,
            'address': '',
            'place_id': '',
          },
          'preferred_drivers': [],
          'blocked_drivers': [],
          'emergency_contacts': [],
          'preferences': {
            'share_location': true,
            'allow_contact_after_ride': false,
            'preferred_vehicle_type': 'economy',
            'payment_method': 'upi',
            'auto_request_upi': false,
          },
          'saved_places': [],
          'stats': {
            'total_trips': 0,
            'average_rating': 0.0,
            'member_since': now,
          },
        });
      }

      // Register FCM token
      await registerFCMToken(userId);
      
      print('[Firestore] User profile initialized: $userId');
    } catch (e) {
      print('[Firestore Error] Failed to initialize user profile: $e');
      rethrow;
    }
  }

  /// Update user online status
  Future<void> updateUserStatus(String userId, bool isOnline) async {
    try {
      await _db.collection('users').doc(userId).update({
        'is_online': isOnline,
        'last_seen': DateTime.now(),
      });
    } catch (e) {
      print('[Firestore Error] Failed to update user status: $e');
    }
  }

  // ==================== DRIVER FUNCTIONS ====================

  /// Update driver location (real-time, called every 10 seconds during trip)
  Future<void> updateDriverLocation({
    required String driverId,
    required double latitude,
    required double longitude,
    required double accuracy,
  }) async {
    try {
      await _db.collection('drivers').doc(driverId).update({
        'current_location': {
          'latitude': latitude,
          'longitude': longitude,
          'accuracy': accuracy,
          'updated_at': FieldValue.serverTimestamp(),
        },
      });
    } catch (e) {
      print('[Firestore Error] Failed to update driver location: $e');
    }
  }

  /// Update driver status (online, offline, on_trip, unavailable)
  Future<void> updateDriverStatus(String driverId, String status) async {
    try {
      final update = {
        'status': status,
        'metadata.last_location_update': FieldValue.serverTimestamp(),
      };

      if (status == 'offline') {
        update['metadata.shift_end'] = FieldValue.serverTimestamp();
      } else if (status == 'online') {
        update['metadata.shift_start'] = FieldValue.serverTimestamp();
      }

      await _db.collection('drivers').doc(driverId).update(update);
    } catch (e) {
      print('[Firestore Error] Failed to update driver status: $e');
    }
  }

  /// Get stream of nearby drivers for trip assignment
  Stream<List<DocumentSnapshot>> getNearbyDrivers({
    required double latitude,
    required double longitude,
    required double radiusInKm,
  }) {
    try {
      final geoRef = GeoCollectionReference(_db.collection('drivers'));
      
      return geoRef
          .withConverter(
            fromFirestore: (snapshot, _) => snapshot.data()!,
            toFirestore: (data, _) => data,
          )
          .findGeohash(
            center: GeoPoint(latitude, longitude),
            radiusInKilometers: radiusInKm,
          )
          .snapshots()
          .asyncMap((snapshot) async {
            final results = <DocumentSnapshot>[];
            for (var doc in snapshot.docs) {
              final distance = GeoFirestoreUtil.distance(
                lat1: latitude,
                lon1: longitude,
                lat2: doc['current_location']['latitude'],
                lon2: doc['current_location']['longitude'],
              );

              if (distance <= radiusInKm) {
                results.add(doc);
              }
            }
            return results;
          });
    } catch (e) {
      print('[Firestore Error] Failed to get nearby drivers: $e');
      return Stream.value([]);
    }
  }

  /// Stream of driver's current trips
  Stream<List<ActiveTrip>> getDriverTrips(String driverId) {
    try {
      return _db
          .collection('active_trips')
          .where('driver_id', isEqualTo: driverId)
          .where('status', whereIn: ['accepted', 'driver_arriving', 'arrived', 'in_progress'])
          .orderBy('timeline.requested_at', descending: true)
          .snapshots()
          .map((snapshot) => snapshot.docs
              .map((doc) => ActiveTrip.fromFirestore(doc))
              .toList());
    } catch (e) {
      print('[Firestore Error] Failed to get driver trips: $e');
      return Stream.value([]);
    }
  }

  // ==================== PASSENGER FUNCTIONS ====================

  /// Create trip request in Firestore
  Future<String> createTripRequest({
    required String passengerId,
    required double pickupLat,
    required double pickupLng,
    required String pickupAddress,
    required double dropoffLat,
    required double dropoffLng,
    required String dropoffAddress,
    required String rideType,
    required double estimatedFare,
  }) async {
    try {
      final now = DateTime.now();
      
      final docRef = await _db.collection('active_trips').add({
        'passenger_id': passengerId,
        'driver_id': null,
        'status': 'requested',
        'ride_type': rideType,
        'pickup': {
          'latitude': pickupLat,
          'longitude': pickupLng,
          'address': pickupAddress,
          'timestamp': now,
        },
        'dropoff': {
          'latitude': dropoffLat,
          'longitude': dropoffLng,
          'address': dropoffAddress,
          'timestamp': now,
        },
        'distance_km': 0,
        'estimated_duration_seconds': 0,
        'estimated_fare': estimatedFare,
        'currency': 'RWF',
        'driver_location': {
          'latitude': 0,
          'longitude': 0,
          'timestamp': now,
          'distance_to_pickup': 0,
        },
        'route': {
          'polyline': '',
          'waypoints': [],
          'updated_at': now,
        },
        'passenger_location_history': [{
          'latitude': pickupLat,
          'longitude': pickupLng,
          'timestamp': now,
        }],
        'driver_location_history': [],
        'events': [{
          'type': 'requested',
          'timestamp': now,
          'metadata': {},
        }],
        'timeline': {
          'requested_at': now,
          'accepted_at': null,
          'driver_arrived_at': null,
          'started_at': null,
          'completed_at': null,
          'cancelled_at': null,
        },
        'payment': {
          'method': 'upi',
          'status': 'pending',
          'amount': 0,
          'transaction_id': '',
        },
        'rating': {
          'passenger_rating': null,
          'driver_rating': null,
          'passenger_review': null,
          'driver_review': null,
        },
        'cancellation': {
          'reason': null,
          'cancelled_by': null,
          'refund_amount': null,
        },
        'metadata': {
          'promotion_code': null,
          'discount_amount': 0,
          'notes': '',
        },
      });

      return docRef.id;
    } catch (e) {
      print('[Firestore Error] Failed to create trip request: $e');
      rethrow;
    }
  }

  /// Get stream of passenger's trips
  Stream<List<ActiveTrip>> getPassengerTrips(String passengerId) {
    try {
      return _db
          .collection('active_trips')
          .where('passenger_id', isEqualTo: passengerId)
          .orderBy('timeline.requested_at', descending: true)
          .snapshots()
          .map((snapshot) => snapshot.docs
              .map((doc) => ActiveTrip.fromFirestore(doc))
              .toList());
    } catch (e) {
      print('[Firestore Error] Failed to get passenger trips: $e');
      return Stream.value([]);
    }
  }

  // ==================== TRIP MANAGEMENT ====================

  /// Get real-time trip updates
  Stream<ActiveTrip?> getTripStream(String tripId) {
    try {
      return _db
          .collection('active_trips')
          .doc(tripId)
          .snapshots()
          .map((snapshot) => snapshot.exists ? ActiveTrip.fromFirestore(snapshot) : null);
    } catch (e) {
      print('[Firestore Error] Failed to get trip stream: $e');
      return Stream.value(null);
    }
  }

  /// Update trip status
  Future<void> updateTripStatus(String tripId, String status) async {
    try {
      final update = {'status': status};
      final now = DateTime.now();

      // Update timeline
      switch (status) {
        case 'accepted':
          update['timeline.accepted_at'] = now;
          break;
        case 'driver_arriving':
          update['timeline.driver_arrived_at'] = now;
          break;
        case 'arrived':
          update['timeline.driver_arrived_at'] = now;
          break;
        case 'in_progress':
          update['timeline.started_at'] = now;
          break;
        case 'completed':
          update['timeline.completed_at'] = now;
          break;
        case 'cancelled':
          update['timeline.cancelled_at'] = now;
          break;
      }

      await _db.collection('active_trips').doc(tripId).update(update);
    } catch (e) {
      print('[Firestore Error] Failed to update trip status: $e');
    }
  }

  /// Track driver location during trip
  Future<void> updateTripDriverLocation({
    required String tripId,
    required double latitude,
    required double longitude,
  }) async {
    try {
      await _db.collection('active_trips').doc(tripId).update({
        'driver_location': {
          'latitude': latitude,
          'longitude': longitude,
          'timestamp': FieldValue.serverTimestamp(),
          'distance_to_pickup': 0, // Calculate on backend
        },
      });
    } catch (e) {
      print('[Firestore Error] Failed to update trip driver location: $e');
    }
  }

  // ==================== CHAT ====================

  /// Send chat message
  Future<void> sendChatMessage({
    required String tripId,
    required String message,
    required String senderType, // 'passenger' or 'driver'
    required String recipientId,
  }) async {
    try {
      final userId = _auth.currentUser?.uid;
      if (userId == null) throw Exception('User not authenticated');

      await _db
          .collection('active_trips')
          .doc(tripId)
          .collection('chat_messages')
          .add({
        'sender_id': userId,
        'sender_type': senderType,
        'recipient_id': recipientId,
        'message': message,
        'message_type': 'text',
        'timestamp': FieldValue.serverTimestamp(),
        'read': false,
        'read_at': null,
        'delivery_status': 'sent',
        'attachments': [],
      });
    } catch (e) {
      print('[Firestore Error] Failed to send chat message: $e');
      rethrow;
    }
  }

  /// Get chat messages stream
  Stream<List<ChatMessage>> getChatMessages(String tripId) {
    try {
      return _db
          .collection('active_trips')
          .doc(tripId)
          .collection('chat_messages')
          .orderBy('timestamp')
          .snapshots()
          .map((snapshot) => snapshot.docs
              .map((doc) => ChatMessage.fromFirestore(doc))
              .toList());
    } catch (e) {
      print('[Firestore Error] Failed to get chat messages: $e');
      return Stream.value([]);
    }
  }

  // ==================== NOTIFICATIONS ====================

  /// Register FCM token
  Future<void> registerFCMToken(String userId) async {
    try {
      final token = await _messaging.getToken();
      if (token == null) throw Exception('Failed to get FCM token');

      await _db.collection('fcm_tokens').add({
        'user_id': userId,
        'token': token,
        'platform': 'android', // or 'ios' based on platform
        'device_model': '', // Get from device info
        'app_version': '1.0.0',
        'os_version': '', // Get from device info
        'is_active': true,
        'created_at': DateTime.now(),
        'last_used_at': DateTime.now(),
        'metadata': {
          'notification_enabled': true,
          'sound_enabled': true,
          'vibration_enabled': true,
        },
      });

      print('[FCM] Token registered: $token');
    } catch (e) {
      print('[FCM Error] Failed to register token: $e');
    }
  }

  /// Get user notifications stream
  Stream<List<NotificationModel>> getNotifications(String userId) {
    try {
      return _db
          .collection('notifications')
          .where('user_id', isEqualTo: userId)
          .where('delivery_status', isNotEqualTo: 'read')
          .orderBy('delivery_status')
          .orderBy('created_at', descending: true)
          .snapshots()
          .map((snapshot) => snapshot.docs
              .map((doc) => NotificationModel.fromFirestore(doc))
              .toList());
    } catch (e) {
      print('[Firestore Error] Failed to get notifications: $e');
      return Stream.value([]);
    }
  }

  /// Mark notification as read
  Future<void> markNotificationAsRead(String notificationId) async {
    try {
      await _db.collection('notifications').doc(notificationId).update({
        'delivery_status': 'read',
        'read_at': FieldValue.serverTimestamp(),
      });
    } catch (e) {
      print('[Firestore Error] Failed to mark notification as read: $e');
    }
  }

  // ==================== RATINGS ====================

  /// Submit driver rating
  Future<void> submitDriverRating({
    required String tripId,
    required String driverId,
    required String passengerId,
    required double rating,
    required String? review,
    required Map<String, double> categories,
  }) async {
    try {
      await _db.collection('driver_ratings').add({
        'driver_id': driverId,
        'trip_id': tripId,
        'passenger_id': passengerId,
        'rating': rating,
        'review': review ?? '',
        'categories': categories,
        'created_at': FieldValue.serverTimestamp(),
        'anonymous': false,
      });

      // Update driver average rating
      await _updateDriverAverageRating(driverId);
    } catch (e) {
      print('[Firestore Error] Failed to submit driver rating: $e');
      rethrow;
    }
  }

  /// Submit passenger rating
  Future<void> submitPassengerRating({
    required String tripId,
    required String passengerId,
    required String driverId,
    required double rating,
    required String? review,
    required Map<String, double> categories,
  }) async {
    try {
      await _db.collection('passenger_ratings').add({
        'passenger_id': passengerId,
        'trip_id': tripId,
        'driver_id': driverId,
        'rating': rating,
        'review': review ?? '',
        'categories': categories,
        'created_at': FieldValue.serverTimestamp(),
        'anonymous': false,
      });
    } catch (e) {
      print('[Firestore Error] Failed to submit passenger rating: $e');
      rethrow;
    }
  }

  /// Update driver average rating
  Future<void> _updateDriverAverageRating(String driverId) async {
    try {
      final ratings = await _db
          .collection('driver_ratings')
          .where('driver_id', isEqualTo: driverId)
          .get();

      if (ratings.docs.isEmpty) return;

      double total = 0;
      for (var doc in ratings.docs) {
        total += doc['rating'] as double;
      }
      double average = total / ratings.docs.length;

      await _db.collection('drivers').doc(driverId).update({
        'average_rating': average,
      });

      await _db.collection('users').doc(driverId).update({
        'rating': average,
      });
    } catch (e) {
      print('[Firestore Error] Failed to update driver average rating: $e');
    }
  }

  // ==================== EMERGENCY ====================

  /// Create emergency request
  Future<String> createEmergencyRequest({
    required String userId,
    required String userType,
    required String emergencyType,
    required double latitude,
    required double longitude,
    required String address,
  }) async {
    try {
      final docRef = await _db.collection('emergency_requests').add({
        'user_id': userId,
        'user_type': userType,
        'trip_id': null,
        'emergency_type': emergencyType,
        'location': {
          'latitude': latitude,
          'longitude': longitude,
          'address': address,
        },
        'emergency_contact_ids': [],
        'police_contacted': false,
        'medical_contacted': false,
        'status': 'active',
        'created_at': FieldValue.serverTimestamp(),
        'acknowledged_at': null,
        'resolved_at': null,
        'notes': '',
        'attachments': [],
      });

      return docRef.id;
    } catch (e) {
      print('[Firestore Error] Failed to create emergency request: $e');
      rethrow;
    }
  }

  // ==================== UTILITY ====================

  /// Cleanup user data on logout
  Future<void> cleanupOnLogout(String userId) async {
    try {
      // Update user status
      await updateUserStatus(userId, false);

      // If driver, update driver status
      final driverDoc = await _db.collection('drivers').doc(userId).get();
      if (driverDoc.exists) {
        await updateDriverStatus(userId, 'offline');
      }

      print('[Firestore] User data cleaned up: $userId');
    } catch (e) {
      print('[Firestore Error] Failed to cleanup user data: $e');
    }
  }

  /// Batch write for performance
  Future<void> batchUpdate(List<Map<String, dynamic>> updates) async {
    try {
      final batch = _db.batch();

      for (var update in updates) {
        final docRef = _db.doc(update['path']);
        batch.update(docRef, update['data']);
      }

      await batch.commit();
    } catch (e) {
      print('[Firestore Error] Failed to perform batch update: $e');
      rethrow;
    }
  }
}
```

---

## 📱 Model Classes with Firestore Serialization

**File:** `lib/models/firestore_models.dart`

```dart
import 'package:cloud_firestore/cloud_firestore.dart';

class ActiveTrip {
  final String id;
  final String passengerId;
  final String? driverId;
  final String status;
  final String rideType;
  final Location pickup;
  final Location dropoff;
  final double distanceKm;
  final int estimatedDurationSeconds;
  final double estimatedFare;
  final String currency;
  final Location driverLocation;
  final Route route;
  final List<Location> passengerLocationHistory;
  final List<Location> driverLocationHistory;
  final List<Event> events;
  final Timeline timeline;
  final Payment payment;
  final Rating rating;
  final Cancellation? cancellation;
  final TripMetadata metadata;

  ActiveTrip({
    required this.id,
    required this.passengerId,
    this.driverId,
    required this.status,
    required this.rideType,
    required this.pickup,
    required this.dropoff,
    required this.distanceKm,
    required this.estimatedDurationSeconds,
    required this.estimatedFare,
    required this.currency,
    required this.driverLocation,
    required this.route,
    required this.passengerLocationHistory,
    required this.driverLocationHistory,
    required this.events,
    required this.timeline,
    required this.payment,
    required this.rating,
    this.cancellation,
    required this.metadata,
  });

  factory ActiveTrip.fromFirestore(DocumentSnapshot snapshot) {
    final data = snapshot.data() as Map<String, dynamic>;

    return ActiveTrip(
      id: snapshot.id,
      passengerId: data['passenger_id'] ?? '',
      driverId: data['driver_id'],
      status: data['status'] ?? 'requested',
      rideType: data['ride_type'] ?? 'private_car',
      pickup: Location.fromMap(data['pickup'] ?? {}),
      dropoff: Location.fromMap(data['dropoff'] ?? {}),
      distanceKm: (data['distance_km'] ?? 0).toDouble(),
      estimatedDurationSeconds: data['estimated_duration_seconds'] ?? 0,
      estimatedFare: (data['estimated_fare'] ?? 0).toDouble(),
      currency: data['currency'] ?? 'RWF',
      driverLocation: Location.fromMap(data['driver_location'] ?? {}),
      route: Route.fromMap(data['route'] ?? {}),
      passengerLocationHistory: (data['passenger_location_history'] as List?)
              ?.map((item) => Location.fromMap(item))
              .toList() ??
          [],
      driverLocationHistory: (data['driver_location_history'] as List?)
              ?.map((item) => Location.fromMap(item))
              .toList() ??
          [],
      events: (data['events'] as List?)
              ?.map((item) => Event.fromMap(item))
              .toList() ??
          [],
      timeline: Timeline.fromMap(data['timeline'] ?? {}),
      payment: Payment.fromMap(data['payment'] ?? {}),
      rating: Rating.fromMap(data['rating'] ?? {}),
      cancellation: data['cancellation'] != null
          ? Cancellation.fromMap(data['cancellation'])
          : null,
      metadata: TripMetadata.fromMap(data['metadata'] ?? {}),
    );
  }

  Map<String, dynamic> toMap() => {
    'passenger_id': passengerId,
    'driver_id': driverId,
    'status': status,
    'ride_type': rideType,
    'pickup': pickup.toMap(),
    'dropoff': dropoff.toMap(),
    'distance_km': distanceKm,
    'estimated_duration_seconds': estimatedDurationSeconds,
    'estimated_fare': estimatedFare,
    'currency': currency,
    'driver_location': driverLocation.toMap(),
    'route': route.toMap(),
    'passenger_location_history': passengerLocationHistory.map((e) => e.toMap()).toList(),
    'driver_location_history': driverLocationHistory.map((e) => e.toMap()).toList(),
    'events': events.map((e) => e.toMap()).toList(),
    'timeline': timeline.toMap(),
    'payment': payment.toMap(),
    'rating': rating.toMap(),
    'cancellation': cancellation?.toMap(),
    'metadata': metadata.toMap(),
  };
}

class Location {
  final double latitude;
  final double longitude;
  final String address;
  final DateTime timestamp;

  Location({
    required this.latitude,
    required this.longitude,
    required this.address,
    required this.timestamp,
  });

  factory Location.fromMap(Map<String, dynamic> map) {
    return Location(
      latitude: (map['latitude'] ?? 0).toDouble(),
      longitude: (map['longitude'] ?? 0).toDouble(),
      address: map['address'] ?? '',
      timestamp: (map['timestamp'] as Timestamp?)?.toDate() ?? DateTime.now(),
    );
  }

  Map<String, dynamic> toMap() => {
    'latitude': latitude,
    'longitude': longitude,
    'address': address,
    'timestamp': timestamp,
  };
}

class Route {
  final String polyline;
  final List<Location> waypoints;
  final DateTime updatedAt;

  Route({
    required this.polyline,
    required this.waypoints,
    required this.updatedAt,
  });

  factory Route.fromMap(Map<String, dynamic> map) {
    return Route(
      polyline: map['polyline'] ?? '',
      waypoints: (map['waypoints'] as List?)
              ?.map((item) => Location.fromMap(item))
              .toList() ??
          [],
      updatedAt: (map['updated_at'] as Timestamp?)?.toDate() ?? DateTime.now(),
    );
  }

  Map<String, dynamic> toMap() => {
    'polyline': polyline,
    'waypoints': waypoints.map((e) => e.toMap()).toList(),
    'updated_at': updatedAt,
  };
}

class Event {
  final String type;
  final DateTime timestamp;
  final Map<String, dynamic> metadata;

  Event({
    required this.type,
    required this.timestamp,
    required this.metadata,
  });

  factory Event.fromMap(Map<String, dynamic> map) {
    return Event(
      type: map['type'] ?? '',
      timestamp: (map['timestamp'] as Timestamp?)?.toDate() ?? DateTime.now(),
      metadata: map['metadata'] ?? {},
    );
  }

  Map<String, dynamic> toMap() => {
    'type': type,
    'timestamp': timestamp,
    'metadata': metadata,
  };
}

class Timeline {
  final DateTime requestedAt;
  final DateTime? acceptedAt;
  final DateTime? driverArrivedAt;
  final DateTime? startedAt;
  final DateTime? completedAt;
  final DateTime? cancelledAt;

  Timeline({
    required this.requestedAt,
    this.acceptedAt,
    this.driverArrivedAt,
    this.startedAt,
    this.completedAt,
    this.cancelledAt,
  });

  factory Timeline.fromMap(Map<String, dynamic> map) {
    return Timeline(
      requestedAt: (map['requested_at'] as Timestamp?)?.toDate() ?? DateTime.now(),
      acceptedAt: (map['accepted_at'] as Timestamp?)?.toDate(),
      driverArrivedAt: (map['driver_arrived_at'] as Timestamp?)?.toDate(),
      startedAt: (map['started_at'] as Timestamp?)?.toDate(),
      completedAt: (map['completed_at'] as Timestamp?)?.toDate(),
      cancelledAt: (map['cancelled_at'] as Timestamp?)?.toDate(),
    );
  }

  Map<String, dynamic> toMap() => {
    'requested_at': requestedAt,
    'accepted_at': acceptedAt,
    'driver_arrived_at': driverArrivedAt,
    'started_at': startedAt,
    'completed_at': completedAt,
    'cancelled_at': cancelledAt,
  };
}

class Payment {
  final String method;
  final String status;
  final double amount;
  final String transactionId;

  Payment({
    required this.method,
    required this.status,
    required this.amount,
    required this.transactionId,
  });

  factory Payment.fromMap(Map<String, dynamic> map) {
    return Payment(
      method: map['method'] ?? 'upi',
      status: map['status'] ?? 'pending',
      amount: (map['amount'] ?? 0).toDouble(),
      transactionId: map['transaction_id'] ?? '',
    );
  }

  Map<String, dynamic> toMap() => {
    'method': method,
    'status': status,
    'amount': amount,
    'transaction_id': transactionId,
  };
}

class Rating {
  final double? passengerRating;
  final double? driverRating;
  final String? passengerReview;
  final String? driverReview;

  Rating({
    this.passengerRating,
    this.driverRating,
    this.passengerReview,
    this.driverReview,
  });

  factory Rating.fromMap(Map<String, dynamic> map) {
    return Rating(
      passengerRating: map['passenger_rating']?.toDouble(),
      driverRating: map['driver_rating']?.toDouble(),
      passengerReview: map['passenger_review'],
      driverReview: map['driver_review'],
    );
  }

  Map<String, dynamic> toMap() => {
    'passenger_rating': passengerRating,
    'driver_rating': driverRating,
    'passenger_review': passengerReview,
    'driver_review': driverReview,
  };
}

class Cancellation {
  final String? reason;
  final String? cancelledBy;
  final double? refundAmount;

  Cancellation({
    this.reason,
    this.cancelledBy,
    this.refundAmount,
  });

  factory Cancellation.fromMap(Map<String, dynamic> map) {
    return Cancellation(
      reason: map['reason'],
      cancelledBy: map['cancelled_by'],
      refundAmount: map['refund_amount']?.toDouble(),
    );
  }

  Map<String, dynamic> toMap() => {
    'reason': reason,
    'cancelled_by': cancelledBy,
    'refund_amount': refundAmount,
  };
}

class TripMetadata {
  final String? promotionCode;
  final double discountAmount;
  final String notes;

  TripMetadata({
    this.promotionCode,
    this.discountAmount = 0,
    this.notes = '',
  });

  factory TripMetadata.fromMap(Map<String, dynamic> map) {
    return TripMetadata(
      promotionCode: map['promotion_code'],
      discountAmount: (map['discount_amount'] ?? 0).toDouble(),
      notes: map['notes'] ?? '',
    );
  }

  Map<String, dynamic> toMap() => {
    'promotion_code': promotionCode,
    'discount_amount': discountAmount,
    'notes': notes,
  };
}

class ChatMessage {
  final String id;
  final String senderId;
  final String senderType;
  final String recipientId;
  final String message;
  final String messageType;
  final DateTime timestamp;
  final bool read;
  final DateTime? readAt;
  final String deliveryStatus;

  ChatMessage({
    required this.id,
    required this.senderId,
    required this.senderType,
    required this.recipientId,
    required this.message,
    required this.messageType,
    required this.timestamp,
    required this.read,
    this.readAt,
    required this.deliveryStatus,
  });

  factory ChatMessage.fromFirestore(DocumentSnapshot snapshot) {
    final data = snapshot.data() as Map<String, dynamic>;

    return ChatMessage(
      id: snapshot.id,
      senderId: data['sender_id'] ?? '',
      senderType: data['sender_type'] ?? 'passenger',
      recipientId: data['recipient_id'] ?? '',
      message: data['message'] ?? '',
      messageType: data['message_type'] ?? 'text',
      timestamp: (data['timestamp'] as Timestamp?)?.toDate() ?? DateTime.now(),
      read: data['read'] ?? false,
      readAt: (data['read_at'] as Timestamp?)?.toDate(),
      deliveryStatus: data['delivery_status'] ?? 'sent',
    );
  }

  Map<String, dynamic> toMap() => {
    'sender_id': senderId,
    'sender_type': senderType,
    'recipient_id': recipientId,
    'message': message,
    'message_type': messageType,
    'timestamp': timestamp,
    'read': read,
    'read_at': readAt,
    'delivery_status': deliveryStatus,
  };
}

class NotificationModel {
  final String id;
  final String userId;
  final String notificationType;
  final String title;
  final String body;
  final Map<String, dynamic> data;
  final String deliveryStatus;
  final List<String> deliveryChannels;
  final DateTime createdAt;
  final DateTime? sentAt;
  final DateTime? readAt;

  NotificationModel({
    required this.id,
    required this.userId,
    required this.notificationType,
    required this.title,
    required this.body,
    required this.data,
    required this.deliveryStatus,
    required this.deliveryChannels,
    required this.createdAt,
    this.sentAt,
    this.readAt,
  });

  factory NotificationModel.fromFirestore(DocumentSnapshot snapshot) {
    final data = snapshot.data() as Map<String, dynamic>;

    return NotificationModel(
      id: snapshot.id,
      userId: data['user_id'] ?? '',
      notificationType: data['notification_type'] ?? '',
      title: data['title'] ?? '',
      body: data['body'] ?? '',
      data: data['data'] ?? {},
      deliveryStatus: data['delivery_status'] ?? 'pending',
      deliveryChannels: List<String>.from(data['delivery_channels'] ?? []),
      createdAt: (data['created_at'] as Timestamp?)?.toDate() ?? DateTime.now(),
      sentAt: (data['sent_at'] as Timestamp?)?.toDate(),
      readAt: (data['read_at'] as Timestamp?)?.toDate(),
    );
  }

  Map<String, dynamic> toMap() => {
    'user_id': userId,
    'notification_type': notificationType,
    'title': title,
    'body': body,
    'data': data,
    'delivery_status': deliveryStatus,
    'delivery_channels': deliveryChannels,
    'created_at': createdAt,
    'sent_at': sentAt,
    'read_at': readAt,
  };
}
```

---

## 🚀 Usage Example in Flutter Widget

```dart
class TripTrackingScreen extends StatefulWidget {
  final String tripId;
  
  const TripTrackingScreen({required this.tripId});

  @override
  State<TripTrackingScreen> createState() => _TripTrackingScreenState();
}

class _TripTrackingScreenState extends State<TripTrackingScreen> {
  late final FirestoreService _firestoreService;
  
  @override
  void initState() {
    super.initState();
    _firestoreService = FirestoreService();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: Text('Trip Tracking')),
      body: StreamBuilder<ActiveTrip?>(
        stream: _firestoreService.getTripStream(widget.tripId),
        builder: (context, snapshot) {
          if (snapshot.hasError) {
            return Center(child: Text('Error: ${snapshot.error}'));
          }
          
          if (snapshot.connectionState == ConnectionState.waiting) {
            return Center(child: CircularProgressIndicator());
          }
          
          final trip = snapshot.data;
          if (trip == null) {
            return Center(child: Text('Trip not found'));
          }
          
          return Column(
            children: [
              // Display trip status
              Text('Status: ${trip.status}'),
              
              // Display driver location
              Text(
                'Driver: ${trip.driverLocation.latitude}, '
                '${trip.driverLocation.longitude}',
              ),
              
              // Display estimated fare
              Text('Estimated Fare: ${trip.estimatedFare} ${trip.currency}'),
              
              // Chat messages
              Expanded(
                child: StreamBuilder<List<ChatMessage>>(
                  stream: _firestoreService.getChatMessages(widget.tripId),
                  builder: (context, snapshot) {
                    if (!snapshot.hasData) return SizedBox.shrink();
                    
                    final messages = snapshot.data!;
                    return ListView.builder(
                      itemCount: messages.length,
                      itemBuilder: (context, index) {
                        final msg = messages[index];
                        return ListTile(
                          title: Text(msg.message),
                          subtitle: Text(msg.senderType),
                        );
                      },
                    );
                  },
                ),
              ),
            ],
          );
        },
      ),
    );
  }
}
```

---

## ✅ Setup Checklist

- [ ] Add Firebase dependencies to `pubspec.yaml`
- [ ] Create `FirestoreService` class
- [ ] Create model classes with Firestore serialization
- [ ] Run `firebase init` and configure Firestore
- [ ] Set security rules in Firebase Console
- [ ] Create composite indexes
- [ ] Test user initialization
- [ ] Test real-time listeners
- [ ] Load test with 1000 concurrent connections
- [ ] Monitor Firestore usage in Firebase Console
- [ ] Set up alerts for quota limits

