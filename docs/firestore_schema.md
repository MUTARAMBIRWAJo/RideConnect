# Firestore Schema for RideConnect

## Overview
This document defines the production Firestore schema for RideConnect's real-time data synchronization layer.

## Collections

### 1. users
**Purpose**: Store user profile information and real-time status

**Document Structure**:
```javascript
{
  email: string,
  name: string,
  phone: string,
  role: string, // 'driver' | 'passenger' | 'admin'
  is_online: boolean,
  last_seen: timestamp,
  rating: number,
  completed_trips: number,
  cancelled_trips: number,
  metadata: {
    created_at: timestamp,
    updated_at: timestamp,
    firebase_token: string | null,
    app_version: string
  }
}
```

**Required Indexes**:
- `role` (ascending)
- `is_online` (ascending)
- `last_seen` (descending)

**Security Rules**:
```javascript
match /users/{userId} {
  allow read: if request.auth != null && (request.auth.uid == userId || hasRole('admin'));
  allow write: if request.auth != null && request.auth.uid == userId;
}
```

**Retention Policy**: 7 years (GDPR compliance)

---

### 2. drivers
**Purpose**: Store driver-specific information and real-time status

**Document Structure**:
```javascript
{
  user_id: string,
  status: string, // 'offline' | 'available' | 'on_trip' | 'break'
  current_location: {
    latitude: number,
    longitude: number,
    accuracy: number,
    updated_at: timestamp
  },
  current_trip_id: string | null,
  vehicle: {
    type: string, // 'economy' | 'comfort' | 'motorcycle'
    license_plate: string,
    color: string,
    model: string
  },
  service_types: array<string>, // ['private_car', 'motorcycle', 'public_bus']
  response_time: number, // seconds
  acceptance_rate: number, // 0-1
  cancellation_rate: number, // 0-1
  average_rating: number,
  total_earnings: number,
  available_capacity: number,
  metadata: {
    last_location_update: timestamp,
    shift_start: timestamp | null,
    shift_end: timestamp | null,
    offline_reason: string | null
  }
}
```

**Required Indexes**:
- `status` (ascending)
- `current_location.latitude` (for geo queries)
- `current_location.longitude` (for geo queries)
- `service_types` (array-contains)
- `metadata.last_location_update` (descending)

**Security Rules**:
```javascript
match /drivers/{driverId} {
  allow read: if request.auth != null;
  allow write: if request.auth != null && request.auth.uid == driverId;
}
```

**Retention Policy**: 7 years

---

### 3. active_trips
**Purpose**: Store real-time trip information for active trips

**Document Structure**:
```javascript
{
  passenger_id: string,
  driver_id: string | null,
  status: string, // 'requested' | 'accepted' | 'driver_arriving' | 'arrived' | 'in_progress' | 'completed' | 'cancelled'
  ride_type: string, // 'private_car' | 'motorcycle' | 'public_bus'
  pickup: {
    latitude: number,
    longitude: number,
    address: string,
    timestamp: timestamp
  },
  dropoff: {
    latitude: number,
    longitude: number,
    address: string,
    timestamp: timestamp
  },
  distance_km: number,
  estimated_duration_seconds: number,
  estimated_fare: number,
  currency: string,
  driver_location: {
    latitude: number,
    longitude: number,
    timestamp: timestamp,
    distance_to_pickup: number
  },
  route: {
    polyline: string,
    waypoints: array<object>,
    updated_at: timestamp
  },
  passenger_location_history: array<object>,
  driver_location_history: array<object>,
  events: array<object>,
  timeline: {
    requested_at: timestamp,
    accepted_at: timestamp | null,
    driver_arrived_at: timestamp | null,
    started_at: timestamp | null,
    completed_at: timestamp | null,
    cancelled_at: timestamp | null
  },
  payment: {
    method: string,
    status: string,
    amount: number,
    transaction_id: string
  },
  rating: {
    passenger_rating: number | null,
    driver_rating: number | null,
    passenger_review: string | null,
    driver_review: string | null
  },
  cancellation: {
    reason: string | null,
    cancelled_by: string | null,
    refund_amount: number | null
  },
  metadata: {
    promotion_code: string | null,
    discount_amount: number,
    notes: string
  }
}
```

**Required Indexes**:
- `passenger_id` (ascending)
- `driver_id` (ascending)
- `status` (ascending)
- `timeline.requested_at` (descending)
- `timeline.completed_at` (descending)

**Security Rules**:
```javascript
match /active_trips/{tripId} {
  allow read: if request.auth != null && (
    request.auth.uid == resource.data.passenger_id || 
    request.auth.uid == resource.data.driver_id ||
    hasRole('admin')
  );
  allow write: if request.auth != null && (
    request.auth.uid == resource.data.passenger_id || 
    request.auth.uid == resource.data.driver_id
  );
}
```

**Retention Policy**: Move to `completed_trips` after 30 days, then 7 years

---

### 4. trip_events
**Purpose**: Store all trip lifecycle events for real-time updates

**Document Structure**:
```javascript
{
  trip_id: number,
  event: string, // 'trip_created' | 'driver_assigned' | 'driver_accepted' | 'driver_arrived' | 'trip_started' | 'trip_completed' | 'payment_completed' | 'rating_submitted'
  payload: object,
  timestamp: timestamp
}
```

**Required Indexes**:
- `trip_id` (ascending)
- `event` (ascending)
- `timestamp` (descending)

**Security Rules**:
```javascript
match /trip_events/{eventId} {
  allow read, write: if request.auth != null;
}
```

**Retention Policy**: 90 days

---

### 5. driver_locations
**Purpose**: Store real-time driver location updates

**Document Structure**:
```javascript
{
  driver_id: string,
  trip_id: string | null,
  location: {
    latitude: number,
    longitude: number,
    accuracy: number,
    heading: number,
    speed: number
  },
  timestamp: timestamp,
  is_online: boolean
}
```

**Required Indexes**:
- `driver_id` (ascending)
- `trip_id` (ascending)
- `timestamp` (descending)
- `location.latitude` (for geo queries)
- `location.longitude` (for geo queries)

**Security Rules**:
```javascript
match /driver_locations/{locationId} {
  allow read: if request.auth != null;
  allow write: if request.auth != null && request.auth.uid == resource.data.driver_id;
}
```

**Retention Policy**: 30 days

---

### 6. trip_tracking
**Purpose**: Store detailed trip tracking information

**Document Structure**:
```javascript
{
  trip_id: number,
  driver_id: string,
  passenger_id: string,
  tracking_data: {
    polyline: string,
    distance_traveled: number,
    duration_seconds: number,
    stops: array<object>
  },
  current_location: {
    latitude: number,
    longitude: number,
    timestamp: timestamp
  },
  eta: number | null, // seconds
  started_at: timestamp,
  updated_at: timestamp
}
```

**Required Indexes**:
- `trip_id` (ascending)
- `driver_id` (ascending)
- `passenger_id` (ascending)
- `updated_at` (descending)

**Security Rules**:
```javascript
match /trip_tracking/{trackingId} {
  allow read: if request.auth != null && (
    request.auth.uid == resource.data.passenger_id || 
    request.auth.uid == resource.data.driver_id
  );
  allow write: if request.auth != null && request.auth.uid == resource.data.driver_id;
}
```

**Retention Policy**: 90 days

---

### 7. notifications
**Purpose**: Store in-app notifications for users

**Document Structure**:
```javascript
{
  user_id: number,
  type: string, // 'trip_update' | 'payment' | 'promotion' | 'system'
  title: string,
  body: string,
  data: object,
  read: boolean,
  timestamp: timestamp,
  expires_at: timestamp | null
}
```

**Required Indexes**:
- `user_id` (ascending)
- `read` (ascending)
- `timestamp` (descending)
- `expires_at` (descending)

**Security Rules**:
```javascript
match /notifications/{notificationId} {
  allow read: if request.auth != null && request.auth.uid == resource.data.user_id;
  allow write: if request.auth != null;
}
```

**Retention Policy**: 30 days after read, 90 days total

---

### 8. chat_rooms
**Purpose**: Store chat room metadata for trip communication

**Document Structure**:
```javascript
{
  trip_id: number,
  participants: array<string>, // [driver_id, passenger_id]
  type: string, // 'trip_chat'
  created_at: timestamp,
  updated_at: timestamp,
  metadata: {
    last_message_at: timestamp | null,
    message_count: number
  }
}
```

**Required Indexes**:
- `trip_id` (ascending)
- `participants` (array-contains)
- `updated_at` (descending)

**Security Rules**:
```javascript
match /chat_rooms/{roomId} {
  allow read: if request.auth != null && resource.data.participants.includes(request.auth.uid);
  allow write: if request.auth != null && resource.data.participants.includes(request.auth.uid);
}
```

**Retention Policy**: 90 days after trip completion

---

### 9. chat_messages
**Purpose**: Store individual chat messages

**Document Structure**:
```javascript
{
  room_id: string,
  sender_id: string,
  message: string,
  message_type: string, // 'text' | 'location' | 'system'
  timestamp: timestamp,
  read_by: array<string>,
  metadata: object
}
```

**Required Indexes**:
- `room_id` (ascending)
- `sender_id` (ascending)
- `timestamp` (descending)

**Security Rules**:
```javascript
match /chat_messages/{messageId} {
  allow read: if request.auth != null && isRoomParticipant(request.auth.uid, resource.data.room_id);
  allow write: if request.auth != null && request.auth.uid == resource.data.sender_id;
}
```

**Retention Policy**: 90 days after trip completion

---

### 10. presence
**Purpose**: Store user presence information for online status

**Document Structure**:
```javascript
{
  user_id: string,
  online: boolean,
  last_seen: timestamp,
  device_info: {
    platform: string, // 'ios' | 'android' | 'web'
    app_version: string
  },
  location: {
    latitude: number | null,
    longitude: number | null
  }
}
```

**Required Indexes**:
- `user_id` (ascending)
- `online` (ascending)
- `last_seen` (descending)

**Security Rules**:
```javascript
match /presence/{userId} {
  allow read: if request.auth != null;
  allow write: if request.auth != null && request.auth.uid == userId;
}
```

**Retention Policy**: 30 days

---

### 11. device_tokens
**Purpose**: Store FCM device tokens for push notifications

**Document Structure**:
```javascript
{
  user_id: string,
  token: string,
  platform: string, // 'ios' | 'android'
  app_version: string,
  active: boolean,
  created_at: timestamp,
  last_used_at: timestamp
}
```

**Required Indexes**:
- `user_id` (ascending)
- `token` (ascending)
- `active` (ascending)
- `last_used_at` (descending)

**Security Rules**:
```javascript
match /device_tokens/{tokenId} {
  allow read: if request.auth != null && request.auth.uid == resource.data.user_id;
  allow write: if request.auth != null && request.auth.uid == resource.data.user_id;
}
```

**Retention Policy**: 1 year after last use

---

## Index Configuration (indexes.json)

```json
{
  "indexes": [
    {
      "collectionGroup": "users",
      "queryScope": "COLLECTION",
      "fields": [
        { "fieldPath": "role", "order": "ASCENDING" },
        { "fieldPath": "is_online", "order": "ASCENDING" },
        { "fieldPath": "last_seen", "order": "DESCENDING" }
      ]
    },
    {
      "collectionGroup": "drivers",
      "queryScope": "COLLECTION",
      "fields": [
        { "fieldPath": "status", "order": "ASCENDING" },
        { "fieldPath": "current_location.latitude", "order": "ASCENDING" },
        { "fieldPath": "current_location.longitude", "order": "ASCENDING" }
      ]
    },
    {
      "collectionGroup": "drivers",
      "queryScope": "COLLECTION",
      "fields": [
        { "fieldPath": "service_types", "order": "ASCENDING" },
        { "fieldPath": "metadata.last_location_update", "order": "DESCENDING" }
      ]
    },
    {
      "collectionGroup": "active_trips",
      "queryScope": "COLLECTION",
      "fields": [
        { "fieldPath": "passenger_id", "order": "ASCENDING" },
        { "fieldPath": "status", "order": "ASCENDING" }
      ]
    },
    {
      "collectionGroup": "active_trips",
      "queryScope": "COLLECTION",
      "fields": [
        { "fieldPath": "driver_id", "order": "ASCENDING" },
        { "fieldPath": "status", "order": "ASCENDING" }
      ]
    },
    {
      "collectionGroup": "trip_events",
      "queryScope": "COLLECTION",
      "fields": [
        { "fieldPath": "trip_id", "order": "ASCENDING" },
        { "fieldPath": "timestamp", "order": "DESCENDING" }
      ]
    },
    {
      "collectionGroup": "notifications",
      "queryScope": "COLLECTION",
      "fields": [
        { "fieldPath": "user_id", "order": "ASCENDING" },
        { "fieldPath": "read", "order": "ASCENDING" },
        { "fieldPath": "timestamp", "order": "DESCENDING" }
      ]
    }
  ],
  "fieldOverrides": []
}
```

---

## Security Rules (firestore.rules)

```javascript
rules_version = '2';
service cloud.firestore {
  match /databases/{database}/documents {
    
    // Helper functions
    function isAuthenticated() {
      return request.auth != null;
    }
    
    function hasRole(role) {
      return isAuthenticated() && 
             get(/databases/$(database)/documents/users/$(request.auth.uid)).data.role == role;
    }
    
    function isRoomParticipant(userId, roomId) {
      return roomId in get(/databases/$(database)/documents/chat_rooms/$(roomId)).data.participants;
    }
    
    // Users collection
    match /users/{userId} {
      allow read: if isAuthenticated() && (request.auth.uid == userId || hasRole('admin'));
      allow write: if isAuthenticated() && request.auth.uid == userId;
    }
    
    // Drivers collection
    match /drivers/{driverId} {
      allow read: if isAuthenticated();
      allow write: if isAuthenticated() && request.auth.uid == driverId;
    }
    
    // Active trips collection
    match /active_trips/{tripId} {
      allow read: if isAuthenticated() && (
        request.auth.uid == resource.data.passenger_id || 
        request.auth.uid == resource.data.driver_id ||
        hasRole('admin')
      );
      allow write: if isAuthenticated() && (
        request.auth.uid == resource.data.passenger_id || 
        request.auth.uid == resource.data.driver_id
      );
    }
    
    // Trip events collection
    match /trip_events/{eventId} {
      allow read, write: if isAuthenticated();
    }
    
    // Driver locations collection
    match /driver_locations/{locationId} {
      allow read: if isAuthenticated();
      allow write: if isAuthenticated() && request.auth.uid == resource.data.driver_id;
    }
    
    // Trip tracking collection
    match /trip_tracking/{trackingId} {
      allow read: if isAuthenticated() && (
        request.auth.uid == resource.data.passenger_id || 
        request.auth.uid == resource.data.driver_id
      );
      allow write: if isAuthenticated() && request.auth.uid == resource.data.driver_id;
    }
    
    // Notifications collection
    match /notifications/{notificationId} {
      allow read: if isAuthenticated() && request.auth.uid == resource.data.user_id;
      allow write: if isAuthenticated();
    }
    
    // Chat rooms collection
    match /chat_rooms/{roomId} {
      allow read: if isAuthenticated() && resource.data.participants.includes(request.auth.uid);
      allow write: if isAuthenticated() && resource.data.participants.includes(request.auth.uid);
    }
    
    // Chat messages collection
    match /chat_messages/{messageId} {
      allow read: if isAuthenticated() && isRoomParticipant(request.auth.uid, resource.data.room_id);
      allow write: if isAuthenticated() && request.auth.uid == resource.data.sender_id;
    }
    
    // Presence collection
    match /presence/{userId} {
      allow read: if isAuthenticated();
      allow write: if isAuthenticated() && request.auth.uid == userId;
    }
    
    // Device tokens collection
    match /device_tokens/{tokenId} {
      allow read: if isAuthenticated() && request.auth.uid == resource.data.user_id;
      allow write: if isAuthenticated() && request.auth.uid == resource.data.user_id;
    }
  }
}
```

---

## Data Migration Strategy

1. **Initial Sync**: Use FirebaseSync service to migrate existing users, drivers, and active trips
2. **Incremental Updates**: Event-driven synchronization via FirebaseEventDispatcher
3. **Backfill**: Scheduled jobs to ensure data consistency between Supabase and Firestore
4. **Validation**: Regular reconciliation jobs to detect and fix data divergence

---

## Monitoring and Maintenance

1. **Daily**: Check Firestore usage metrics and costs
2. **Weekly**: Review index usage and optimize if needed
3. **Monthly**: Audit security rules and access patterns
4. **Quarterly**: Review retention policies and data lifecycle management
