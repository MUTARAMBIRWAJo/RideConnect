# RideConnect Dashboard System - Visual Guide

## System Architecture Diagram

```
┌─────────────────────────────────────────────────────────────────────────┐
│                           USER LOGIN                                     │
└──────────────────────────────┬──────────────────────────────────────────┘
                               │
                               ▼
                    ┌─────────────────────┐
                    │  Session Created    │
                    │  User Authenticated │
                    └──────────┬──────────┘
                               │
                               ▼
                    ┌─────────────────────┐
                    │   Navigate to /admin │
                    └──────────┬──────────┘
                               │
                               ▼
                   ┌──────────────────────────┐
                   │  Dashboard::mount()      │
                   │  (Main Router)           │
                   └──────────┬───────────────┘
                              │
                ┌─────────────┼─────────────┐
                │ Resolve User Role Value   │
                │ (Enum OR Spatie Roles)    │
                └─────────────┬─────────────┘
                              │
              ┌───────────────┼───────────────┐
              │                               │
         ┌────▼─────┐                  ┌─────▼────┐
         │ ENUM Role│                  │Spatie Role
         │ Check    │                  │Check
         └────┬─────┘                  └────┬────┘
              │                             │
              └────────────────┬────────────┘
                               │
        ┌──────────────────────▼──────────────────┐
        │ Match Role in Switch Statement          │
        └──────────────────────┬──────────────────┘
                               │
        ┌──────────────────────┴────────────────────┐
        │                                           │
  ┌─────▼──────┐  ┌────────────┐  ┌─────────────┐
  │SUPER_ADMIN │  │   ADMIN    │  │ ACCOUNTANT  │
  │     ↓      │  │     ↓      │  │      ↓      │
  │ super-     │  │ admin-     │  │accountant-  │
  │dashboard   │  │dashboard   │  │dashboard    │
  └─────┬──────┘  └────────────┘  └─────────────┘
        │
  ┌─────▼──────┐  ┌────────────┐  ┌─────────────┐
  │OFFICER     │  │ DRIVER     │  │ PASSENGER   │
  │    ↓       │  │     ↓      │  │      ↓      │
  │ officer-   │  │ driver-    │  │passenger-   │
  │dashboard-v2│  │dashboard   │  │dashboard    │
  └─────┬──────┘  └────────────┘  └─────────────┘
        │
        └─────────────────┬────────────────────┘
                          │
                          ▼
            ┌────────────────────────────┐
            │Redirect Route Constructed  │
            │filament.admin.pages.{name} │
            └────────────┬───────────────┘
                         │
                         ▼
            ┌────────────────────────────┐
            │Dashboard::canAccess()      │
            │Validate User Has Role      │
            └────────────┬───────────────┘
                         │
              ┌──────────┴──────────┐
              │                     │
          ┌───▼────┐            ┌──▼────┐
          │  PASS  │            │ FAIL  │
          └───┬────┘            └──┬────┘
              │                    │
              ▼                    ▼
        ┌──────────┐        ┌──────────────┐
        │Load Page │        │abort_unless( │
        │Render    │        │false, 403)   │
        │Widgets   │        │              │
        └──────────┘        └──────────────┘
              │                    │
              ▼                    ▼
        ┌──────────┐        ┌──────────────┐
        │Dashboard │        │403 Forbidden │
        │Displayed │        │Error Page    │
        └──────────┘        └──────────────┘
```

## Role Permission Matrix

```
┌──────────────┬─────────────┬──────────────┬──────────┬────────┐
│ User Role    │ Dashboard   │ Can View All │ Can Edit │ Access │
│              │ Route       │ Data         │ Settings │ Level  │
├──────────────┼─────────────┼──────────────┼──────────┼────────┤
│ SUPER_ADMIN  │ /super-db   │ ✅ YES      │ ✅ YES   │ FULL   │
│ ADMIN        │ /admin-db   │ ⚠ LIMITED   │ ⚠ LIMITED│ HIGH   │
│ ACCOUNTANT   │ /acct-db    │ 💰 FINANCE  │ 💰 BILLING│MEDIUM  │
│ OFFICER      │ /officer-db │ 📋 ADMIN    │ 📋 LOGS   │ MEDIUM │
│ DRIVER       │ /driver-db  │ 🚗 OWN DATA │ ❌ NO    │ LOW    │
│ PASSENGER    │ /pass-db    │ 🚖 OWN DATA │ ❌ NO    │ LOW    │
└──────────────┴─────────────┴──────────────┴──────────┴────────┘
```

## Data Access Hierarchy

```
                    SUPER_ADMIN
                         │
                ┌────────┴────────┐
                │                 │
              ADMIN            ACCOUNTANT
                │                 │
         ┌──────┤                 │
         │      │                 │
      OFFICER DRIVER          (Finance Data)
              (Ledgers)         │
                │          ┌─────┘
         ┌──────┴──────┐   │
         │             │   │
    (Ride Data)   (Own Data)
                │
            PASSENGER
              (Own Data)

Key:
- Arrows show who can see data
- SUPER_ADMIN sees ALL
- ADMIN sees organizational data
- ACCOUNTANT sees financial data
- OFFICER sees compliance/audit data
- Drivers/Passengers see only their own data
```

## Widget Loading Flow

```
Dashboard::mount()
    │
    ├─→ Check canAccess()
    │
    ├─→ Load Widgets
    │    │
    │    ├─→ getHeaderWidgets()
    │    │    (Top of page)
    │    │
    │    ├─→ getWidgets() or getColumns()
    │    │    (Main dashboard grid)
    │    │
    │    └─→ getFooterWidgets()
    │         (Bottom of page)
    │
    └─→ Render Dashboard
         with filtered data
         based on role
```

## Configuration Flow

```
┌──────────────────────────────────────────────────┐
│  AdminPanelProvider::panel()                     │
└────────────────────┬─────────────────────────────┘
                     │
         ┌───────────┴───────────┐
         │                       │
    ┌────▼────┐           ┌──────▼─────┐
    │pages()[] │           │discoverPages
    │register  │           │() auto-scan
    │          │           │
    │- Super*  │           │ Finds all:
    │- Admin*  │           │ - Dashboard.php
    │- Account*│           │ - *Dashboard.php
    │- Officer*│           │ - BiDashboard
    │- Driver* │           │ - Compliance*
    │- Passenger
    │- BI      │
    └────┬────┘
         │
    ┌────▼──────────────────────┐
    │Filament Registers Routes   │
    │filament.admin.pages.*      │
    └────────────────────────────┘
```

## Request Flow Timeline

```
Timeline:
─────────────────────────────────────────────────────────────

1. User clicks login
   └─→ Auth::attempt()

2. Session created
   └─→ \Auth user stored

3. User visits /admin
   └─→ GET /admin/admin

4. Filament routes to Dashboard.php
   └─→ Dashboard::render()

5. Dashboard.mount() called
   └─→ resolveUserRoleValue()
   └─→ Determine target route
   └─→ redirectRoute('filament.admin.pages.*')

6. Browser redirects to specific dashboard
   └─→ GET /admin/{dashboard-path}/

7. Dashboard page renders
   └─→ canAccess() validates
   └─→ getWidgets() loads
   └─→ render() shows page

8. User sees dashboard
   └─→ Fully rendered with widgets
   └─→ Only data user has access to
```

## Error Handling Flow

```
User attempts unauthorized access:

GET /admin/super-dashboard (as ADMIN user)
     │
     └─→ SuperDashboard::canAccess()
          │
          └─→ userHasRole()
               │
               └─→ Check enum/spatie roles
                    │
              ┌─────▼─────┐
              │  Match?   │
              └─┬────────┬┘
                │        │
              ✅ YES    ❌ NO
                │        │
                │    abort_unless
                │    (self::canAccess(), 403)
                │        │
                ▼        ▼
            Render    Throw HttpException
            Page      403 Forbidden
                      (Show error page)
```

## State Machine

```
                    ┌──────────────┐
                    │ NOT BY PASS  │
                    │ (Redirected) │
                    └──────────────┘
                           ▲
                           │
        ┌──────────────────┴─────────────────┐
        │ Invalid Role or No Auth            │
        │ Exception thrown                   │
        │ abort_unless() trigger             │
        │                                    │
    ┌───▼────────────────────────────────────┴──┐
    │          USER AUTHENTICATED                │
    │         (Session Active)                   │
    └────┬────────────────────────┬──────────────┘
         │                        │
         └──────────┬─────────────┘
                    │
    ┌───────────────▼────────────────┐
    │ CHECK ROLE MATCHES DASHBOARD   │
    │ userHasRole()                  │
    └───┬─────────────────────────┬──┘
        │ Match Found             │ No Match
        │                         │
    ┌───▼──────────────┐    ┌─────▼──────────┐
    │ LOAD DASHBOARD   │    │ REDIRECT AGAIN │
    │ render()         │    │ to default     │
    │ (Success)        │    │ or abort 403   │
    └──────────────────┘    └────────────────┘
         │ ✅
         └─→ Dashboard Visible to User
```

## File Structure

```
app/Filament/Pages/
├── Dashboard.php                          ← Main Router/Gateway
├── SuperDashboard.php                     ← SUPER_ADMIN dashboard
├── AdminDashboard.php                     ← ADMIN dashboard
├── AccountantDashboard.php                ← ACCOUNTANT dashboard
├── OfficerDashboardV2.php                 ← OFFICER dashboard
├── DriverDashboard.php                    ← DRIVER dashboard
├── PassengerDashboard.php                 ← PASSENGER dashboard
├── BiDashboard.php                        ← Analytics (SUPER_ADMIN, ACCOUNTANT)
├── ComplianceDashboard.php                ← Compliance (SUPER_ADMIN, ACCOUNTANT)
├── AIMonitoringDashboard.php              ← AI Monitor (SUPER_ADMIN, ADMIN)
└── Concerns/
    └── HandlesRoleDashboards.php          ← Role checking trait

app/Providers/Filament/
└── AdminPanelProvider.php                 ← Registers all dashboards

config/
├── services.php                           ← AI service config
└── auth.php

app/Enums/
└── UserRole.php                           ← Role enum (6 values)
```

---

This visual guide helps understand how RideConnect routes users to their appropriate dashboards based on their assigned roles.
