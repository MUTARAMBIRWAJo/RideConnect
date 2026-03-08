# Responsive Role Dashboards (RideConnect)

## Updated Frontend Structure

```text
app/Filament/
  Pages/
    Dashboard.php                 # Role-aware redirect entrypoint
    SuperDashboard.php
    AdminDashboard.php
    AccountantDashboard.php
    OfficerDashboard.php
    DriverDashboard.php           # New
    PassengerDashboard.php        # New
  Support/
    RoleDashboardConfig.php       # Role -> widgets/columns resolver
  Widgets/
    Dashboard/
      ActivityFeedWidget.php      # New reusable feed widget
      NotificationsWidget.php     # New reusable notification widget
      MonthlyEarningsChartWidget.php
      TransactionsTableWidget.php
      ...
resources/views/filament/widgets/dashboard/
  activity-feed-widget.blade.php  # New responsive component view
  notifications-widget.blade.php  # New responsive component view
config/
  dashboard.php                   # Role layout and breakpoint config
```

## Mobile-First Responsive Strategy

- Grid columns are role-configurable in `config/dashboard.php`.
- Default layout is 1 column on small screens, then expands on `md/xl`.
- Widgets use compact card spacing and stack vertically on narrow devices.
- Table/chart widgets remain full-width where needed (`columnSpan = 'full'`).

Example role columns:

```php
'columns' => [
  'default' => 1,
  'md' => 2,
  'xl' => 3,
]
```

## Role-Based Widget Rendering

Dashboards now pull widgets from centralized config instead of hardcoded arrays.

```php
public function getWidgets(): array
{
    return RoleDashboardConfig::widgetsForRole(UserRole::ADMIN->value);
}

public function getColumns(): int | string | array
{
    return RoleDashboardConfig::columnsForRole(UserRole::ADMIN->value);
}
```

Supported roles:

- `SUPER_ADMIN`
- `ADMIN`
- `ACCOUNTANT`
- `OFFICER`
- `DRIVER`
- `PASSENGER`

## Real-Time Updates

Polling is configurable globally in `config/dashboard.php`:

```php
'realtime' => [
  'enabled' => env('DASHBOARD_REALTIME_ENABLED', true),
  'polling_interval' => env('DASHBOARD_POLLING_INTERVAL', '30s'),
],
```

Widgets can opt-in by implementing:

```php
protected function getPollingInterval(): ?string
{
    return RoleDashboardConfig::pollingInterval();
}
```

Environment tuning:

```env
DASHBOARD_REALTIME_ENABLED=true
DASHBOARD_POLLING_INTERVAL=15s
```

## Touch + Accessibility Notes

- Cards and counters use larger tap targets.
- Semantic headings are preserved in section widgets.
- Reduced visual density on mobile prevents accidental taps.
- Text contrast follows Filament theme tokens.

## Performance Notes

- Role dashboards only load relevant widgets.
- Polling can be disabled globally for low-resource environments.
- Full-width heavy widgets remain limited to roles that need them.
