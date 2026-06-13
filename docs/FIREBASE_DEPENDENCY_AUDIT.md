# Firebase Dependency Audit

**Generated:** 2026-06-13
**Phase:** A - Dependency Audit
**Status:** Complete

## Executive Summary

This audit identifies all references to legacy Firebase classes that need to be migrated to the unified FirebaseSyncService architecture.

**Key Findings:**
- 6 legacy classes identified
- 3 jobs directly reference FirebaseSync
- 1 health check references FirebaseRealtimeService
- FirebaseEventDispatcher has no active references (deprecated)
- All 3 event listeners already delegate to FirebaseSyncService
- EventServiceProvider already uses UnifiedFirebaseSyncListener

## Legacy Classes Audit

### 1. FirebaseSync
**File:** `app/Services/FirebaseSync.php`
**Status:** ACTIVE - Needs migration
**Lines:** 682

#### References Found:

| File | Class | Method | Reference Type | Migration Strategy |
|------|-------|--------|----------------|-------------------|
| `app/Jobs/FirebaseSyncJob.php` | FirebaseSyncJob | handle() | Constructor Injection | Replace with FirebaseSyncService |
| `app/Jobs/DriverLocationSyncJob.php` | DriverLocationSyncJob | handle() | Constructor Injection | Replace with FirebaseSyncService |
| `app/Services/FirebaseEventDispatcher.php` | FirebaseEventDispatcher | __construct() | Constructor Injection | Replace with FirebaseSyncService |

#### Methods Used:
- `isEnabled()` - Check if Firebase is enabled
- `syncUserCreation()` - Sync user creation
- `syncUserProfileUpdate()` - Sync user profile update
- `syncDriverProfileCreation()` - Sync driver profile creation
- `syncDriverStatus()` - Sync driver status
- `syncDriverLocation()` - Sync driver location
- `syncTripCreation()` - Sync trip creation
- `syncTripStatusUpdate()` - Sync trip status update
- `syncTripCompletion()` - Sync trip completion
- `syncRatingCreation()` - Sync rating creation
- `batchSync()` - Batch sync operations
- `healthCheck()` - Health check

#### Migration Path:
Convert to thin wrapper delegating to FirebaseSyncService:
```php
class FirebaseSync
{
    public function __construct(
        private readonly FirebaseSyncService $firebaseSyncService,
    ) {
        Log::warning('[FirebaseSync] DEPRECATED - Use FirebaseSyncService instead');
    }

    public function isEnabled(): bool
    {
        return $this->firebaseSyncService->isEnabled();
    }

    public function syncDriverLocation(string $driverId, float $latitude, float $longitude, float $accuracy = 0): bool
    {
        return $this->firebaseSyncService->syncDriverLocation($driverId, $latitude, $longitude, $accuracy);
    }

    // ... other methods delegate to FirebaseSyncService
}
```

---

### 2. FirebaseEventDispatcher
**File:** `app/Services/FirebaseEventDispatcher.php`
**Status:** DEPRECATED - No active references
**Lines:** 247

#### References Found:
**NONE** - This class is already marked as deprecated and has no active consumers.

#### Migration Path:
Convert to thin wrapper delegating to FirebaseSyncService:
```php
class FirebaseEventDispatcher
{
    public function __construct(
        private readonly FirebaseSyncService $firebaseSyncService,
    ) {
        Log::warning('[FirebaseEventDispatcher] DEPRECATED - Use FirebaseSyncService instead');
    }

    public function dispatchTripCreated(Trip $trip): void
    {
        $this->firebaseSyncService->syncEvent('TripCreated', [
            'trip_id' => $trip->id,
            // ... other fields
        ]);
    }

    // ... other methods delegate to FirebaseSyncService::syncEvent()
}
```

---

### 3. FirebaseRealtimeService
**File:** `app/Services/FirebaseRealtimeService.php`
**Status:** PARTIALLY MIGRATED - Read-only mode
**Lines:** 153

#### References Found:

| File | Class | Method | Reference Type | Migration Strategy |
|------|-------|--------|----------------|-------------------|
| `app/Services/Health/Checks/FirebaseHealthCheck.php` | FirebaseHealthCheck | __construct() | Constructor Injection | Replace with FirebaseSyncService |
| `app/Services/FirebaseEventDispatcher.php` | FirebaseEventDispatcher | __construct() | Constructor Injection | Replace with FirebaseSyncService |

#### Current State:
- Already delegates to FirebaseSyncService for write operations
- `pushTripEvent()` routes to `FirebaseSyncService::syncEvent()`
- `pushNotification()` is deprecated (handled internally by FirebaseSyncService)
- Kept for connectivity status checks and health monitoring

#### Migration Path:
Keep as read-only connectivity layer, delegate all writes to FirebaseSyncService:
```php
class FirebaseRealtimeService
{
    public function __construct(
        private readonly FirebaseSyncService $firebaseSyncService,
    ) {
        // Initialize read-only connections
    }

    public function pushTripEvent(string $tripId, string $event, array $payload = []): void
    {
        Log::warning('[FirebaseRealtimeService] pushTripEvent called - DEPRECATED');
        $this->firebaseSyncService->syncEvent($event, array_merge($payload, ['trip_id' => $tripId]));
    }

    // Keep connectivityStatus() for health checks
}
```

---

### 4. SyncTripEventsToFirebase
**File:** `app/Listeners/Firebase/SyncTripEventsToFirebase.php`
**Status:** DEPRECATED - Already delegates to FirebaseSyncService
**Lines:** 146

#### References Found:
**NONE** - Not registered in EventServiceProvider (replaced by UnifiedFirebaseSyncListener)

#### Current State:
- Already delegates all operations to FirebaseSyncService
- Marked as DEPRECATED in class docblock
- Handles: TripMatched, TripStarted, TripCompleted, MotorcycleTripStarted, MotorcycleDriverArrived, MotorcycleTripCompleted

#### Migration Path:
Already compliant - keep as thin wrapper for backward compatibility:
```php
class SyncTripEventsToFirebase
{
    public function __construct(
        private readonly FirebaseSyncService $firebaseSyncService,
    ) {}

    public function handle(object $event): void
    {
        // Already delegates to firebaseSyncService->syncEvent()
    }
}
```

---

### 5. SyncPaymentEventsToFirebase
**File:** `app/Listeners/Firebase/SyncPaymentEventsToFirebase.php`
**Status:** DEPRECATED - Already delegates to FirebaseSyncService
**Lines:** 53

#### References Found:
**NONE** - Not registered in EventServiceProvider (replaced by UnifiedFirebaseSyncListener)

#### Current State:
- Already delegates all operations to FirebaseSyncService
- Marked as DEPRECATED in class docblock
- Handles: PaymentVerified

#### Migration Path:
Already compliant - keep as thin wrapper for backward compatibility:
```php
class SyncPaymentEventsToFirebase
{
    public function __construct(
        private readonly FirebaseSyncService $firebaseSyncService,
    ) {}

    public function handle(object $event): void
    {
        // Already delegates to firebaseSyncService->syncEvent()
    }
}
```

---

### 6. SyncRatingEventsToFirebase
**File:** `app/Listeners/Firebase/SyncRatingEventsToFirebase.php`
**Status:** DEPRECATED - Already delegates to FirebaseSyncService
**Lines:** 56

#### References Found:
**NONE** - Not registered in EventServiceProvider (replaced by UnifiedFirebaseSyncListener)

#### Current State:
- Already delegates all operations to FirebaseSyncService
- Marked as DEPRECATED in class docblock
- Handles: Review creation events

#### Migration Path:
Already compliant - keep as thin wrapper for backward compatibility:
```php
class SyncRatingEventsToFirebase
{
    public function __construct(
        private readonly FirebaseSyncService $firebaseSyncService,
    ) {}

    public function handle(object $event): void
    {
        // Already delegates to firebaseSyncService->syncEvent()
    }
}
```

---

## Current Architecture State

### Unified Classes (Target Architecture)

#### FirebaseSyncService
**File:** `app/Services/Firebase/FirebaseSyncService.php`
**Status:** PRODUCTION READY
**Lines:** 1529

**Methods Implemented:**
- ✅ `isEnabled()` - Check if Firebase is enabled
- ✅ `bootstrapSchema()` - Bootstrap Firestore schema
- ✅ `syncSupabaseToFirestore()` - Sync data from Supabase
- ✅ `syncEvent()` - Main entry point for all event-driven syncs
- ✅ `handleDriverAssigned()` - Handle driver assignment
- ✅ `handleTripStarted()` - Handle trip started
- ✅ `handleTripCompleted()` - Handle trip completed
- ✅ `handlePaymentCompleted()` - Handle payment completed
- ✅ `handleRatingSubmitted()` - Handle rating submitted
- ✅ `handleDriverLocationUpdated()` - Handle driver location updated
- ✅ `handleUserCreated()` - Handle user created
- ✅ `handleDriverCreated()` - Handle driver created
- ✅ `handleTripCancelled()` - Handle trip cancelled

#### FirebaseBootstrapService
**File:** `app/Services/Firebase/FirebaseBootstrapService.php`
**Status:** PRODUCTION READY
**Lines:** 388

**Methods Implemented:**
- ✅ `isEnabled()` - Check if Firebase is enabled
- ✅ `isBootstrapEnabled()` - Check if bootstrap is enabled
- ✅ `bootstrapSchema()` - Bootstrap Firestore schema
- ✅ `seedDefaultDocuments()` - Seed system documents
- ✅ `validateSchemaHealth()` - Validate schema health
- ✅ `getRequiredCollections()` - Get required collections list

#### UnifiedFirebaseSyncListener
**File:** `app/Listeners/Firebase/UnifiedFirebaseSyncListener.php`
**Status:** ACTIVE - Registered in EventServiceProvider
**Lines:** ~200

**Events Handled:**
- ✅ TripMatched
- ✅ TripStarted
- ✅ TripCompleted
- ✅ MotorcycleTripStarted
- ✅ MotorcycleDriverArrived
- ✅ MotorcycleTripCompleted
- ✅ PaymentVerified
- ✅ DriverLocationUpdated
- ✅ Review (rating events)

---

## EventServiceProvider Configuration

**File:** `app/Providers/EventServiceProvider.php`

**Current State:**
- ✅ All Firebase sync events route through `UnifiedFirebaseSyncListener`
- ✅ Legacy listeners (SyncTripEventsToFirebase, SyncPaymentEventsToFirebase, SyncRatingEventsToFirebase) are NOT registered
- ✅ Single source of truth for Firebase sync

**Event Mappings:**
```php
protected $listen = [
    // Trip Events - Unified Firebase Sync
    \App\Events\Domain\TripMatched::class => [
        \App\Listeners\BroadcastTripEvents::class,
        \App\Listeners\Firebase\UnifiedFirebaseSyncListener::class,
    ],
    \App\Events\Domain\TripStarted::class => [
        \App\Listeners\BroadcastTripEvents::class,
        \App\Listeners\Firebase\UnifiedFirebaseSyncListener::class,
    ],
    \App\Events\Domain\TripCompleted::class => [
        \App\Listeners\BroadcastTripEvents::class,
        \App\Listeners\Firebase\UnifiedFirebaseSyncListener::class,
    ],
    
    // Motorcycle Trip Events - Unified Firebase Sync
    \App\Events\MotorcycleTripStarted::class => [
        \App\Listeners\Firebase\UnifiedFirebaseSyncListener::class,
    ],
    \App\Events\MotorcycleDriverArrived::class => [
        \App\Listeners\Firebase\UnifiedFirebaseSyncListener::class,
    ],
    \App\Events\MotorcycleTripCompleted::class => [
        \App\Listeners\Firebase\UnifiedFirebaseSyncListener::class,
    ],
    
    // Payment Events - Unified Firebase Sync
    \App\Events\Domain\PaymentVerified::class => [
        \App\Listeners\Firebase\UnifiedFirebaseSyncListener::class,
    ],
    
    // Driver Location Events - Unified Firebase Sync
    \App\Events\DriverLocationUpdated::class => [
        \App\Listeners\Firebase\UnifiedFirebaseSyncListener::class,
    ],
    
    // Rating Events - Unified Firebase Sync
    \App\Models\Review::class => [
        \App\Listeners\Firebase\UnifiedFirebaseSyncListener::class,
    ],
];
```

---

## Jobs Audit

### FirebaseSyncJob
**File:** `app/Jobs/FirebaseSyncJob.php`
**Status:** NEEDS MIGRATION
**Dependency:** FirebaseSync

**Actions Handled:**
- sync_user_creation
- sync_user_profile_update
- sync_driver_profile_creation
- sync_driver_status
- sync_driver_location
- sync_trip_creation
- sync_trip_status_update
- sync_trip_completion
- sync_rating_creation
- batch_sync

**Migration Strategy:**
Replace FirebaseSync with FirebaseSyncService in constructor injection.

---

### DriverLocationSyncJob
**File:** `app/Jobs/DriverLocationSyncJob.php`
**Status:** NEEDS MIGRATION
**Dependency:** FirebaseSync

**Actions Handled:**
- syncDriverLocation()

**Migration Strategy:**
Replace FirebaseSync with FirebaseSyncService in constructor injection.

---

## Service Providers Audit

### AppServiceProvider
**File:** `app/Providers/AppServiceProvider.php`
**Status:** NO Firebase references

**Finding:** No Firebase-related registrations or bootstrapping.

---

### EventServiceProvider
**File:** `app/Providers/EventServiceProvider.php`
**Status:** COMPLIANT

**Finding:** Already uses UnifiedFirebaseSyncListener exclusively.

---

## Console Commands Audit

**Directory:** `app/Console/Commands`

**Finding:** No Firebase-related console commands found.

**Recommendation:** Create artisan commands for:
- `firebase:bootstrap` - Bootstrap Firestore schema
- `firebase:schema-health` - Validate schema health
- `firebase:validate-system` - Production validation

---

## Health Checks Audit

### FirebaseHealthCheck
**File:** `app/Services/Health/Checks/FirebaseHealthCheck.php`
**Status:** NEEDS MIGRATION
**Dependency:** FirebaseRealtimeService

**Migration Strategy:**
Replace FirebaseRealtimeService with FirebaseSyncService for health checks.

---

## Configuration Audit

### Firebase Config
**File:** `config/firebase.php`
**Status:** COMPLIANT

**Settings:**
- ✅ `firebase.enabled` - Master enable/disable
- ✅ `firebase.bootstrap_enabled` - Bootstrap enable/disable
- ✅ `firebase.sync.*` - Sync configuration
- ✅ `firebase.collections.*` - Collection mappings
- ✅ `firebase.logging.*` - Logging configuration

---

## Migration Priority

### High Priority (Blocking)
1. **FirebaseSync** - Used by 2 jobs and FirebaseEventDispatcher
2. **DriverLocationSyncJob** - Depends on FirebaseSync
3. **FirebaseSyncJob** - Depends on FirebaseSync
4. **FirebaseHealthCheck** - Depends on FirebaseRealtimeService

### Medium Priority (Non-blocking)
5. **FirebaseRealtimeService** - Already partially migrated, used by health check
6. **FirebaseEventDispatcher** - No active references, but should be wrapped

### Low Priority (Already Compliant)
7. **SyncTripEventsToFirebase** - Already delegates to FirebaseSyncService
8. **SyncPaymentEventsToFirebase** - Already delegates to FirebaseSyncService
9. **SyncRatingEventsToFirebase** - Already delegates to FirebaseSyncService

---

## Next Steps

### Phase B - Compatibility Layer
Convert legacy classes to thin wrappers delegating to FirebaseSyncService:
- FirebaseSync → delegate all methods to FirebaseSyncService
- FirebaseEventDispatcher → delegate all methods to FirebaseSyncService
- FirebaseRealtimeService → keep read-only, delegate writes to FirebaseSyncService
- SyncTripEventsToFirebase → already compliant
- SyncPaymentEventsToFirebase → already compliant
- SyncRatingEventsToFirebase → already compliant

### Phase C - Complete FirebaseSyncService
Verify all required methods exist and are production-ready:
- syncUser()
- syncDriver()
- syncTrip()
- syncTripEvent()
- syncDriverLocation()
- syncPaymentEvent()
- syncChatRoom()
- syncChatMessage()
- syncPresence()
- syncDeviceToken()
- syncNotification()
- syncSupabaseToFirestore()

### Phase D - Firestore Bootstrap Validation
Enhance FirebaseBootstrapService with:
- validateSchemaHealth() - Already implemented
- Add artisan commands: firebase:bootstrap, firebase:schema-health

### Phase E - Production Validation Command
Create `php artisan firebase:validate-system` command with readiness score (0-100).

### Phase F - Safe Removal Report
Generate removal plan for legacy classes after migration complete.

### Phase G - Production Readiness Report
Generate comprehensive production readiness report with architecture diagrams.

---

## Summary

**Total Legacy Classes:** 6
**Total References Found:** 5
**Already Delegating to FirebaseSyncService:** 3
**Need Migration:** 3
**No References (Safe to Wrap):** 1

**Migration Complexity:** LOW
**Risk Level:** LOW
**Estimated Effort:** 2-3 hours

**Recommendation:** Proceed with Phase B (Compatibility Layer) to convert legacy classes to thin wrappers, then validate with Phase E (Production Validation Command).
