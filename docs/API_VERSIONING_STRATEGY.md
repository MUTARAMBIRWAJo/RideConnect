# RideConnect API Versioning Strategy

This document outlines the API versioning strategy for RideConnect, designed to support 5+ years of growth while maintaining backward compatibility.

## 1. Versioning Approach: URI Versioning

We use **URI Path Versioning** as our primary strategy:

```
/api/v1/...
/api/v2/...
```

### Why URI Versioning?

| Approach | Pros | Cons |
|----------|------|------|
| URI Path (`/api/v1/`) | Simple, explicit, cacheable | URL changes on version bump |
| Query String (`?version=1`) | URL stays same | Harder to cache, less explicit |
| Header (`Accept: version=1`) | Clean URLs | Requires custom headers |

**Decision**: URI Path versioning is the most explicit and industry-standard approach (used by Stripe, Slack, Azure, etc.)

## 2. Folder Structure

```
app/
├── Http/
│   └── Controllers/
│       └── Api/
│           ├── V1/
│           │   ├── AuthController.php
│           │   ├── PassengerController.php
│           │   ├── RiderController.php
│           │   └── ...
│           └── V2/
│               ├── AuthController.php
│               ├── PassengerController.php
│               ├── RiderController.php
│               └── ...
routes/
├── api_v1.php
├── api_v2.php
resources/
└── api/
    └── V1/
        └── Resources/
            └── UserResource.php
```

## 3. Route Version Grouping

### Current Implementation (routes/api.php)

```php
Route::prefix('v1')->group(function () {
    // All v1 endpoints
    Route::prefix('auth')->group(function () {...});
    Route::prefix('passenger')->group(function () {...});
    Route::prefix('rider')->group(function () {...});
});
```

### Recommended Structure for v2

Create separate route files:

```php
// routes/api_v1.php
Route::prefix('v1')->group(base_path('routes/api_v1.php'));

// routes/api_v2.php
Route::prefix('v2')->group(base_path('routes/api_v2.php'));
```

## 4. Controller Versioning Strategy

### Option A: Separate Controllers (Recommended)

```php
// V1 Controllers
app/Http/Controllers/Api/V1/AuthController.php
app/Http/Controllers/Api/V1/PassengerController.php

// V2 Controllers
app/Http/Controllers/Api/V2/AuthController.php
app/Http/Controllers/Api/V2/PassengerController.php
```

Routes in `routes/api_v1.php`:
```php
use App\Http\Controllers\Api\V1\AuthController;
Route::post('/auth/login', [AuthController::class, 'login']);
```

Routes in `routes/api_v2.php`:
```php
use App\Http\Controllers\Api\V2\AuthController;
Route::post('/auth/login', [AuthController::class, 'login']);
```

### Option B: Single Controller with Version Check

```php
public function login(Request $request)
{
    $version = $request->header('API-Version', 'v1');
    
    if ($version === 'v2') {
        return $this->loginV2($request);
    }
    
    return $this->loginV1($request);
}
```

**Recommendation**: Option A for cleaner separation and easier maintenance.

## 5. API Resource Versioning

```php
// resources/api/V1/Users/UserResource.php
namespace App\Http\Resources\Api\V1\Users;

class UserResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
        ];
    }
}

// resources/api/V2/Users/UserResource.php
namespace App\Http\Resources\Api\V2\Users;

class UserResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,  // Added in v2
            'name' => $this->name,
            'email' => $this->email,
            'profile' => new ProfileResource($this->profile),  // Expanded in v2
        ];
    }
}
```

## 6. Version-Based Middleware

Create version-aware middleware:

```php
// app/Http/Middleware/ApiVersion.php
public function handle($request, Closure $next)
{
    // Extract version from URL
    $version = $request->segment(3); // /api/v1/...
    
    // Set version in request for controllers to use
    $request->attributes->set('api_version', $version);
    
    return $next($request);
}
```

Apply in routes:

```php
Route::prefix('v1')->middleware(['api', 'api_version:v1'])->group(function () {...});
Route::prefix('v2')->middleware(['api', 'api_version:v2'])->group(function () {...});
```

## 7. Database Migration Strategy

### Schema Changes Between Versions

| Version | Change Type | Strategy |
|---------|-------------|----------|
| v1 → v2 | Add field | Add nullable column, no migration needed |
| v1 → v2 | Remove field | Keep column, mark deprecated |
| v1 → v2 | Rename field | Add new, copy data, deprecate old |
| v1 → v2 | Change type | Add new column, migrate data |

### Migration Example

```php
// Add phone_country_code in v2
Schema::table('users', function (Blueprint $table) {
    $table->string('phone_country_code', 5)->nullable()->after('phone');
});

// Mark old phone field as deprecated (don't drop yet)
```

## 8. Deprecation Policy

### Sunset Headers

Add deprecation headers to responses:

```php
// In AppServiceProvider or middleware
response()->withHeaders([
    'Sunset' => 'Sat, 01 Jan 2027 00:00:00 GMT',
    'Deprecation' => 'Sat, 01 Jan 2027 00:00:00 GMT',
    'Link' => '<https://api.rideconnect.com/api/v2/auth/login>; rel="latest-version"',
]);
```

### Deprecation Timeline

```
v1.0 - Initial release (2024)
v1.1 - Bug fixes only
v1.2 - Bug fixes + security patches
v2.0 - New features (2025) - v1 marked deprecated
v2.1 - v1 still supported
v2.2 - v1 security fixes only
v3.0 - v1 deprecated (2026)
```

## 9. Mobile App Version Detection

### Flutter Integration

```dart
class ApiClient {
  static const String baseUrl = 'https://api.rideconnect.com/api';
  
  // Check supported versions
  static Future<void> checkApiVersion() async {
    try {
      final response = await dio.get('$baseUrl/version');
      final data = response.data;
      
      if (data['minimum_supported_version'] != null) {
        final minVersion = Version.parse(data['minimum_supported_version']);
        final currentVersion = Version.parse(appVersion);
        
        if (currentVersion < minVersion) {
          // Show update required dialog
          _showUpdateDialog();
        }
      }
      
      // Check for deprecated endpoints
      if (data['deprecated_endpoints'] != null) {
        _logDeprecationWarnings(data['deprecated_endpoints']);
      }
    } catch (e) {
      // Use default v1
    }
  }
}
```

### Version Endpoint

```php
// routes/api.php
Route::get('/version', [ApiController::class, 'version']);

public function version()
{
    return response()->json([
        'current_version' => 'v1',
        'latest_version' => 'v2',
        'minimum_supported_version' => 'v1',
        'deprecated_endpoints' => [
            '/api/v1/auth/login' => 'Use /api/v2/auth/login instead',
        ],
        'sunset_date' => '2027-01-01',
    ]);
}
```

## 10. Upgrade Strategy

### When to Create v2

Create a new version when:
1. **Breaking Changes**: Remove or rename fields, change response format
2. **Major Features**: Add new functionality that changes existing behavior
3. **Security**: Required security improvements that break backward compatibility

### How to Avoid Breaking Production Users

1. **Maintain v1 for 12+ months** after v2 release
2. **Add new fields only** - never remove in v2
3. **Support both versions** in mobile app during transition
4. **Use feature flags** instead of versioning when possible:
   ```php
   // Instead of versioning, use request parameter
   Route::post('/auth/login', [AuthController::class, 'login']);
   // ?new_format=true for new format
   ```
5. **Communicate deprecation early**:
   - 6 months before: Announce deprecation
   - 3 months before: Send push notifications to users
   - 1 month before: Show in-app warnings

### Sunset Policy Example

```
┌─────────────────────────────────────────────────────────────┐
│                    Sunset Policy                            │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│  v1 Release: January 2024                                   │
│  v2 Release: January 2025                                   │
│                                                             │
│  Phase 1 (Jan - Jun 2025):                                 │
│  - Both v1 and v2 active                                    │
│  - v1 returns Deprecation header                            │
│                                                             │
│  Phase 2 (Jul - Dec 2025):                                 │
│  - v1 security fixes only                                   │
│  - v1 returns Sunset header                                 │
│                                                             │
│  Phase 3 (Jan 2026):                                       │
│  - v1 deprecated                                            │
│  - Returns 410 Gone                                          │
│                                                             │
└─────────────────────────────────────────────────────────────┘
```

## 11. Version Comparison

| v1 (Current) | v2 (Planned) |
|--------------|---------------|
| Basic user model | Enhanced user with profile |
| Single auth flow | Multi-factor auth |
| Basic ride booking | Dynamic pricing |
| Simple payments | Payment intents |
| Email notifications | Push + SMS + Email |

## 12. Implementation Checklist

- [ ] Create `app/Http/Controllers/Api/V2/` directory
- [ ] Copy V1 controllers to V2
- [ ] Update V2 with new features
- [ ] Create `routes/api_v2.php`
- [ ] Add version endpoint
- [ ] Update mobile app to detect versions
- [ ] Set up deprecation headers
- [ ] Document migration guide
- [ ] Create test suite for both versions

## 13. Testing Strategy

```php
// Tests for both versions
class ApiVersionTest extends TestCase
{
    public function test_v1_login_returns_token()
    {
        $response = $this->postJson('/api/v1/auth/login', [...]);
        $response->assertStatus(200);
    }
    
    public function test_v2_login_returns_token_and_refresh_token()
    {
        $response = $this->postJson('/api/v2/auth/login', [...]);
        $response->assertStatus(200);
        $response->assertJsonStructure(['refresh_token']);
    }
}
```
