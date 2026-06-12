<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Database Table Protection
    |--------------------------------------------------------------------------
    |
    | When enabled, RideConnect blocks destructive artisan commands and raw SQL
    | DROP/TRUNCATE statements against locked tables. Locked tables are recorded
    | in the schema_table_locks table after migrations complete.
    |
    */

    'enabled' => env('DB_TABLE_PROTECTION', true),

    /*
    |--------------------------------------------------------------------------
    | Auto-lock After Migrate
    |--------------------------------------------------------------------------
    |
    | When true, all public schema tables are registered as locked after each
    | successful migration run.
    |
    */

    'auto_lock_after_migrate' => env('DB_AUTO_LOCK_TABLES', true),

    /*
    |--------------------------------------------------------------------------
    | PostgreSQL Event Trigger Guard
    |--------------------------------------------------------------------------
    |
    | Attempts to install a Postgres DDL event trigger that blocks DROP TABLE on
    | locked tables. May require elevated privileges on managed hosts (Supabase).
    | Laravel-level guards still apply when this is unavailable.
    |
    */

    'postgres_event_trigger' => env('DB_POSTGRES_DROP_GUARD', true),

    /*
    |--------------------------------------------------------------------------
    | Policy Locked Tables (logical names)
    |--------------------------------------------------------------------------
    |
    | Core production tables that must never be dropped without owner approval.
    | Includes policy names mapped to actual RideConnect table names.
    |
    */

    'policy_tables' => [
        'users',
        'mobile_users', // passengers
        'drivers',
        'vehicles',
        'vehicles_v2',
        'trips',
        'trip_status_events', // trip_status_history
        'ride_events', // trip_events
        'payments',
        'reviews',
        'notifications',
        'user_notifications',
        'driver_locations',
        'trip_assignment_attempts', // driver_assignments
        'routes', // public_routes
        'route_stops', // public_stops
        'rides', // public_schedules
        'transport_corridors',
        'corridor_stops',
        'roles',
        'permissions',
        'model_has_roles',
        'model_has_permissions',
        'motorcycle_trips',
        'trip_requests',
        'bookings',
        'migrations',
        'schema_table_locks',
    ],

    /*
    |--------------------------------------------------------------------------
    | Blocked Artisan Commands
    |--------------------------------------------------------------------------
    */

    'blocked_commands' => [
        'migrate:fresh',
        'db:wipe',
        'migrate:refresh',
        'migrate:reset',
        'migrate:rollback',
    ],

];
