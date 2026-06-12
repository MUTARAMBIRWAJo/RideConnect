# Supabase ↔ Firebase Firestore Data Synchronization Guide

**Purpose:** Keep data consistent between Supabase (source of truth) and Firebase Firestore (real-time sync)  
**Last Updated:** June 11, 2026

---

## 🏗️ Architecture Overview

```
┌─────────────────────────────────────────────────────────────┐
│                   RideConnect Backend                       │
├──────────────────────┬────────────────────────────────────┤
│                      │                                    │
│  Supabase PgSQL      │     Firebase Firestore            │
│  (Source of Truth)   │     (Real-time Sync)              │
│  ═══════════════     │     ═════════════════             │
│                      │                                    │
│  • Users             │  Triggered by:                     │
│  • Profiles          │  • Backend API writes              │
│  • Trips             │  • Edge Functions                  │
│  • Payments          │  • Cron jobs                       │
│  • Ratings           │                                    │
│  • Auth              │  Sync Direction:                   │
│                      │  Supabase → Firebase              │
│                      │  (One-way write)                  │
│                      │                                    │
└──────────────────────┴────────────────────────────────────┘
```

**Key Rules:**
1. ✅ Supabase is the **source of truth**
2. ✅ Firebase is **read-optimized** for mobile
3. ✅ Sync is **one-way**: Supabase → Firebase only
4. ✅ Real-time features (GPS, chat) are Firebase-only
5. ❌ Never write critical data to Firebase first

---

## 📝 Data Sync Strategy

### Entities to Sync

| Entity | Supabase | Firebase | Sync Trigger |
|--------|----------|----------|--------------|
| users | ✅ Primary | ✅ Cache | User registration |
| drivers | ✅ Profile | ✅ Status + Location | Driver goes online |
| passengers | ✅ Profile | ✅ Preferences | Passenger registers |
| trips | ✅ History | ✅ Active only | Trip created/updated |
| ratings | ✅ Primary | ✅ Denormalized | Rating submitted |
| payments | ✅ Primary | ❌ None | - |
| chat | ❌ None | ✅ Primary | Real-time messaging |
| locations | ❌ None | ✅ Primary | GPS updates |

---

## 🔄 Sync Implementation

### Option 1: Backend Sync (Recommended)

**File:** `app/Services/FirebaseSync.php`

```php
<?php

namespace App\Services;

use Google\Cloud\Firestore\FirestoreClient;
use Google\Cloud\Firestore\FieldValue;
use Illuminate\Support\Facades\Log;
use Exception;

class FirebaseSync
{
    private FirestoreClient $db;
    private string $projectId;

    public function __construct()
    {
        $this->projectId = config('services.firebase.project_id');
        $this->db = new FirestoreClient([
            'projectId' => $this->projectId,
            'keyFile' => config('services.firebase.key_file'),
        ]);
    }

    // ==================== USER SYNC ====================

    /**
     * Sync user creation from Supabase to Firebase
     */
    public function syncUserCreation($user)
    {
        try {
            $this->db->collection('users')->document($user->id)->set([
                'email' => $user->email,
                'name' => $user->name,
                'role' => $user->role,
                'is_online' => false,
                'last_seen' => new \DateTime(),
                'rating' => 0.0,
                'completed_trips' => 0,
                'cancelled_trips' => 0,
                'metadata' => [
                    'created_at' => new \DateTime(),
                    'updated_at' => new \DateTime(),
                    'firebase_token' => null,
                    'app_version' => '1.0.0',
                ],
            ], ['merge' => true]);

            Log::info("[Firebase] User synced: {$user->id}");
        } catch (Exception $e) {
            Log::error("[Firebase Sync Error] Failed to sync user: {$e->getMessage()}");
            throw $e;
        }
    }

    /**
     * Sync user profile update
     */
    public function syncUserProfileUpdate($user)
    {
        try {
            $this->db->collection('users')->document($user->id)->update([
                ['path' => 'email', 'value' => $user->email],
                ['path' => 'name', 'value' => $user->name],
                ['path' => 'metadata.updated_at', 'value' => new \DateTime()],
            ]);

            Log::info("[Firebase] User profile updated: {$user->id}");
        } catch (Exception $e) {
            Log::error("[Firebase Sync Error] Failed to update user profile: {$e->getMessage()}");
        }
    }

    /**
     * Sync driver profile creation
     */
    public function syncDriverProfileCreation($driver)
    {
        try {
            $this->db->collection('drivers')->document($driver->user_id)->set([
                'user_id' => $driver->user_id,
                'status' => 'offline',
                'current_location' => [
                    'latitude' => 0,
                    'longitude' => 0,
                    'accuracy' => 0,
                    'updated_at' => new \DateTime(),
                ],
                'current_trip_id' => null,
                'vehicle' => [
                    'type' => $driver->vehicle_type,
                    'license_plate' => $driver->license_plate,
                    'color' => $driver->vehicle_color,
                    'model' => $driver->vehicle_model,
                ],
                'service_types' => $driver->service_types ?? ['private_car'],
                'response_time' => 0,
                'acceptance_rate' => 0,
                'cancellation_rate' => 0,
                'average_rating' => 0.0,
                'total_earnings' => 0,
                'available_capacity' => $driver->capacity,
                'metadata' => [
                    'last_location_update' => new \DateTime(),
                    'shift_start' => null,
                    'shift_end' => null,
                    'offline_reason' => null,
                ],
            ]);

            Log::info("[Firebase] Driver profile synced: {$driver->user_id}");
        } catch (Exception $e) {
            Log::error("[Firebase Sync Error] Failed to sync driver profile: {$e->getMessage()}");
            throw $e;
        }
    }

    // ==================== TRIP SYNC ====================

    /**
     * Sync trip creation to Firebase
     */
    public function syncTripCreation($trip)
    {
        try {
            $this->db->collection('active_trips')->document($trip->id)->set([
                'passenger_id' => $trip->passenger_id,
                'driver_id' => $trip->driver_id,
                'status' => $trip->status,
                'ride_type' => $trip->ride_type,
                'pickup' => [
                    'latitude' => $trip->pickup_latitude,
                    'longitude' => $trip->pickup_longitude,
                    'address' => $trip->pickup_address,
                    'timestamp' => new \DateTime($trip->created_at),
                ],
                'dropoff' => [
                    'latitude' => $trip->dropoff_latitude,
                    'longitude' => $trip->dropoff_longitude,
                    'address' => $trip->dropoff_address,
                    'timestamp' => new \DateTime(),
                ],
                'distance_km' => $trip->distance_km,
                'estimated_duration_seconds' => $trip->estimated_duration,
                'estimated_fare' => $trip->estimated_fare,
                'currency' => 'RWF',
                'driver_location' => [
                    'latitude' => 0,
                    'longitude' => 0,
                    'timestamp' => new \DateTime(),
                    'distance_to_pickup' => 0,
                ],
                'route' => [
                    'polyline' => '',
                    'waypoints' => [],
                    'updated_at' => new \DateTime(),
                ],
                'passenger_location_history' => [[
                    'latitude' => $trip->pickup_latitude,
                    'longitude' => $trip->pickup_longitude,
                    'timestamp' => new \DateTime(),
                ]],
                'driver_location_history' => [],
                'events' => [[
                    'type' => 'requested',
                    'timestamp' => new \DateTime(),
                    'metadata' => [],
                ]],
                'timeline' => [
                    'requested_at' => new \DateTime($trip->created_at),
                    'accepted_at' => null,
                    'driver_arrived_at' => null,
                    'started_at' => null,
                    'completed_at' => null,
                    'cancelled_at' => null,
                ],
                'payment' => [
                    'method' => 'upi',
                    'status' => 'pending',
                    'amount' => 0,
                    'transaction_id' => '',
                ],
                'rating' => [
                    'passenger_rating' => null,
                    'driver_rating' => null,
                    'passenger_review' => null,
                    'driver_review' => null,
                ],
                'cancellation' => [
                    'reason' => null,
                    'cancelled_by' => null,
                    'refund_amount' => null,
                ],
                'metadata' => [
                    'promotion_code' => $trip->promo_code,
                    'discount_amount' => $trip->discount_amount,
                    'notes' => '',
                ],
            ]);

            Log::info("[Firebase] Trip synced: {$trip->id}");
        } catch (Exception $e) {
            Log::error("[Firebase Sync Error] Failed to sync trip: {$e->getMessage()}");
            throw $e;
        }
    }

    /**
     * Sync trip status update
     */
    public function syncTripStatusUpdate($trip)
    {
        try {
            $update = [
                ['path' => 'status', 'value' => $trip->status],
            ];

            // Update timeline based on status
            $statusMap = [
                'accepted' => 'timeline.accepted_at',
                'driver_arriving' => 'timeline.driver_arrived_at',
                'arrived' => 'timeline.driver_arrived_at',
                'in_progress' => 'timeline.started_at',
                'completed' => 'timeline.completed_at',
                'cancelled' => 'timeline.cancelled_at',
            ];

            if (isset($statusMap[$trip->status])) {
                $update[] = [
                    'path' => $statusMap[$trip->status],
                    'value' => new \DateTime(),
                ];
            }

            $this->db->collection('active_trips')->document($trip->id)->update($update);

            Log::info("[Firebase] Trip status updated: {$trip->id} -> {$trip->status}");
        } catch (Exception $e) {
            Log::error("[Firebase Sync Error] Failed to update trip status: {$e->getMessage()}");
        }
    }

    /**
     * Sync trip completion
     */
    public function syncTripCompletion($trip, $payment)
    {
        try {
            $this->db->collection('active_trips')->document($trip->id)->update([
                ['path' => 'status', 'value' => 'completed'],
                ['path' => 'timeline.completed_at', 'value' => new \DateTime()],
                ['path' => 'payment.status', 'value' => $payment->status],
                ['path' => 'payment.amount', 'value' => $payment->amount],
                ['path' => 'payment.transaction_id', 'value' => $payment->transaction_id],
            ]);

            // Update driver earnings
            $this->updateDriverEarnings($trip->driver_id, $payment->amount);

            Log::info("[Firebase] Trip completed: {$trip->id}");
        } catch (Exception $e) {
            Log::error("[Firebase Sync Error] Failed to sync trip completion: {$e->getMessage()}");
        }
    }

    /**
     * Remove active trip after 24 hours (archive to completed_trips)
     */
    public function archiveCompletedTrip($tripId)
    {
        try {
            // Get trip data
            $tripSnapshot = $this->db->collection('active_trips')->document($tripId)->snapshot();
            if (!$tripSnapshot->exists()) {
                return;
            }

            $tripData = $tripSnapshot->data();

            // Archive to completed_trips
            $this->db->collection('completed_trips')->document($tripId)->set($tripData);

            // Delete from active_trips
            $this->db->collection('active_trips')->document($tripId)->delete();

            Log::info("[Firebase] Trip archived: {$tripId}");
        } catch (Exception $e) {
            Log::error("[Firebase Sync Error] Failed to archive trip: {$e->getMessage()}");
        }
    }

    // ==================== RATINGS SYNC ====================

    /**
     * Sync rating creation
     */
    public function syncRatingCreation($rating)
    {
        try {
            $this->db->collection('driver_ratings')->add([
                'driver_id' => $rating->driver_id,
                'trip_id' => $rating->trip_id,
                'passenger_id' => $rating->passenger_id,
                'rating' => $rating->rating,
                'review' => $rating->review,
                'categories' => [
                    'cleanliness' => $rating->cleanliness_rating,
                    'driving_skills' => $rating->driving_skills_rating,
                    'communication' => $rating->communication_rating,
                    'vehicle_condition' => $rating->vehicle_condition_rating,
                    'route_efficiency' => $rating->route_efficiency_rating,
                ],
                'created_at' => new \DateTime(),
                'anonymous' => false,
            ]);

            // Update driver average rating
            $this->updateDriverAverageRating($rating->driver_id);

            Log::info("[Firebase] Rating synced: {$rating->id}");
        } catch (Exception $e) {
            Log::error("[Firebase Sync Error] Failed to sync rating: {$e->getMessage()}");
        }
    }

    /**
     * Update driver average rating
     */
    private function updateDriverAverageRating($driverId)
    {
        try {
            // Query all ratings for driver
            $ratingsSnapshot = $this->db->collection('driver_ratings')
                ->where('driver_id', '==', $driverId)
                ->documents();

            $total = 0;
            $count = 0;

            foreach ($ratingsSnapshot as $doc) {
                $total += $doc['rating'];
                $count++;
            }

            if ($count === 0) {
                return;
            }

            $average = $total / $count;

            // Update driver
            $this->db->collection('drivers')->document($driverId)->update([
                ['path' => 'average_rating', 'value' => $average],
            ]);

            // Update user
            $this->db->collection('users')->document($driverId)->update([
                ['path' => 'rating', 'value' => $average],
            ]);

            Log::info("[Firebase] Driver average rating updated: {$driverId} -> {$average}");
        } catch (Exception $e) {
            Log::error("[Firebase Sync Error] Failed to update average rating: {$e->getMessage()}");
        }
    }

    // ==================== DRIVER EARNINGS ====================

    /**
     * Update driver total earnings
     */
    private function updateDriverEarnings($driverId, $amount)
    {
        try {
            $this->db->collection('drivers')->document($driverId)->update([
                ['path' => 'total_earnings', 'value' => FieldValue::increment($amount)],
            ]);

            Log::info("[Firebase] Driver earnings updated: {$driverId} +{$amount}");
        } catch (Exception $e) {
            Log::error("[Firebase Sync Error] Failed to update driver earnings: {$e->getMessage()}");
        }
    }

    // ==================== BATCH SYNC ====================

    /**
     * Batch sync multiple documents
     */
    public function batchSync(array $operations)
    {
        try {
            $batch = $this->db->batch();

            foreach ($operations as $op) {
                $docRef = $this->db->collection($op['collection'])->document($op['id']);

                if ($op['type'] === 'set') {
                    $batch->set($docRef, $op['data'], ['merge' => true]);
                } elseif ($op['type'] === 'update') {
                    $batch->update($docRef, $op['data']);
                } elseif ($op['type'] === 'delete') {
                    $batch->delete($docRef);
                }
            }

            $batch->commit();

            Log::info("[Firebase] Batch sync completed: " . count($operations) . " operations");
        } catch (Exception $e) {
            Log::error("[Firebase Sync Error] Batch sync failed: {$e->getMessage()}");
            throw $e;
        }
    }
}
```

### Option 2: Supabase Edge Functions (Alternative)

**File:** `supabase/functions/sync-to-firebase/index.ts`

```typescript
import { createClient } from "https://esm.sh/@supabase/supabase-js@2";
import { initializeApp } from "https://www.gstatic.com/devrel-devsite/prod/v7dbe4e37920cd4a55bd580441aa58e7853afc04b39a9d9ac4199e1b01f7a1b7d/firebase/prod/v0/firebase-app.js";
import { getFirestore, doc, setDoc, updateDoc, deleteDoc } from "https://www.gstatic.com/devrel-devsite/prod/v7dbe4e37920cd4a55bd580441aa58e7853afc04b39a9d9ac4199e1b01f7a1b7d/firebase/prod/v0/firebase-firestore.js";

const supabase = createClient(
  Deno.env.get("SUPABASE_URL")!,
  Deno.env.get("SUPABASE_SERVICE_ROLE_KEY")!
);

const firebaseApp = initializeApp({
  projectId: Deno.env.get("FIREBASE_PROJECT_ID"),
  // ... other Firebase config
});

const firestore = getFirestore(firebaseApp);

Deno.serve(async (req) => {
  const payload = await req.json();

  try {
    // Sync to Firebase based on payload type
    switch (payload.type) {
      case "user.created":
        await syncUserCreation(payload.data);
        break;
      case "trip.created":
        await syncTripCreation(payload.data);
        break;
      case "trip.updated":
        await syncTripUpdate(payload.data);
        break;
      case "rating.created":
        await syncRatingCreation(payload.data);
        break;
    }

    return new Response(JSON.stringify({ success: true }), {
      headers: { "Content-Type": "application/json" },
    });
  } catch (error) {
    console.error("Sync error:", error);
    return new Response(JSON.stringify({ error: error.message }), {
      status: 500,
      headers: { "Content-Type": "application/json" },
    });
  }
});

async function syncUserCreation(userData) {
  const userRef = doc(firestore, "users", userData.id);
  await setDoc(userRef, {
    email: userData.email,
    name: userData.name,
    role: userData.role,
    is_online: false,
    rating: 0,
    completed_trips: 0,
    cancelled_trips: 0,
    metadata: {
      created_at: new Date(),
      updated_at: new Date(),
    },
  });
}

async function syncTripCreation(tripData) {
  const tripRef = doc(firestore, "active_trips", tripData.id);
  await setDoc(tripRef, {
    passenger_id: tripData.passenger_id,
    driver_id: tripData.driver_id,
    status: tripData.status,
    // ... rest of trip data
  });
}

async function syncTripUpdate(tripData) {
  const tripRef = doc(firestore, "active_trips", tripData.id);
  await updateDoc(tripRef, {
    status: tripData.status,
    // ... updated fields
  });
}

async function syncRatingCreation(ratingData) {
  // Sync rating
}
```

---

## 🔌 Integration Points

### In AuthController.php

```php
use App\Services\FirebaseSync;

class AuthController extends Controller
{
    protected FirebaseSync $firebaseSync;

    public function __construct(FirebaseSync $firebaseSync)
    {
        $this->firebaseSync = $firebaseSync;
    }

    public function register(RegisterRequest $request)
    {
        $user = User::create([
            'email' => $request->email,
            'name' => $request->name,
            'password' => bcrypt($request->password),
            'role' => $request->role,
        ]);

        // Sync to Firebase
        $this->firebaseSync->syncUserCreation($user);

        return response()->json(['success' => true, 'data' => $user]);
    }
}
```

### In TripController.php

```php
public function store(CreateTripRequest $request)
{
    $trip = Trip::create([
        'passenger_id' => auth()->id(),
        'pickup_latitude' => $request->pickup_latitude,
        'pickup_longitude' => $request->pickup_longitude,
        'pickup_address' => $request->pickup_address,
        'dropoff_latitude' => $request->dropoff_latitude,
        'dropoff_longitude' => $request->dropoff_longitude,
        'dropoff_address' => $request->dropoff_address,
        'ride_type' => $request->ride_type,
        'estimated_fare' => $this->calculateFare($request),
        'status' => 'requested',
    ]);

    // Sync to Firebase
    $this->firebaseSync->syncTripCreation($trip);

    return response()->json(['success' => true, 'trip_id' => $trip->id]);
}
```

---

## 📊 Monitoring & Debugging

### Firebase Cloud Logging

```php
// Log sync operations
Log::channel('firebase')->info('Trip synced', [
    'trip_id' => $trip->id,
    'status' => $trip->status,
    'timestamp' => now(),
]);
```

### Dashboard Metrics

**Firebase Console → Firestore → Database Stats:**
- Documents created/updated
- Read/write operations
- Storage usage
- Real-time connection count

### Firestore CLI for Local Testing

```bash
# List all documents in users collection
firebase firestore:export --export-path ./backups/firestore-backup

# Query documents
firebase firestore:delete users/{userId}
```

---

## 🚨 Error Handling & Retry Strategy

```php
class FirebaseSyncWithRetry
{
    public function syncWithRetry($operation, $maxRetries = 3)
    {
        for ($i = 0; $i < $maxRetries; $i++) {
            try {
                return $operation();
            } catch (Exception $e) {
                if ($i === $maxRetries - 1) {
                    // Log to error service
                    \Sentry\captureException($e);
                    throw $e;
                }
                // Exponential backoff
                sleep(2 ** $i);
            }
        }
    }
}
```

---

## ✅ Sync Verification Checklist

- [ ] User syncs after registration
- [ ] Driver profile syncs when going online
- [ ] Trip syncs with all initial data
- [ ] Trip status updates in real-time
- [ ] Ratings sync immediately
- [ ] Driver earnings update after trip completion
- [ ] Firestore mirrors Supabase data accurately
- [ ] No data loss during sync failures
- [ ] All Firebase documents have timestamps
- [ ] Sync happens within 100ms of Supabase write

---

## 📈 Performance Optimization

**Batch Writes:** Group multiple Firebase writes for efficiency
**Debouncing:** Avoid syncing duplicate updates within 1 second
**Selective Sync:** Only sync changed fields, not entire document
**Async Processing:** Queue sync jobs for non-critical data

---

## 🔐 Security Best Practices

1. **Service Account Keys:** Store in environment variables
2. **Least Privilege:** Service account should only write to Firestore
3. **Audit Logging:** Log all sync operations
4. **Rate Limiting:** Cap sync requests per second
5. **Data Validation:** Validate before syncing to Firebase

