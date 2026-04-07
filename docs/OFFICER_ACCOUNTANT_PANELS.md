# RideConnect Officer & Accountant Panels Setup Guide

## Overview

This document outlines the complete setup and usage of the **Officer Panel** (`/officer`) and **Accountant Panel** (`/accountant`) for RideConnect ride-hailing system.

## Architecture

### Separation of Concerns

- **Admin Panel** (`/admin`): System administrators and super users
- **Officer Panel** (`/officer`): Operations team (ride dispatch, driver management, complaints)
- **Accountant Panel** (`/accountant`): Finance team (revenue tracking, settlements, refunds)

Each panel has:
- Separate URL paths
- Dedicated page classes
- Unique navigation structure
- Role-based access control (RBAC)
- Custom styling & branding

### Key Technology Stack

- Laravel 12.50.0
- Filament v3+ (UI Framework)
- Spatie Permissions (RBAC)
- Blade Templates (Static rendering, no Livewire widgets)
- Tailwind CSS (Styling)

## Officer Panel Features

**URL**: `http://localhost/officer`

### Pages

1. **Dashboard** (`/officer/officer-dashboard`)
   - Real-time KPI cards: Active Rides, Pending Bookings, Open Tickets, Online Drivers
   - Overdue booking alerts, high-priority ticket queue, cancellation tracking
   - Recent bookings & tickets tables
   - Escalation queue & unassigned rides monitoring

2. **Live Rides** (`/officer/live-rides-page`)
   - Real-time ride tracking and monitoring
   - View active ride details: route, driver, status, estimated fare
   - Actions: Force cancel rides, reassign drivers
   - Demand load metrics

3. **Driver Management** (`/officer/driver-management-page`)
   - Complete driver fleet overview
   - Status: online/offline, approved/suspended
   - Driver metrics: rating, completed rides
   - Actions: Approve/suspend drivers, toggle online status
   - Fleet analytics: total drivers, online count, offline count

4. **Complaints & Tickets** (`/officer/complaints-page`)
   - Support ticket management system
   - Complaint types: ride issues, payment disputes, driver complaints
   - Priority levels: normal, high, urgent
   - Actions: Mark reviewed, resolve tickets
   - Statistics: open, resolved complaint counts

5. **AI Insights** (`/officer/ai-insights-page`)
   - Demand heatmap by service area
   - Peak hours analysis with predictive surge detection
   - Weekly revenue & ride trends
   - Average wait time & driver acceptance rate metrics
   - AI-powered recommendations for demand management

## Accountant Panel Features

**URL**: `http://localhost/accountant`

### Pages

1. **Financial Dashboard** (`/accountant/financial-dashboard`)
   - Total revenue & monthly revenue tracking
   - Commission earnings today
   - Payment processing metrics: successful/failed (24h)
   - Pending driver payouts queue
   - Payment retry management
   - Recent payments & failed payment tables

2. **Transactions** (`/accountant/transactions-page`)
   - Ride-by-ride transaction analysis
   - Fare matching: estimated vs. actual comparison
   - Discrepancy detection & highlighting
   - Transaction status tracking
   - Automated validation of pricing accuracy

3. **Driver Earnings** (`/accountant/driver-earnings-page`)
   - Driver income history and breakdown
   - Gross earnings, commission deductions, net earnings
   - Driver settlement status
   - Top earner analytics
   - Payout batch processing

4. **Reports** (`/accountant/reports-page`)
   - Daily financial summary report
   - Monthly comprehensive financial overview
   - Driver settlement reports
   - Tax summary reports
   - Export options: PDF & CSV
   - Batch report generation

5. **Audit Logs** (`/accountant/audit-logs-page`)
   - Immutable audit trail of all financial operations
   - Transaction-level change tracking
   - Valid/suspicious transaction flagging
   - Compliance documentation
   - Full accountability chain

6. **Refund Management** (`/accountant/refund-management-page`)
   - Refund request processing queue
   - Status tracking: pending, approved, rejected
   - Manual fare adjustments
   - Refund payout management
   - Compliance guidelines

## Installation & Setup

### 1. Role & Permission Setup

Create roles with permissions:

```bash
php artisan tinker

# In tinker console:
Role::create(['name' => 'officer', 'guard_name' => 'web']);
Role::create(['name' => 'accountant', 'guard_name' => 'web']);

Permission::create(['name' => 'view rides', 'guard_name' => 'web']);
Permission::create(['name' => 'manage rides', 'guard_name' => 'web']);
Permission::create(['name' => 'manage drivers', 'guard_name' => 'web']);
Permission::create(['name' => 'manage tickets', 'guard_name' => 'web']);
Permission::create(['name' => 'view finances', 'guard_name' => 'web']);
Permission::create(['name' => 'manage finances', 'guard_name' => 'web']);

$officer = Role::findByName('officer');
$officer->givePermissionTo(['view rides', 'manage rides', 'manage drivers', 'manage tickets']);

$accountant = Role::findByName('accountant');
$accountant->givePermissionTo(['view finances', 'manage finances']);
```

### 2. User Role Assignment

```bash
$user = User::find(13); // Your officer user
$user->assignRole('officer');

$user2 = User::find(14); // Your accountant user
$user2->assignRole('accountant');
```

### 3. Panel Registration

Panels are automatically registered in `bootstrap/providers.php`:

```php
return [
    // ... other providers
    App\Providers\Filament\AdminPanelProvider::class,
    App\Providers\Filament\OfficerPanelProvider::class,   // ← New
    App\Providers\Filament\AccountantPanelProvider::class, // ← New
];
```

### 4. Access Control

**Middleware Stack**:

- `EnsureOfficerRole`: Restricts `/officer` panel to users with `officer` role
- `EnsureAccountantRole`: Restricts `/accountant` panel to users with `accountant` role

**Page-Level Guards**:

Each page class implements `canAccess()` method:

```php
public static function canAccess(): bool
{
    return auth()->check() && 
           (auth()->user()->hasRole('officer') || auth()->user()->hasRole('OFFICER'));
}
```

## Panel Provider Configuration

### OfficerPanelProvider

```php
namespace App\Providers\Filament;

class OfficerPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('officer')
            ->path('officer')
            ->colors(['primary' => Color::Green]) // Green theme
            ->discoverPages(app_path('Filament/Pages/Officer'), 'App\\Filament\\Pages\\Officer')
            ->pages([
                OfficerDashboard::class,
                LiveRidesPage::class,
                DriverManagementPage::class,
                ComplaintsPage::class,
                AIInsightsPage::class,
            ])
            ->authMiddleware([
                Authenticate::class,
                EnsureOfficerRole::class,
            ]);
    }
}
```

### AccountantPanelProvider

```php
namespace App\Providers\Filament;

class AccountantPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('accountant')
            ->path('accountant')
            ->colors(['primary' => Color::Amber]) // Amber theme
            ->discoverPages(app_path('Filament/Pages/Accountant'), 'App\\Filament\\Pages\\Accountant')
            ->pages([
                FinancialDashboard::class,
                TransactionsPage::class,
                DriverEarningsPage::class,
                ReportsPage::class,
                AuditLogsPage::class,
                RefundManagementPage::class,
            ])
            ->authMiddleware([
                Authenticate::class,
                EnsureAccountantRole::class,
            ]);
    }
}
```

## Panel URLs

### Officer Panel

- Dashboard: `http://localhost/officer`
- Live Rides: `http://localhost/officer/live-rides-page`
- Driver Management: `http://localhost/officer/driver-management-page`
- Complaints: `http://localhost/officer/complaints-page`
- AI Insights: `http://localhost/officer/ai-insights-page`

### Accountant Panel

- Dashboard: `http://localhost/accountant`
- Transactions: `http://localhost/accountant/transactions-page`
- Driver Earnings: `http://localhost/accountant/driver-earnings-page`
- Reports: `http://localhost/accountant/reports-page`
- Audit Logs: `http://localhost/accountant/audit-logs-page`
- Refunds: `http://localhost/accountant/refund-management-page`

## Data Loading Pattern

All pages use **server-side data loading** via `mount()` method:

```php
public function mount(): void
{
    abort_unless(static::canAccess(), 403);
    
    // Load data from database
    $this->activeRidesCount = $this->resolveActiveRidesCount();
    $this->recentBookings = $this->resolveRecentBookings();
    // ... etc
}

private function resolveActiveRidesCount(): int
{
    if (!Schema::hasTable('rides')) {
        return 0;
    }
    
    return (int) DB::table('rides')
        ->whereIn('status', ['in_progress', 'IN_PROGRESS'])
        ->count();
}
```

**Why This Approach?**
- ✅ No Livewire widget trees (avoids multi-root element errors)
- ✅ Static page rendering (guaranteed single root)
- ✅ Fast performance (no real-time polling)
- ✅ Clean, predictable UI
- ✅ Production-safe & stable

## Blade View Structure

All views follow single-root pattern:

```blade
<x-filament-panels::page>
    <div class="space-y-6">
        <!-- Hero section -->
        <!-- KPI cards -->
        <!-- Tables and content -->
    </div>
</x-filament-panels::page>
```

**Key Components Used:**

- `<x-filament-panels::page>`: Page wrapper (Filament component)
- `<x-dashboard-card>`: Reusable stat card component
- `<table>`: HTML tables for data display (no Livewire components)

## Customization Guide

### Add a New Officer Page

1. Create page class in `app/Filament/Pages/Officer/`:

```php
namespace App\Filament\Pages\Officer;

class MyNewPage extends Page
{
    protected static ?string $navigationGroup = 'My Group';
    protected static string $view = 'filament.pages.officer.my-new-page';
    
    public static function canAccess(): bool
    {
        return auth()->check() && auth()->user()->hasRole('officer');
    }
    
    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);
        // Load data
    }
}
```

2. Create Blade view in `resources/views/filament/pages/officer/`:

```blade
<x-filament-panels::page>
    <div class="space-y-6">
        <!-- Your content here -->
    </div>
</x-filament-panels::page>
```

3. Register in `OfficerPanelProvider`:

```php
->pages([
    // ... existing pages
    \App\Filament\Pages\Officer\MyNewPage::class,
])
```

### Add New Permissions

```bash
php artisan tinker

Permission::create(['name' => 'new_permission', 'guard_name' => 'web']);
Role::findByName('officer')->givePermissionTo('new_permission');
```

### Customize Colors

In panel provider:

```php
->colors([
    'primary' => Color::Green,
    'success' => Color::Green,
    'warning' => Color::Amber,
    'danger' => Color::Red,
])
```

## Testing

### Manual Testing

1. Log in as Officer user
2. Navigate to `http://localhost/officer`
3. Verify all pages load without errors
4. Check role-based access restrictions

```bash
# View user roles
php artisan tinker
User::find(13)->roles;
```

### Testing Access Control

```bash
# This should fail (user without officer role)
curl -H "Authorization: Bearer $TOKEN" http://localhost/officer

# This should succeed (user with officer role)
curl -H "Authorization: Bearer $TOKEN" http://localhost/officer
```

## Troubleshooting

### "403 Forbidden" Error

**Cause**: User doesn't have required role
**Solution**: Assign role to user

```bash
php artisan tinker
User::find(13)->assignRole('officer');
```

### Page Not Found

**Cause**: Route not registered
**Solution**: Verify panel provider is in `bootstrap/providers.php`

```bash
php artisan route:list | grep officer
```

### Missing Data in Tables

**Cause**: Database schema mismatch
**Solution**: Check table/column existence in `mount()` method

```php
if (!Schema::hasTable('rides') || !Schema::hasColumn('rides', 'status')) {
    return 0;
}
```

## Production Deployment

### Pre-Deployment Checklist

- [ ] All roles and permissions created in production DB
- [ ] Users assigned appropriate roles
- [ ] Database migrations run: `php artisan migrate:fresh`
- [ ] Cache cleared: `php artisan cache:clear && `artisan config:cache`
- [ ] Views compiled: `php artisan view:clear`
- [ ] Queue jobs tested (if using async operations)
- [ ] Email notifications configured (for alerts)

### Deployment Commands

```bash
# Clear all caches
php artisan cache:clear
php artisan config:cache
php artisan route:cache
php artisan view:clear

# Migrate database (if new tables added)
php artisan migrate

# Seed roles/permissions (if not already done)
php artisan db:seed --class=RolePermissionSeeder
```

## Performance Optimization

### Database Indexing

Add indexes to frequently queried tables:

```sql
ALTER TABLE rides ADD INDEX idx_status_created (status, created_at);
ALTER TABLE bookings ADD INDEX idx_status_created (status, created_at);
ALTER TABLE tickets ADD INDEX idx_status_priority (status, priority);
ALTER TABLE payments ADD INDEX idx_status_created (status, created_at);
ALTER TABLE driver_payouts ADD INDEX idx_status_driver (status, driver_id);
```

### Caching

Add caching to dashboard queries:

```php
return Cache::remember('active_rides_count', 60, function() {
    return DB::table('rides')
        ->whereIn('status', ['in_progress', 'IN_PROGRESS'])
        ->count();
});
```

## Security Considerations

1. **Role-Based Access Control (RBAC)**
   - Always check user role before displaying sensitive data
   - Use middleware guards on all pages

2. **Audit Logging**
   - All financial actions logged in `audit_logs` table
   - Track user attribution for compliance

3. **Permission Checks**
   - Controller methods verify: `$this->authorize('manage rides')`
   - Page methods check: `if (!auth()->user()->can('view finances'))`

4. **Data Privacy**
   - Personal driver/passenger data marked as sensitive
   - Implement proper GDPR/privacy controls

## Support & Documentation

For updates or feature requests:
1. Check existing pages for similar functionality
2. Reference service classes in `app/Services/`
3. Follow established Blade component patterns
4. Test thoroughly on staging before production deployment

---

**Last Updated**: April 7, 2026
**Version**: 1.0.0
**Status**: Production Ready ✅
