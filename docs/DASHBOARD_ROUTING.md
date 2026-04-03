# Dashboard Role-Based Routing Guide

## Overview

The RideConnect Filament dashboards are configured to automatically redirect users to their role-specific dashboard upon login. This system ensures that each user sees only the data and widgets relevant to their role.

## Architecture

### Main Router: `Dashboard.php`

The main dashboard page acts as a router that:
1. Checks the authenticated user's role
2. Determines the appropriate dashboard based on role
3. Redirects to the role-specific dashboard

**File:** `app/Filament/Pages/Dashboard.php`

### Role-to-Dashboard Mapping

| User Role | Dashboard | Route | Permissions |
|-----------|-----------|-------|------------|
| SUPER_ADMIN | SuperDashboard | `/admin/super-dashboard` | All data, system-wide metrics |
| ADMIN | AdminDashboard | `/admin/admin-dashboard` | Admin operations, user management |
| ACCOUNTANT | AccountantDashboard | `/admin/accountant-dashboard` | Finance, accounting, billing |
| OFFICER | OfficerDashboardV2 | `/admin/officer-dashboard-v2` | Compliance, audit logs |
| DRIVER | DriverDashboard | `/admin/driver-dashboard` | Personal rides, earnings |
| PASSENGER | PassengerDashboard | `/admin/passenger-dashboard` | Booking history, trips |

## Key Components

### 1. UserRole Enum (`app/Enums/UserRole.php`)

Defines all available roles:
- **Manager Roles:** SUPER_ADMIN, ADMIN, ACCOUNTANT, OFFICER
- **Mobile User Roles:** DRIVER, PASSENGER

### 2. HandlesRoleDashboards Trait

Location: `app/Filament/Pages/Concerns/HandlesRoleDashboards.php`

Provides helper methods:
- `resolveUserRoleValue()`: Gets role value from User model
- `userHasRole()`: Checks if user has specific role (Spatie + Enum)
- `userHasAnyRole()`: Checks if user has any of given roles

### 3. Dashboard Registration

**File:** `app/Providers/Filament/AdminPanelProvider.php`

All dashboards are registered and discovered via:
```php
->pages([
    Dashboard::class,
    \App\Filament\Pages\SuperDashboard::class,
    \App\Filament\Pages\AdminDashboard::class,
    \App\Filament\Pages\AccountantDashboard::class,
    \App\Filament\Pages\OfficerDashboardV2::class,
    \App\Filament\Pages\DriverDashboard::class,
    \App\Filament\Pages\PassengerDashboard::class,
    \App\Filament\Pages\BiDashboard::class,
    \App\Filament\Pages\ComplianceDashboard::class,
    \App\Filament\Pages\AIMonitoringDashboard::class,
])
```

## How It Works

### 1. User Accesses `/admin`

```
GET /admin
  ↓
Dashboard::mount() is called
  ↓
Check user role via resolveUserRoleValue()
  ↓
Match role to route
  ↓
Redirect to specific dashboard
```

### 2. Role Permission Checks

Each dashboard implements:
- `canAccess()`: Server-side access control
- `canView()`: View-level access control
- `shouldRegisterNavigation()`: Navigation visibility
- `mount()`: Abort with 403 if user doesn't have role

Example:
```php
public static function canAccess(): bool
{
    return static::userHasRole(auth()->user(), 'Super_admin', UserRole::SUPER_ADMIN);
}
```

### 3. Fallback Behavior

If user role is not recognized:
- Main Dashboard redirects to denied page (403)
- User must use correct credentials with valid role

## Access Control Flow

```
User Login
  ↓
Check user role (Enum or Spatie)
  ↓
Route to Dashboard.php
  ↓
Mount method redirects to role-specific dashboard
  ↓
Dashboard.canAccess() validates permission
  ↓
If valid: Show widgets
If invalid: Abort 403
```

## Available Special Dashboards

### AI Monitoring Dashboard
- **Roles:** SUPER_ADMIN, ADMIN
- **Purpose:** Monitor AI model accuracy and performance
- **Route:** `/admin/ai-monitoring`

### BI Dashboard (Analytics)
- **Roles:** SUPER_ADMIN, ACCOUNTANT
- **Purpose:** Business intelligence and reporting
- **Route:** `/admin/analytics` (via navigation)

### Compliance Dashboard
- **Roles:** SUPER_ADMIN, ACCOUNTANT
- **Purpose:** Regulatory compliance reporting
- **Route:** Via navigation menu

## Testing Dashboard Routing

Run the dashboard routing tests:

```bash
php artisan test tests/Feature/DashboardRoutingTest.php
```

Tests verify:
- ✅ Each role redirects to correct dashboard
- ✅ Access control is enforced (403 for unauthorized)
- ✅ Unauthenticated users are redirected to login
- ✅ Dashboard pages load successfully for authorized users

## Troubleshooting

### User Redirected to Wrong Dashboard
1. Check user's `role` field in database
2. Compare against `UserRole` enum values
3. Verify Spatie roles are synced if using role tables
4. Check `HandlesRoleDashboards::resolveUserRoleValue()`

### 403 Forbidden Error
1. Verify user role is set correctly
2. Check dashboard's `canAccess()` method
3. Ensure role name matches enum cases
4. Clear cache: `php artisan cache:clear`

### Dashboard Not Accessible
1. Verify dashboard is registered in `AdminPanelProvider.php`
2. Check route path matches Filament naming convention
3. Verify dashboard class extends `\Filament\Pages\Dashboard`
4. Check `mount()` method authorization

## Configuration

### Environment Variables

```env
# No special env vars needed for dashboard routing
# Dashboard configuration is code-based in
# app/Filament/Pages/
```

### AI Service Configuration

Located in `config/services.php`:
```php
'ai_service' => [
    'url' => env('RIDE_AI_BASE_URL', 'https://rideconnect-ai.onrender.com'),
    'key' => env('RIDE_AI_API_KEY'),
    'timeout' => env('RIDE_AI_TIMEOUT', 10),
],
```

## Future Enhancements

1. Role-based widget visibility
2. Customizable dashboard layouts per role
3. Dashboard analytics and usage tracking
4. Role-based feature flags for experimental dashboards
5. Cross-role dashboard access with audit logging

## References

- Filament Documentation: https://filamentphp.com/docs
- Laravel Enum Documentation: https://laravel.com/docs/enums
- Spatie Laravel Permissions: https://spatie.be/docs/laravel-permission
