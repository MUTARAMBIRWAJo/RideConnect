# Firebase Firestore Database Schema for RideConnect

**Integration:** Firebase Firestore + Supabase PostgreSQL  
**Purpose:** Real-time data synchronization and event handling  
**Last Updated:** June 11, 2026

---

## 🏗️ Architecture Overview

```
┌─────────────────────────────────────────────────────────┐
│                    RideConnect App                      │
├─────────────────┬─────────────────────────────────────┤
│                 │                                     │
│  Supabase       │         Firebase Firestore          │
│  PostgreSQL     │         Real-time Database          │
│  ════════════   │         ════════════════════        │
│                 │                                     │
│ • Users         │  • Active Trips (real-time)        │
│ • Profiles      │  • Driver Locations (GPS)          │
│ • Trips         │  • Chat Messages                   │
│ • Rides         │  • Notifications/FCM Tokens        │
│ • Payments      │  • User Presence                   │
│ • Ratings       │  • Active Driver Status            │
│ • Auth          │  • Real-time Trip Updates          │
│                 │                                     │
└─────────────────┴─────────────────────────────────────┘
```

**Key Principle:** Supabase = Source of Truth, Firebase = Real-time Sync

---

## 📊 Firestore Collections Schema

### 1. **users** Collection
**Purpose:** User metadata and real-time presence  
**Primary Key:** user_id (matches Supabase users.id)

```javascript
{
  users
  ├── {user_id}
  │   ├── email: string
  │   ├── name: string
  │   ├── avatar_url: string
  │   ├── role: "passenger" | "driver" | "admin"
  │   ├── is_online: boolean
  │   ├── last_seen: timestamp
  │   ├── phone: string
  │   ├── rating: number (0-5)
  │   ├── completed_trips: number
  │   ├── cancelled_trips: number
  │   └── metadata: {
  │       ├── created_at: timestamp
  │       ├── updated_at: timestamp
  │       ├── firebase_token: string
  │       └── app_version: string
  │   }
}
```

**Indexes:**
- `role`, `is_online` (Query: get all online drivers)
- `rating` (Query: ranking by rating)
- `completed_trips` (Query: top drivers)

---

### 2. **drivers** Collection
**Purpose:** Driver real-time status and location  
**Primary Key:** driver_id (matches Supabase users.id)

```javascript
{
  drivers
  ├── {driver_id}
  │   ├── user_id: string (reference to users/{user_id})
  │   ├── status: "online" | "offline" | "on_trip" | "unavailable"
  │   ├── current_location: {
  │   │   ├── latitude: number
  │   │   ├── longitude: number
  │   │   ├── accuracy: number (meters)
  │   │   └── updated_at: timestamp
  │   ├── current_trip_id: string | null
  │   ├── vehicle: {
  │   │   ├── type: "economy" | "comfort" | "premium"
  │   │   ├── license_plate: string
  │   │   ├── color: string
  │   │   └── model: string
  │   ├── service_types: ["private_car", "public_bus", "motorcycle"]
  │   ├── response_time: number (seconds)
  │   ├── acceptance_rate: number (0-100)
  │   ├── cancellation_rate: number (0-100)
  │   ├── average_rating: number (0-5)
  │   ├── total_earnings: number
  │   ├── available_capacity: number
  │   └── metadata: {
  │       ├── last_location_update: timestamp
  │       ├── shift_start: timestamp | null
  │       ├── shift_end: timestamp | null
  │       └── offline_reason: string | null
  │   }
}
```

**Indexes:**
- `status`, `service_types` (Query: find available drivers by service)
- `current_location` (Geo query: drivers within X km)
- `average_rating`, `acceptance_rate` (Query: driver ranking)

---

### 3. **passengers** Collection
**Purpose:** Passenger preferences and travel history  
**Primary Key:** passenger_id (matches Supabase users.id)

```javascript
{
  passengers
  ├── {passenger_id}
  │   ├── user_id: string (reference to users/{user_id})
  │   ├── home_address: {
  │   │   ├── latitude: number
  │   │   ├── longitude: number
  │   │   ├── address: string
  │   │   └── place_id: string
  │   ├── work_address: {
  │   │   ├── latitude: number
  │   │   ├── longitude: number
  │   │   ├── address: string
  │   │   └── place_id: string
  │   ├── preferred_drivers: [driver_id, ...] (up to 5)
  │   ├── blocked_drivers: [driver_id, ...]
  │   ├── emergency_contacts: [{
  │   │   ├── name: string
  │   │   ├── phone: string
  │   │   └── relationship: string
  │   }]
  │   ├── preferences: {
  │   │   ├── share_location: boolean
  │   │   ├── allow_contact_after_ride: boolean
  │   │   ├── preferred_vehicle_type: "economy" | "comfort" | "premium"
  │   │   ├── payment_method: string
  │   │   └── auto_request_upi: boolean
  │   }
  │   ├── saved_places: [{
  │   │   ├── label: "Home" | "Work" | "Custom"
  │   │   ├── latitude: number
  │   │   ├── longitude: number
  │   │   └── address: string
  │   }]
  │   └── stats: {
  │       ├── total_trips: number
  │       ├── average_rating: number (0-5)
  │       └── member_since: timestamp
  │   }
}
```

**Indexes:**
- None (mostly read-heavy, keyed by ID)

---

### 4. **active_trips** Collection
**Purpose:** Real-time trip tracking and status updates  
**Primary Key:** trip_id (matches Supabase trips.id)

```javascript
{
  active_trips
  ├── {trip_id}
  │   ├── passenger_id: string (reference to users/{})
  │   ├── driver_id: string | null (reference to drivers/{})
  │   ├── status: "requested" | "accepted" | "driver_arriving" | "arrived" | "in_progress" | "completed" | "cancelled"
  │   ├── ride_type: "private_car" | "public_bus" | "motorcycle"
  │   ├── pickup: {
  │   │   ├── latitude: number
  │   │   ├── longitude: number
  │   │   ├── address: string
  │   │   └── timestamp: timestamp
  │   ├── dropoff: {
  │   │   ├── latitude: number
  │   │   ├── longitude: number
  │   │   ├── address: string
  │   │   └── timestamp: timestamp
  │   ├── distance_km: number
  │   ├── estimated_duration_seconds: number
  │   ├── estimated_fare: number
  │   ├── currency: "RWF" | "USD"
  │   ├── driver_location: {
  │   │   ├── latitude: number
  │   │   ├── longitude: number
  │   │   ├── timestamp: timestamp
  │   │   └── distance_to_pickup: number (meters)
  │   ├── route: {
  │   │   ├── polyline: string (encoded)
  │   │   ├── waypoints: [{latitude, longitude}, ...]
  │   │   └── updated_at: timestamp
  │   ├── passenger_location_history: [{
  │   │   ├── latitude: number
  │   │   ├── longitude: number
  │   │   └── timestamp: timestamp
  │   }]
  │   ├── driver_location_history: [{
  │   │   ├── latitude: number
  │   │   ├── longitude: number
  │   │   └── timestamp: timestamp
  │   }]
  │   ├── events: [{
  │   │   ├── type: "requested" | "accepted" | "arrived" | "started" | "completed" | "cancelled"
  │   │   ├── timestamp: timestamp
  │   │   └── metadata: object
  │   }]
  │   ├── timeline: {
  │   │   ├── requested_at: timestamp
  │   │   ├── accepted_at: timestamp | null
  │   │   ├── driver_arrived_at: timestamp | null
  │   │   ├── started_at: timestamp | null
  │   │   ├── completed_at: timestamp | null
  │   │   └── cancelled_at: timestamp | null
  │   ├── payment: {
  │   │   ├── method: "upi" | "card" | "cash" | "wallet"
  │   │   ├── status: "pending" | "completed" | "failed"
  │   │   ├── amount: number
  │   │   └── transaction_id: string
  │   ├── rating: {
  │   │   ├── passenger_rating: number (0-5) | null
  │   │   ├── driver_rating: number (0-5) | null
  │   │   ├── passenger_review: string | null
  │   │   └── driver_review: string | null
  │   ├── cancellation: {
  │   │   ├── reason: string | null
  │   │   ├── cancelled_by: "passenger" | "driver" | "admin" | null
  │   │   └── refund_amount: number | null
  │   └── metadata: {
  │       ├── promotion_code: string | null
  │       ├── discount_amount: number
  │       └── notes: string
  │   }
}
```

**Indexes (CRITICAL):**
- `status`, `passenger_id` (Query: get passenger's trips)
- `status`, `driver_id` (Query: get driver's trips)
- `status`, `requested_at` (Query: find active requests)
- TTL Policy: Delete documents 30 days after `completed_at`

---

### 5. **trip_requests** Collection
**Purpose:** Unmatched trip requests (temporary)  
**Primary Key:** request_id

```javascript
{
  trip_requests
  ├── {request_id}
  │   ├── passenger_id: string (reference to users/{})
  │   ├── pickup: {
  │   │   ├── latitude: number
  │   │   ├── longitude: number
  │   │   ├── address: string
  │   ├── dropoff: {
  │   │   ├── latitude: number
  │   │   ├── longitude: number
  │   │   ├── address: string
  │   ├── ride_type: "private_car" | "public_bus" | "motorcycle"
  │   ├── requested_at: timestamp
  │   ├── expires_at: timestamp (5 minutes from request)
  │   ├── estimated_fare: number
  │   ├── search_radius: number (meters)
  │   ├── candidate_drivers: [driver_id, ...] (offered to these drivers)
  │   ├── responses: {
  │   │   ├── {driver_id}: {
  │   │   │   ├── status: "pending" | "accepted" | "rejected"
  │   │   │   └── responded_at: timestamp
  │   │   }
  │   }
  │   └── status: "active" | "matched" | "expired" | "cancelled"
}
```

**Indexes:**
- `status`, `requested_at` (Query: find active requests)
- TTL Policy: Delete documents 10 minutes after `requested_at`

---

### 6. **chat_messages** Collection
**Purpose:** Real-time chat between passenger and driver  
**Nested Structure:** `active_trips/{trip_id}/chat_messages/{message_id}`

```javascript
{
  active_trips/{trip_id}/chat_messages
  ├── {message_id}
  │   ├── sender_id: string (user_id)
  │   ├── sender_type: "passenger" | "driver"
  │   ├── recipient_id: string (user_id)
  │   ├── message: string
  │   ├── message_type: "text" | "location" | "emergency"
  │   ├── timestamp: timestamp (server timestamp)
  │   ├── read: boolean
  │   ├── read_at: timestamp | null
  │   ├── delivery_status: "sent" | "delivered" | "read"
  │   └── attachments: [{
  │       ├── type: "image" | "location"
  │       ├── url: string
  │       └── metadata: object
  │   }]
}
```

**Indexes:**
- `timestamp` (Query: get message history)
- TTL Policy: Delete documents 90 days after trip completion

---

### 7. **notifications** Collection
**Purpose:** Notification queue and delivery tracking  
**Primary Key:** notification_id

```javascript
{
  notifications
  ├── {notification_id}
  │   ├── user_id: string (recipient, reference to users/{})
  │   ├── notification_type: "trip_accepted" | "driver_arriving" | "trip_completed" | "payment_received" | "emergency_alert" | "chat_message" | "promotion"
  │   ├── title: string
  │   ├── body: string
  │   ├── data: {
  │   │   ├── trip_id: string
  │   │   ├── driver_id: string
  │   │   ├── action_url: string
  │   │   └── custom_data: object
  │   ├── delivery_status: "pending" | "sent" | "delivered" | "read" | "failed"
  │   ├── delivery_channels: ["fcm_token", "in_app", "email", "sms"]
  │   ├── created_at: timestamp
  │   ├── sent_at: timestamp | null
  │   ├── read_at: timestamp | null
  │   ├── fcm_tokens: [token1, token2, ...] (multiple devices)
  │   └── retry_count: number
}
```

**Indexes:**
- `user_id`, `delivery_status` (Query: pending notifications)
- TTL Policy: Delete documents 30 days after creation

---

### 8. **fcm_tokens** Collection
**Purpose:** Device FCM token management  
**Primary Key:** token_id

```javascript
{
  fcm_tokens
  ├── {token_id}
  │   ├── user_id: string (reference to users/{})
  │   ├── token: string (FCM registration token)
  │   ├── platform: "android" | "ios" | "web"
  │   ├── device_model: string
  │   ├── app_version: string
  │   ├── os_version: string
  │   ├── is_active: boolean
  │   ├── created_at: timestamp
  │   ├── last_used_at: timestamp
  │   └── metadata: {
  │       ├── notification_enabled: boolean
  │       ├── sound_enabled: boolean
  │       └── vibration_enabled: boolean
  │   }
}
```

**Indexes:**
- `user_id`, `is_active` (Query: active tokens for user)
- TTL Policy: Delete if not used for 90 days

---

### 9. **driver_ratings** Collection
**Purpose:** Driver performance metrics and reviews  
**Primary Key:** rating_id

```javascript
{
  driver_ratings
  ├── {rating_id}
  │   ├── driver_id: string (reference to drivers/{})
  │   ├── trip_id: string (reference to active_trips/{})
  │   ├── passenger_id: string (reference to users/{})
  │   ├── rating: number (1-5)
  │   ├── review: string (optional)
  │   ├── categories: {
  │   │   ├── cleanliness: number (1-5)
  │   │   ├── driving_skills: number (1-5)
  │   │   ├── communication: number (1-5)
  │   │   ├── vehicle_condition: number (1-5)
  │   │   └── route_efficiency: number (1-5)
  │   ├── created_at: timestamp
  │   └── anonymous: boolean (passenger identity hidden)
}
```

**Indexes:**
- `driver_id`, `created_at` (Query: driver's ratings)

---

### 10. **passenger_ratings** Collection
**Purpose:** Passenger behavior and reputation  
**Primary Key:** rating_id

```javascript
{
  passenger_ratings
  ├── {rating_id}
  │   ├── passenger_id: string (reference to passengers/{})
  │   ├── trip_id: string (reference to active_trips/{})
  │   ├── driver_id: string (reference to drivers/{})
  │   ├── rating: number (1-5)
  │   ├── review: string (optional)
  │   ├── categories: {
  │   │   ├── cleanliness: number (1-5)
  │   │   ├── respectful_behavior: number (1-5)
  │   │   ├── communication: number (1-5)
  │   │   ├── punctuality: number (1-5)
  │   │   └── safety: number (1-5)
  │   ├── created_at: timestamp
  │   └── anonymous: boolean
}
```

**Indexes:**
- `passenger_id`, `created_at` (Query: passenger's ratings)

---

### 11. **emergency_requests** Collection
**Purpose:** SOS and emergency alerts  
**Primary Key:** emergency_id

```javascript
{
  emergency_requests
  ├── {emergency_id}
  │   ├── user_id: string (reference to users/{})
  │   ├── user_type: "passenger" | "driver"
  │   ├── trip_id: string | null (reference to active_trips/{})
  │   ├── emergency_type: "sos" | "accident" | "medical" | "harassment" | "threat"
  │   ├── location: {
  │   │   ├── latitude: number
  │   │   ├── longitude: number
  │   │   └── address: string
  │   ├── emergency_contact_ids: [user_id, ...] (emergency contacts)
  │   ├── police_contacted: boolean
  │   ├── medical_contacted: boolean
  │   ├── status: "active" | "acknowledged" | "resolved"
  │   ├── created_at: timestamp
  │   ├── acknowledged_at: timestamp | null
  │   ├── resolved_at: timestamp | null
  │   ├── notes: string
  │   └── attachments: [{
  │       ├── type: "photo" | "audio" | "video"
  │       └── url: string
  │   }]
}
```

**Indexes:**
- `status`, `created_at` (Query: active emergencies)

---

### 12. **support_tickets** Collection
**Purpose:** Customer support conversations  
**Primary Key:** ticket_id

```javascript
{
  support_tickets
  ├── {ticket_id}
  │   ├── user_id: string (reference to users/{})
  │   ├── trip_id: string | null (reference to active_trips/{})
  │   ├── category: "complaint" | "payment" | "lost_item" | "accident" | "general"
  │   ├── subject: string
  │   ├── description: string
  │   ├── status: "open" | "in_progress" | "resolved" | "closed"
  │   ├── priority: "low" | "medium" | "high" | "critical"
  │   ├── created_at: timestamp
  │   ├── updated_at: timestamp
  │   ├── resolved_at: timestamp | null
  │   ├── assigned_to: string (admin_id) | null
  │   └── messages: [{
  │       ├── sender_id: string
  │       ├── sender_type: "user" | "admin"
  │       ├── message: string
  │       └── timestamp: timestamp
  │   }]
}
```

**Indexes:**
- `status`, `created_at` (Query: open tickets)
- `priority`, `status` (Query: high priority tickets)

---

### 13. **promotions** Collection
**Purpose:** Active promotions and discount codes  
**Primary Key:** promotion_id

```javascript
{
  promotions
  ├── {promotion_id}
  │   ├── code: string (unique)
  │   ├── title: string
  │   ├── description: string
  │   ├── discount_type: "percentage" | "fixed_amount"
  │   ├── discount_value: number
  │   ├── max_discount: number (for percentage discounts)
  │   ├── min_ride_amount: number
  │   ├── max_uses: number
  │   ├── used_count: number
  │   ├── valid_from: timestamp
  │   ├── valid_until: timestamp
  │   ├── eligible_users: ["new_users" | "specific_ids" | "all"]
  │   ├── eligible_ride_types: ["private_car" | "public_bus" | "motorcycle"]
  │   ├── is_active: boolean
  │   └── metadata: {
  │       ├── created_by: string (admin_id)
  │       └── campaign_name: string
  │   }
}
```

**Indexes:**
- `is_active`, `valid_until` (Query: active promotions)
- `code` (Unique lookup)

---

### 14. **analytics** Collection
**Purpose:** Real-time analytics and metrics  
**Primary Key:** metric_id

```javascript
{
  analytics
  ├── daily/{YYYY-MM-DD}
  │   ├── total_requests: number
  │   ├── total_completed_trips: number
  │   ├── total_cancelled_trips: number
  │   ├── total_revenue: number
  │   ├── active_drivers: number
  │   ├── active_passengers: number
  │   ├── average_wait_time: number (seconds)
  │   ├── average_trip_duration: number (seconds)
  │   ├── average_fare: number
  │   └── peak_hours: object
  │
  ├── hourly/{YYYY-MM-DD/HH}
  │   ├── requests: number
  │   ├── completed: number
  │   ├── cancelled: number
  │   ├── active_drivers: number
  │   └── revenue: number
}
```

**Indexes:**
- None (time-series data)

---

## 🔐 Firestore Security Rules

```javascript
rules_version = '2';
service cloud.firestore {
  match /databases/{database}/documents {
    
    // Helper functions
    function isAuthenticated() {
      return request.auth != null;
    }
    
    function isUser(userId) {
      return isAuthenticated() && request.auth.uid == userId;
    }
    
    function getUserRole() {
      return get(/databases/$(database)/documents/users/$(request.auth.uid)).data.role;
    }
    
    function isDriver() {
      return getUserRole() == 'driver';
    }
    
    function isPassenger() {
      return getUserRole() == 'passenger';
    }
    
    function isAdmin() {
      return getUserRole() == 'admin' || getUserRole() == 'superadmin';
    }
    
    // Users collection
    match /users/{userId} {
      allow read: if isAuthenticated();
      allow write: if isUser(userId) && 
                     !request.resource.data.keys().hasAny(['role', 'is_verified']);
      allow update: if isUser(userId);
    }
    
    // Drivers collection
    match /drivers/{driverId} {
      allow read: if isAuthenticated();
      allow create: if isDriver() && isUser(request.auth.uid);
      allow update: if isUser(driverId) || isAdmin();
    }
    
    // Passengers collection
    match /passengers/{passengerId} {
      allow read: if isUser(passengerId) || isAdmin();
      allow create: if isPassenger() && isUser(request.auth.uid);
      allow update: if isUser(passengerId);
    }
    
    // Active trips collection
    match /active_trips/{tripId} {
      allow read: if isAuthenticated() && 
                     (isUser(resource.data.passenger_id) || 
                      isUser(resource.data.driver_id) || 
                      isAdmin());
      allow create: if isPassenger();
      allow update: if isUser(resource.data.driver_id) || 
                       isUser(resource.data.passenger_id) || 
                       isAdmin();
      
      // Chat messages nested subcollection
      match /chat_messages/{messageId} {
        allow read: if isUser(resource.data.sender_id) || 
                       isUser(resource.data.recipient_id);
        allow create: if isUser(request.resource.data.sender_id);
        allow update: if isUser(request.resource.data.sender_id);
      }
    }
    
    // Trip requests collection
    match /trip_requests/{requestId} {
      allow read: if isUser(resource.data.passenger_id) || isAdmin();
      allow create: if isPassenger();
      allow update: if isUser(resource.data.passenger_id) || isAdmin();
    }
    
    // Notifications collection
    match /notifications/{notificationId} {
      allow read: if isUser(resource.data.user_id);
      allow create: if isAdmin() || isAuthenticated();
      allow update: if isUser(resource.data.user_id);
    }
    
    // FCM tokens collection
    match /fcm_tokens/{tokenId} {
      allow read: if isUser(resource.data.user_id) || isAdmin();
      allow create: if isAuthenticated();
      allow write: if isUser(resource.data.user_id);
    }
    
    // Ratings collections
    match /driver_ratings/{ratingId} {
      allow read: if isAuthenticated();
      allow create: if isPassenger() && 
                       isUser(request.resource.data.passenger_id);
    }
    
    match /passenger_ratings/{ratingId} {
      allow read: if isDriver();
      allow create: if isDriver() && 
                       isUser(request.resource.data.driver_id);
    }
    
    // Emergency requests
    match /emergency_requests/{emergencyId} {
      allow read: if isUser(resource.data.user_id) || isAdmin();
      allow create: if isAuthenticated() && 
                       isUser(request.resource.data.user_id);
      allow update: if isAdmin();
    }
    
    // Support tickets
    match /support_tickets/{ticketId} {
      allow read: if isUser(resource.data.user_id) || isAdmin();
      allow create: if isAuthenticated();
      allow update: if isUser(resource.data.user_id) || isAdmin();
    }
    
    // Promotions (read-only for users)
    match /promotions/{promotionId} {
      allow read: if isAuthenticated();
      allow write: if isAdmin();
    }
    
    // Analytics (admin only)
    match /analytics/{document=**} {
      allow read: if isAdmin();
      allow write: if false; // Written by backend only
    }
    
    // Catch-all deny
    match /{document=**} {
      allow read, write: if false;
    }
  }
}
```

---

## 🗂️ Firestore Indexes

Create these composite indexes for optimal query performance:

```yaml
indexes:
  - collectionGroup: drivers
    queryScope: COLLECTION
    fields:
      - fieldPath: status
        order: ASCENDING
      - fieldPath: current_location
        order: ASCENDING
      
  - collectionGroup: drivers
    queryScope: COLLECTION
    fields:
      - fieldPath: status
        order: ASCENDING
      - fieldPath: average_rating
        order: DESCENDING
      
  - collectionGroup: active_trips
    queryScope: COLLECTION
    fields:
      - fieldPath: passenger_id
        order: ASCENDING
      - fieldPath: timeline.requested_at
        order: DESCENDING
      
  - collectionGroup: active_trips
    queryScope: COLLECTION
    fields:
      - fieldPath: driver_id
        order: ASCENDING
      - fieldPath: status
        order: ASCENDING
      
  - collectionGroup: active_trips
    queryScope: COLLECTION
    fields:
      - fieldPath: status
        order: ASCENDING
      - fieldPath: timeline.requested_at
        order: DESCENDING
      
  - collectionGroup: trip_requests
    queryScope: COLLECTION
    fields:
      - fieldPath: status
        order: ASCENDING
      - fieldPath: requested_at
        order: DESCENDING
      
  - collectionGroup: notifications
    queryScope: COLLECTION
    fields:
      - fieldPath: user_id
        order: ASCENDING
      - fieldPath: delivery_status
        order: ASCENDING
      
  - collectionGroup: driver_ratings
    queryScope: COLLECTION
    fields:
      - fieldPath: driver_id
        order: ASCENDING
      - fieldPath: created_at
        order: DESCENDING
```

---

## 📱 Integration with Supabase

### Data Sync Strategy

```
Supabase (Source of Truth)
    ↓
    ↓ Write Operation
    ↓
PostgreSQL Transaction
    ↓
    ↓ Success
    ↓
Firebase Realtime Listener
    ↓
    ↓ Sync Trigger (Edge Function or Backend)
    ↓
Firestore Write
    ↓
    ↓ Success
    ↓
Push Notification (FCM)
    ↓
    ↓
Flutter App (Real-time Update)
```

### Implementation Pattern

**Backend (Laravel):**
```php
// After creating trip in Supabase
$trip = Trip::create([
    'passenger_id' => $passengerId,
    'pickup_latitude' => $lat,
    'pickup_longitude' => $lng,
    // ...
]);

// Sync to Firebase
FirebaseService::syncTrip($trip);
```

**Firebase Edge Function (trigger on Firestore write):**
```typescript
exports.onTripCreated = functions.firestore
  .document('active_trips/{tripId}')
  .onCreate(async (snapshot) => {
    const trip = snapshot.data();
    
    // Send notification to nearby drivers
    await admin.messaging().sendToTopic('drivers', {
      notification: {
        title: 'New Trip Request',
        body: `${trip.pickup} → ${trip.dropoff}`,
      },
      data: {
        trip_id: tripId,
        action: 'trip_request',
      },
    });
  });
```

---

## 🔄 Real-Time Features

### 1. **Driver Location Tracking**
```dart
// Flutter implementation
Stream<DriverLocation> trackDriverLocation(String driverId) {
  return FirebaseFirestore.instance
      .collection('drivers')
      .doc(driverId)
      .snapshots()
      .map((snapshot) => DriverLocation.fromFirestore(snapshot));
}

// Update driver location (every 10 seconds during trip)
Future<void> updateDriverLocation(String driverId, double lat, double lng) async {
  await FirebaseFirestore.instance
      .collection('drivers')
      .doc(driverId)
      .update({
        'current_location': {
          'latitude': lat,
          'longitude': lng,
          'updated_at': FieldValue.serverTimestamp(),
        },
      });
}
```

### 2. **Real-Time Chat**
```dart
// Listen to chat messages
Stream<List<ChatMessage>> getChatMessages(String tripId) {
  return FirebaseFirestore.instance
      .collection('active_trips')
      .doc(tripId)
      .collection('chat_messages')
      .orderBy('timestamp')
      .snapshots()
      .map((snapshot) =>
          snapshot.docs.map((doc) => ChatMessage.fromFirestore(doc)).toList());
}

// Send chat message
Future<void> sendChatMessage(String tripId, String message) async {
  await FirebaseFirestore.instance
      .collection('active_trips')
      .doc(tripId)
      .collection('chat_messages')
      .add({
        'sender_id': currentUserId,
        'sender_type': userRole,
        'recipient_id': otherUserId,
        'message': message,
        'timestamp': FieldValue.serverTimestamp(),
        'delivery_status': 'sent',
      });
}
```

### 3. **Trip Status Updates**
```dart
// Listen to trip status changes
Stream<ActiveTrip> getTripUpdates(String tripId) {
  return FirebaseFirestore.instance
      .collection('active_trips')
      .doc(tripId)
      .snapshots()
      .map((snapshot) => ActiveTrip.fromFirestore(snapshot));
}

// Update trip status (backend function called)
Future<void> updateTripStatus(String tripId, String status) async {
  await FirebaseFirestore.instance
      .collection('active_trips')
      .doc(tripId)
      .update({
        'status': status,
        'timeline.${status}_at': FieldValue.serverTimestamp(),
      });
}
```

---

## 📊 Database Size Estimation

```
Collection          | Avg Doc Size | Docs/Day | 1-Year Size
────────────────────┼──────────────┼──────────┼─────────────
users               | 2 KB         | 100      | 73 MB
drivers             | 3 KB         | 100      | 110 MB
passengers          | 2 KB         | 100      | 73 MB
active_trips        | 5 KB         | 1000     | 1.8 GB*
chat_messages       | 1 KB         | 5000     | 1.8 GB*
notifications       | 2 KB         | 10000    | 7.3 GB*
fcm_tokens          | 0.5 KB       | 200      | 37 MB
ratings             | 1 KB         | 500      | 183 MB
emergency_requests  | 2 KB         | 10       | 7.3 MB
support_tickets     | 3 KB         | 50       | 55 MB
promotions          | 1 KB         | 50       | 18 MB
analytics           | 1 KB         | 100      | 37 MB
────────────────────┼──────────────┼──────────┼─────────────
TOTAL (with TTL)    |              |          | ~3 GB/year

* TTL policies delete old data (30-90 days)
```

---

## 💰 Firestore Pricing

**Based on 10,000 daily active users:**

```
Operations/Month    | Estimated Cost
──────────────────┼──────────────────
Reads:  5M         | $1.70
Writes: 2M         | $0.68
Deletes: 1M        | $0.34
Storage: 3GB       | $0.60
──────────────────┼──────────────────
TOTAL              | ~$3.50/month

Note: Free tier includes:
- 50K reads/day
- 20K writes/day
- 20K deletes/day
- 1 GB storage
```

---

## ✅ Migration Checklist

- [ ] Create all 14 collections in Firestore
- [ ] Set up security rules
- [ ] Create composite indexes
- [ ] Configure TTL policies
- [ ] Set up backup (daily to Cloud Storage)
- [ ] Create Firestore-to-Supabase sync functions
- [ ] Test real-time listeners
- [ ] Load test with 1000 concurrent users
- [ ] Monitor performance in Firebase console
- [ ] Set up alerts for quota usage
- [ ] Train team on querying patterns

---

## 📚 Related Documentation

- [Firebase Security Rules](https://firebase.google.com/docs/firestore/security/rules-overview)
- [Firestore Best Practices](https://firebase.google.com/docs/firestore/best-practices)
- [Geoqueries](https://firebase.google.com/docs/firestore/solutions/geoqueries)
- [TTL Policies](https://firebase.google.com/docs/firestore/ttl)

